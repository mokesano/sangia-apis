<?php
declare(strict_types=1);

namespace Sangia\Api\Controllers;

use Sangia\Api\Models\Researcher;
use Sangia\Api\Response;

class ResearchersController extends BaseController
{
    public function index(): void
    {
        ['page' => $page, 'per_page' => $perPage] = $this->paginationParams();

        $filters = [
            'field'    => $_GET['field'] ?? 'all',
            'province' => $_GET['province'] ?? '',
            'q'        => $_GET['q'] ?? '',
        ];

        $model  = new Researcher();
        $result = $model->list($filters, $page, $perPage);

        Response::success(
            $result['data'],
            $this->paginationMeta($result['total'], $page, $perPage)
        );
    }

    public function top(): void
    {
        $limit = min(50, max(1, (int) ($_GET['limit'] ?? 10)));
        $model = new Researcher();
        Response::success($model->top($limit));
    }

    public function show(string $orcidId): void
    {
        $model      = new Researcher();
        $researcher = $model->findByOrcid($orcidId);

        if (!$researcher) {
            Response::notFound("Researcher with ORCID '$orcidId' not found");
        }

        Response::success($researcher);
    }
}
