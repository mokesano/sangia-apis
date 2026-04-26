<?php
declare(strict_types=1);

namespace Sangia\Api\Controllers;

use Sangia\Api\Models\Institution;
use Sangia\Api\Response;

class InstitutionsController extends BaseController
{
    public function index(): void
    {
        ['page' => $page, 'per_page' => $perPage] = $this->paginationParams();

        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 30)));

        $filters = [
            'province' => $_GET['province'] ?? '',
            'q'        => $_GET['q'] ?? '',
        ];

        $model  = new Institution();
        $result = $model->list($filters, $page, $perPage);

        Response::success(
            $result['data'],
            $this->paginationMeta($result['total'], $page, $perPage)
        );
    }

    public function map(): void
    {
        $model = new Institution();
        Response::success($model->mapData());
    }

    public function show(string $id): void
    {
        $model       = new Institution();
        $institution = $model->find((int) $id);

        if (!$institution) {
            Response::notFound("Institution with id '$id' not found");
        }

        Response::success($institution);
    }
}
