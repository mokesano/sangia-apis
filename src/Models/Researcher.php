<?php
declare(strict_types=1);

namespace Sangia\Api\Models;

class Researcher extends BaseModel
{
    private function normalize(array $row): array
    {
        $row['field_of_study']      = $this->decodeJson($row['field_of_study'] ?? '[]');
        $row['expertise_tags']      = $this->decodeJson($row['expertise_tags'] ?? '[]');
        $row['sdgs_primary_goals']  = $this->decodeJson($row['sdgs_primary_goals'] ?? '[]');
        $row['total_publications']  = (int) ($row['total_publications'] ?? 0);
        $row['total_citations']     = (int) ($row['total_citations'] ?? 0);
        $row['h_index']             = (int) ($row['h_index'] ?? 0);
        $row['i10_index']           = (int) ($row['i10_index'] ?? 0);
        $row['wizdam_score']        = (float) ($row['wizdam_score'] ?? 0);
        $row['wizdam_percentile']   = (float) ($row['wizdam_percentile'] ?? 0);
        return $row;
    }

    public function list(array $filters, int $page, int $perPage): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['field']) && $filters['field'] !== 'all') {
            $where[] = 'JSON_CONTAINS(r.field_of_study, JSON_QUOTE(:field))';
            $params[':field'] = $filters['field'];
        }
        if (!empty($filters['province'])) {
            $where[] = 'r.province = :province';
            $params[':province'] = $filters['province'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(r.full_name LIKE :q OR r.bio LIKE :q2)';
            $params[':q'] = '%' . $filters['q'] . '%';
            $params[':q2'] = '%' . $filters['q'] . '%';
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $total = $this->count(
            "SELECT COUNT(*) FROM researchers r WHERE $whereClause",
            $params
        );

        $rows = $this->query(
            "SELECT r.id, r.orcid_id, r.full_name, i.name AS institution_name,
                    r.province, r.city, r.field_of_study, r.expertise_tags,
                    r.sdgs_primary_goals, r.total_publications, r.total_citations,
                    r.h_index, r.i10_index, r.wizdam_score, r.wizdam_percentile,
                    r.profile_image_url
             FROM researchers r
             LEFT JOIN institutions i ON i.id = r.institution_id
             WHERE $whereClause
             ORDER BY r.wizdam_score DESC
             LIMIT :limit OFFSET :offset",
            array_merge($params, [':limit' => $perPage, ':offset' => $offset])
        );

        return [
            'data'  => array_map([$this, 'normalize'], $rows),
            'total' => $total,
        ];
    }

    public function top(int $limit): array
    {
        $rows = $this->query(
            "SELECT r.id, r.orcid_id, r.full_name, i.name AS institution_name,
                    r.province, r.city, r.field_of_study, r.expertise_tags,
                    r.sdgs_primary_goals, r.total_publications, r.total_citations,
                    r.h_index, r.i10_index, r.wizdam_score, r.wizdam_percentile,
                    r.profile_image_url
             FROM researchers r
             LEFT JOIN institutions i ON i.id = r.institution_id
             ORDER BY r.wizdam_score DESC
             LIMIT :limit",
            [':limit' => $limit]
        );

        return array_map([$this, 'normalize'], $rows);
    }

    public function findByOrcid(string $orcidId): ?array
    {
        $row = $this->queryOne(
            "SELECT r.*, i.name AS institution_name, i.province AS inst_province
             FROM researchers r
             LEFT JOIN institutions i ON i.id = r.institution_id
             WHERE r.orcid_id = :orcid",
            [':orcid' => $orcidId]
        );

        if (!$row) return null;

        $row = $this->normalize($row);

        // Impact pillars from impact_scores
        $score = $this->queryOne(
            "SELECT * FROM impact_scores
             WHERE entity_type = 'researcher' AND entity_id = :id
             ORDER BY calculated_at DESC LIMIT 1",
            [':id' => $row['id']]
        );

        if ($score) {
            $row['impact_pillars'] = [
                'academic'      => (float) $score['pillar_academic'],
                'social'        => (float) $score['pillar_social'],
                'economic'      => (float) $score['pillar_economic'],
                'sdg'           => (float) $score['pillar_sdg'],
                'composite'     => (float) $score['composite_score'],
                'calculated_at' => $score['calculated_at'],
            ];
            $row['sdg_tags'] = $this->decodeJson($score['sdg_tags'] ?? '[]');
        } else {
            $row['impact_pillars'] = null;
            $row['sdg_tags'] = [];
        }

        // Score history
        $history = $this->query(
            "SELECT DATE(calculated_at) AS date,
                    composite_score AS composite,
                    pillar_academic AS academic,
                    pillar_social AS social,
                    pillar_economic AS economic,
                    pillar_sdg AS sdg
             FROM impact_scores
             WHERE entity_type = 'researcher' AND entity_id = :id
             ORDER BY calculated_at ASC",
            [':id' => $row['id']]
        );
        $row['score_history'] = array_map(function ($h) {
            return [
                'date'      => $h['date'],
                'composite' => (float) $h['composite'],
                'academic'  => (float) $h['academic'],
                'social'    => (float) $h['social'],
                'economic'  => (float) $h['economic'],
                'sdg'       => (float) $h['sdg'],
            ];
        }, $history);

        // Recent publications
        $pubs = $this->query(
            "SELECT p.id, p.doi, p.title, p.journal_title, p.publication_year,
                    p.cited_by_count, p.wizdam_score, p.sdgs_goals
             FROM publications p
             JOIN researcher_publications rp ON rp.publication_id = p.id
             WHERE rp.researcher_id = :id
             ORDER BY p.publication_year DESC, p.cited_by_count DESC
             LIMIT 10",
            [':id' => $row['id']]
        );
        $row['recent_publications'] = array_map(function ($p) {
            $p['cited_by_count'] = (int) $p['cited_by_count'];
            $p['wizdam_score']   = (float) $p['wizdam_score'];
            $p['sdgs_goals']     = $this->decodeJson($p['sdgs_goals'] ?? '[]');
            return $p;
        }, $pubs);

        // Average pillars from all publications
        $avg = $this->queryOne(
            "SELECT AVG(pillar_academic) AS avg_academic,
                    AVG(pillar_social) AS avg_social,
                    AVG(pillar_economic) AS avg_economic,
                    AVG(pillar_sdg) AS avg_sdg,
                    AVG(composite_score) AS avg_composite
             FROM impact_scores
             WHERE entity_type = 'researcher' AND entity_id = :id",
            [':id' => $row['id']]
        );
        $row['avg_pillars'] = $avg ? array_map('floatval', $avg) : null;

        return $row;
    }

    public function fieldDistribution(): array
    {
        return $this->query(
            "SELECT
                JSON_UNQUOTE(jt.field) AS field,
                COUNT(DISTINCT r.id) AS researcher_count,
                ROUND(AVG(r.wizdam_score), 1) AS avg_score
             FROM researchers r,
                  JSON_TABLE(r.field_of_study, '$[*]' COLUMNS (field VARCHAR(200) PATH '$')) AS jt
             GROUP BY jt.field
             ORDER BY researcher_count DESC
             LIMIT 20"
        );
    }

    public function provinceDistribution(): array
    {
        return $this->query(
            "SELECT province,
                    COUNT(*) AS researcher_count,
                    ROUND(AVG(wizdam_score), 1) AS avg_impact
             FROM researchers
             WHERE province IS NOT NULL AND province != ''
             GROUP BY province
             ORDER BY researcher_count DESC"
        );
    }
}
