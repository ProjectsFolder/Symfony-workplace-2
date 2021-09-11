<?php

namespace App\Controller;

use App\Services\Clickhouse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/clickhouse", name="clickhouse_")
 */
class ClickhouseController extends AbstractController
{
    /**
     * @Route("/databases", name="databases")
     *
     * @param Clickhouse $clickhouse
     *
     * @return Response
     */
    public function databases(Clickhouse $clickhouse): Response
    {
        return $this->json($clickhouse->showDatabases());
    }
}
