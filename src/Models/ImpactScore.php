<?php
declare(strict_types=1);

namespace Sangia\Api\Models;

class ImpactScore extends BaseModel
{
    private const WEIGHTS = ['academic' => 40, 'social' => 25, 'economic' => 20, 'sdg' => 15];

    private const SDG_LABELS = [
        1  => 'Tanpa Kemiskinan',      2  => 'Tanpa Kelaparan',
        3  => 'Kehidupan Sehat',       4  => 'Pendidikan Berkualitas',
        5  => 'Kesetaraan Gender',     6  => 'Air Bersih & Sanitasi',
        7  => 'Energi Bersih',        8  => 'Pekerjaan Layak',
        9  => 'Industri & Inovasi',   10 => 'Berkurangnya Kesenjangan',
        11 => 'Kota Berkelanjutan',   12 => 'Konsumsi Bertanggung Jawab',
        13 => 'Penanganan Iklim',     14 => 'Ekosistem Laut',
        15 => 'Ekosistem Darat',      16 => 'Perdamaian & Keadilan',
        17 => 'Kemitraan Global',
    ];

    public function latest(string $type, int $id): ?array
    {
        $row = $this->queryOne(
            "SELECT * FROM impact_scores
             WHERE entity_type = :type AND entity_id = :id
             ORDER BY calculated_at DESC LIMIT 1",
            [':type' => $type, ':id' => $id]
        );

        if (!$row) return null;

        return $this->format($row, $type, $id);
    }

    public function history(string $type, int $id, int $months = 12): array
    {
        $rows = $this->query(
            "SELECT * FROM impact_scores
             WHERE entity_type = :type AND entity_id = :id
               AND calculated_at >= DATE_SUB(NOW(), INTERVAL :months MONTH)
             ORDER BY calculated_at ASC",
            [':type' => $type, ':id' => $id, ':months' => $months]
        );

        return array_map(fn($r) => $this->format($r, $type, $id), $rows);
    }

    public function averages(string $type): array
    {
        $row = $this->queryOne(
            "SELECT entity_type,
                    ROUND(AVG(pillar_academic), 1) AS avg_academic,
                    ROUND(AVG(pillar_social), 1)   AS avg_social,
                    ROUND(AVG(pillar_economic), 1) AS avg_economic,
                    ROUND(AVG(pillar_sdg), 1)      AS avg_sdg,
                    ROUND(AVG(composite_score), 1) AS avg_composite,
                    COUNT(*) AS sample_count
             FROM impact_scores
             WHERE entity_type = :type
             GROUP BY entity_type",
            [':type' => $type]
        );

        if (!$row) {
            return [
                'entity_type'   => $type,
                'avg_academic'  => 0,
                'avg_social'    => 0,
                'avg_economic'  => 0,
                'avg_sdg'       => 0,
                'avg_composite' => 0,
                'sample_count'  => 0,
                'weights'       => self::WEIGHTS,
            ];
        }

        $row['avg_academic']  = (float) $row['avg_academic'];
        $row['avg_social']    = (float) $row['avg_social'];
        $row['avg_economic']  = (float) $row['avg_economic'];
        $row['avg_sdg']       = (float) $row['avg_sdg'];
        $row['avg_composite'] = (float) $row['avg_composite'];
        $row['sample_count']  = (int) $row['sample_count'];
        $row['weights']       = self::WEIGHTS;

        return $row;
    }

    public function upsert(string $type, int $id, array $pillars, array $sdgTags = []): array
    {
        $composite = round(
            ($pillars['academic'] * 0.40) +
            ($pillars['social']   * 0.25) +
            ($pillars['economic'] * 0.20) +
            ($pillars['sdg']      * 0.15),
            2
        );

        $this->execute(
            "INSERT INTO impact_scores
                (entity_type, entity_id, composite_score, pillar_academic, pillar_social,
                 pillar_economic, pillar_sdg, sdg_tags, calculated_at)
             VALUES (:type, :id, :composite, :academic, :social, :economic, :sdg, :tags, NOW())",
            [
                ':type'      => $type,
                ':id'        => $id,
                ':composite' => $composite,
                ':academic'  => $pillars['academic'],
                ':social'    => $pillars['social'],
                ':economic'  => $pillars['economic'],
                ':sdg'       => $pillars['sdg'],
                ':tags'      => json_encode($sdgTags),
            ]
        );

        // Update denormalised score on entity table
        if ($type === 'researcher') {
            $this->execute(
                "UPDATE researchers SET wizdam_score = :score WHERE id = :id",
                [':score' => $composite, ':id' => $id]
            );
            $this->recalculatePercentiles();
        } elseif ($type === 'article') {
            $this->execute(
                "UPDATE publications SET wizdam_score = :score WHERE id = :id",
                [':score' => $composite, ':id' => $id]
            );
        } elseif ($type === 'institution') {
            $this->execute(
                "UPDATE institutions SET wizdam_score = :score WHERE id = :id",
                [':score' => $composite, ':id' => $id]
            );
        }

        return $this->latest($type, $id);
    }

    private function recalculatePercentiles(): void
    {
        $this->execute(
            "UPDATE researchers r
             JOIN (
                 SELECT id,
                        ROUND(PERCENT_RANK() OVER (ORDER BY wizdam_score) * 100, 1) AS pct
                 FROM researchers
             ) ranked ON ranked.id = r.id
             SET r.wizdam_percentile = ranked.pct"
        );
    }

    private function format(array $row, string $type, int $id): array
    {
        $sdgTags = json_decode($row['sdg_tags'] ?? '[]', true) ?? [];

        // Ensure labels are present
        $sdgTags = array_map(function ($tag) {
            $tag['label'] = self::SDG_LABELS[(int) $tag['sdg']] ?? "SDG {$tag['sdg']}";
            $tag['score'] = (float) $tag['score'];
            return $tag;
        }, $sdgTags);

        return [
            'entity_type'    => $type,
            'entity_id'      => $id,
            'academic'       => (float) $row['pillar_academic'],
            'social'         => (float) $row['pillar_social'],
            'economic'       => (float) $row['pillar_economic'],
            'sdg'            => (float) $row['pillar_sdg'],
            'composite'      => (float) $row['composite_score'],
            'sdg_tags'       => $sdgTags,
            'calculated_at'  => $row['calculated_at'],
            'weights'        => self::WEIGHTS,
        ];
    }
}
