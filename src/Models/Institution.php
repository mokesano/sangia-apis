<?php
declare(strict_types=1);

namespace Sangia\Api\Models;

class Institution extends BaseModel
{
    private function normalize(array $row): array
    {
        $row['total_researchers']  = (int) ($row['total_researchers'] ?? 0);
        $row['total_publications'] = (int) ($row['total_publications'] ?? 0);
        $row['wizdam_score']       = (float) ($row['wizdam_score'] ?? 0);
        if (isset($row['latitude']))  $row['latitude']  = (float) $row['latitude'];
        if (isset($row['longitude'])) $row['longitude'] = (float) $row['longitude'];
        return $row;
    }

    public function list(array $filters, int $page, int $perPage): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['province'])) {
            $where[] = 'province = :province';
            $params[':province'] = $filters['province'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(name LIKE :q OR short_name LIKE :q2 OR city LIKE :q3)';
            $params[':q']  = '%' . $filters['q'] . '%';
            $params[':q2'] = '%' . $filters['q'] . '%';
            $params[':q3'] = '%' . $filters['q'] . '%';
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $total = $this->count(
            "SELECT COUNT(*) FROM institutions WHERE $whereClause",
            $params
        );

        $rows = $this->query(
            "SELECT id, name, short_name, type, province, city,
                    total_researchers, total_publications, wizdam_score, website, logo_url
             FROM institutions
             WHERE $whereClause
             ORDER BY wizdam_score DESC
             LIMIT :limit OFFSET :offset",
            array_merge($params, [':limit' => $perPage, ':offset' => $offset])
        );

        return [
            'data'  => array_map([$this, 'normalize'], $rows),
            'total' => $total,
        ];
    }

    public function mapData(): array
    {
        $institutions = $this->query(
            "SELECT id, name, short_name, province, city, latitude, longitude,
                    total_researchers, total_publications, wizdam_score
             FROM institutions
             WHERE latitude IS NOT NULL AND longitude IS NOT NULL
             ORDER BY total_researchers DESC"
        );

        $byProvince = $this->query(
            "SELECT province,
                    COUNT(*) AS institution_count,
                    SUM(total_researchers) AS researcher_count,
                    ROUND(AVG(wizdam_score), 1) AS avg_impact
             FROM institutions
             WHERE province IS NOT NULL AND province != ''
             GROUP BY province
             ORDER BY institution_count DESC"
        );

        return [
            'institutions' => array_map([$this, 'normalize'], $institutions),
            'by_province'  => array_map(function ($r) {
                return [
                    'province'          => $r['province'],
                    'institution_count' => (int) $r['institution_count'],
                    'researcher_count'  => (int) $r['researcher_count'],
                    'avg_impact'        => (float) $r['avg_impact'],
                ];
            }, $byProvince),
        ];
    }

    public function find(int $id): ?array
    {
        $row = $this->queryOne(
            "SELECT * FROM institutions WHERE id = :id",
            [':id' => $id]
        );

        if (!$row) return null;

        $row = $this->normalize($row);

        // Top researchers in this institution
        $researchers = $this->query(
            "SELECT id, orcid_id, full_name, field_of_study, wizdam_score, wizdam_percentile, profile_image_url
             FROM researchers
             WHERE institution_id = :id
             ORDER BY wizdam_score DESC
             LIMIT 10",
            [':id' => $id]
        );
        $row['top_researchers'] = array_map(function ($r) {
            $r['field_of_study'] = $this->decodeJson($r['field_of_study'] ?? '[]');
            $r['wizdam_score']   = (float) $r['wizdam_score'];
            $r['wizdam_percentile'] = (float) $r['wizdam_percentile'];
            return $r;
        }, $researchers);

        return $row;
    }
}
