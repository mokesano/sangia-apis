<?php
declare(strict_types=1);

namespace Sangia\Api\Controllers;

class BaseController
{
    protected function paginationParams(): array
    {
        return [
            'page'     => max(1, (int) ($_GET['page'] ?? 1)),
            'per_page' => min(100, max(1, (int) ($_GET['per_page'] ?? 20))),
        ];
    }

    protected function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) return [];
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    protected function paginationMeta(int $total, int $page, int $perPage): array
    {
        return [
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total,
            'pages'    => (int) ceil($total / max(1, $perPage)),
        ];
    }
}
