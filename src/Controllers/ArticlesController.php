<?php
declare(strict_types=1);

namespace Sangia\Api\Controllers;

use Sangia\Api\Models\Publication;
use Sangia\Api\Response;

class ArticlesController extends BaseController
{
    public function index(): void
    {
        ['page' => $page, 'per_page' => $perPage] = $this->paginationParams();

        $filters = [
            'q'    => $_GET['q'] ?? '',
            'year' => $_GET['year'] ?? '',
            'type' => $_GET['type'] ?? 'all',
        ];

        $model  = new Publication();
        $result = $model->list($filters, $page, $perPage);

        Response::success(
            $result['data'],
            $this->paginationMeta($result['total'], $page, $perPage)
        );
    }

    public function top(): void
    {
        $limit = min(50, max(1, (int) ($_GET['limit'] ?? 10)));
        $model = new Publication();
        Response::success($model->top($limit));
    }

    public function trends(): void
    {
        $from  = max(2000, (int) ($_GET['from'] ?? 2019));
        $to    = min((int) date('Y'), (int) ($_GET['to'] ?? (int) date('Y')));
        $model = new Publication();
        Response::success($model->trends($from, $to));
    }

    public function show(string $id): void
    {
        $model   = new Publication();
        $article = $model->find((int) $id);

        if (!$article) {
            Response::notFound("Article with id '$id' not found");
        }

        Response::success($article);
    }
}
