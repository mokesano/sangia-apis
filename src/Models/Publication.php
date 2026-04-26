<?php
declare(strict_types=1);

namespace Sangia\Api\Models;

class Publication extends BaseModel
{
    private function normalize(array $row): array
    {
        $row['cited_by_count'] = (int) ($row['cited_by_count'] ?? 0);
        $row['wizdam_score']   = (float) ($row['wizdam_score'] ?? 0);
        $row['sdgs_goals']     = $this->decodeJson($row['sdgs_goals'] ?? '[]');
        return $row;
    }

    public function list(array $filters, int $page, int $perPage): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(p.title LIKE :q OR p.authors_list LIKE :q2 OR p.journal_title LIKE :q3)';
            $params[':q']  = '%' . $filters['q'] . '%';
            $params[':q2'] = '%' . $filters['q'] . '%';
            $params[':q3'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['year'])) {
            $where[] = 'p.publication_year = :year';
            $params[':year'] = (int) $filters['year'];
        }
        if (!empty($filters['type']) && $filters['type'] !== 'all') {
            $where[] = 'p.document_type = :type';
            $params[':type'] = $filters['type'];
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $total = $this->count(
            "SELECT COUNT(*) FROM publications p WHERE $whereClause",
            $params
        );

        $rows = $this->query(
            "SELECT p.id, p.doi, p.title, p.authors_list, p.journal_title,
                    p.publication_year, p.cited_by_count, p.wizdam_score,
                    p.sdgs_goals, p.document_type, p.access_type
             FROM publications p
             WHERE $whereClause
             ORDER BY p.wizdam_score DESC, p.cited_by_count DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        return [
            'data'  => array_map([$this, 'normalize'], $rows),
            'total' => $total,
        ];
    }

    public function top(int $limit): array
    {
        $rows = $this->query(
            "SELECT id, doi, title, authors_list, journal_title, publication_year,
                    cited_by_count, wizdam_score, sdgs_goals, document_type, access_type
             FROM publications
             ORDER BY wizdam_score DESC, cited_by_count DESC
             LIMIT $limit",
            []
        );

        return array_map([$this, 'normalize'], $rows);
    }

    public function find(int $id): ?array
    {
        $row = $this->queryOne(
            "SELECT * FROM publications WHERE id = :id",
            [':id' => $id]
        );

        if (!$row) return null;

        $row = $this->normalize($row);
        $row['abstract'] = $row['abstract'] ?? null;

        // Authors with researcher links
        $authors = $this->query(
            "SELECT r.id, r.orcid_id, r.full_name
             FROM researchers r
             JOIN researcher_publications rp ON rp.researcher_id = r.id
             WHERE rp.publication_id = :id",
            [':id' => $id]
        );
        $row['linked_researchers'] = $authors;

        return $row;
    }

    public function trends(int $from, int $to): array
    {
        $rows = $this->query(
            "SELECT publication_year AS year,
                    COUNT(*) AS total_publications,
                    ROUND(AVG(wizdam_score), 1) AS avg_wizdam_score,
                    SUM(cited_by_count) AS total_citations
             FROM publications
             WHERE publication_year BETWEEN :from AND :to
             GROUP BY publication_year
             ORDER BY publication_year ASC",
            [':from' => $from, ':to' => $to]
        );

        return array_map(function ($r) {
            return [
                'year'               => (int) $r['year'],
                'total_publications' => (int) $r['total_publications'],
                'avg_wizdam_score'   => (float) $r['avg_wizdam_score'],
                'total_citations'    => (int) $r['total_citations'],
            ];
        }, $rows);
    }

    public function yearlyTrend(): array
    {
        return $this->query(
            "SELECT publication_year AS year,
                    COUNT(*) AS total_publications,
                    ROUND(AVG(wizdam_score), 1) AS avg_score,
                    SUM(cited_by_count) AS total_citations
             FROM publications
             WHERE publication_year IS NOT NULL
             GROUP BY publication_year
             ORDER BY publication_year ASC"
        );
    }
}
