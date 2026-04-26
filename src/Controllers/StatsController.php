<?php
declare(strict_types=1);

namespace Sangia\Api\Controllers;

use Sangia\Api\Models\Researcher;
use Sangia\Api\Models\Publication;
use Sangia\Api\Models\Institution;
use Sangia\Api\Config\Database;
use Sangia\Api\Response;

class StatsController extends BaseController
{
    public function index(): void
    {
        $db = Database::connection();

        $totals = $db->query(
            "SELECT
                (SELECT COUNT(*) FROM researchers) AS total_researchers,
                (SELECT COUNT(*) FROM publications) AS total_publications,
                (SELECT COUNT(*) FROM institutions) AS total_institutions,
                (SELECT ROUND(AVG(wizdam_score), 1) FROM researchers) AS avg_wizdam_score,
                (SELECT SUM(cited_by_count) FROM publications) AS total_citations"
        )->fetch();

        $researcherModel  = new Researcher();
        $publicationModel = new Publication();

        Response::success([
            'total_researchers'    => (int) ($totals['total_researchers'] ?? 0),
            'total_publications'   => (int) ($totals['total_publications'] ?? 0),
            'total_institutions'   => (int) ($totals['total_institutions'] ?? 0),
            'avg_wizdam_score'     => (float) ($totals['avg_wizdam_score'] ?? 0),
            'total_citations'      => (int) ($totals['total_citations'] ?? 0),
            'field_distribution'   => $researcherModel->fieldDistribution(),
            'province_distribution'=> $researcherModel->provinceDistribution(),
            'yearly_trend'         => $publicationModel->yearlyTrend(),
        ]);
    }
}
