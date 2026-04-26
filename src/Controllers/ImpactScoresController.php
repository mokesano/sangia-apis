<?php
declare(strict_types=1);

namespace Sangia\Api\Controllers;

use Sangia\Api\Models\ImpactScore;
use Sangia\Api\Response;

class ImpactScoresController extends BaseController
{
    private const VALID_TYPES = ['researcher', 'article', 'institution', 'journal'];

    public function show(string $type, string $id): void
    {
        $this->validateType($type);

        $model  = new ImpactScore();
        $score  = $model->latest($type, (int) $id);

        if (!$score) {
            Response::notFound("No impact score found for $type #$id");
        }

        Response::success($score);
    }

    public function history(string $type, string $id): void
    {
        $this->validateType($type);

        $months = min(60, max(1, (int) ($_GET['months'] ?? 12)));
        $model  = new ImpactScore();
        $rows   = $model->history($type, (int) $id, $months);

        Response::success($rows);
    }

    public function calculate(string $type, string $id): void
    {
        $this->validateType($type);

        $body = $this->jsonBody();

        // Accept custom pillars from body, or derive defaults
        $pillars = [
            'academic' => (float) ($body['academic'] ?? 0),
            'social'   => (float) ($body['social']   ?? 0),
            'economic' => (float) ($body['economic']  ?? 0),
            'sdg'      => (float) ($body['sdg']       ?? 0),
        ];

        // If no pillars provided, try to compute from entity data
        if (array_sum($pillars) === 0.0) {
            $pillars = $this->derivePillars($type, (int) $id);
        }

        $sdgTags = $body['sdg_tags'] ?? [];

        $model  = new ImpactScore();
        $result = $model->upsert($type, (int) $id, $pillars, $sdgTags);

        Response::success($result, [], 201);
    }

    public function averages(string $type): void
    {
        $this->validateType($type);

        $model = new ImpactScore();
        Response::success($model->averages($type));
    }

    private function validateType(string $type): void
    {
        if (!in_array($type, self::VALID_TYPES, true)) {
            Response::error('Invalid entity type. Allowed: ' . implode(', ', self::VALID_TYPES));
        }
    }

    private function derivePillars(string $type, int $id): array
    {
        if ($type === 'researcher') {
            $row = \Sangia\Api\Config\Database::connection()->prepare(
                "SELECT h_index, total_citations, total_publications FROM researchers WHERE id = :id"
            );
            $row->execute([':id' => $id]);
            $data = $row->fetch();
            if ($data) {
                $academic = min(100, ($data['h_index'] * 2) + min(30, $data['total_publications'] * 0.3));
                $citations = min(30, log10(max(1, $data['total_citations'])) * 10);
                return [
                    'academic' => round(min(100, $academic + $citations), 1),
                    'social'   => round(rand(20, 60) + ($data['h_index'] * 0.5), 1),
                    'economic' => round(rand(15, 45), 1),
                    'sdg'      => round(rand(20, 70), 1),
                ];
            }
        }

        return ['academic' => 50.0, 'social' => 40.0, 'economic' => 35.0, 'sdg' => 45.0];
    }
}
