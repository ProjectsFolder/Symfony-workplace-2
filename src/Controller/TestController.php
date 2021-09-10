<?php

namespace App\Controller;

use App\Entity\Contract;
use App\Entity\Tariff;
use App\Services\BGBilling;
use App\Services\Clickhouse;
use Elastica\Query\BoolQuery;
use Elastica\Query\MatchQuery;
use FOS\ElasticaBundle\Finder\TransformedFinder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/test", name="test_")
 */
class TestController extends AbstractController
{
    /** @var TransformedFinder */
    private $contractFinder;
    /** @var TransformedFinder */
    private $tariffFinder;

    public function __construct(TransformedFinder $contractFinder, TransformedFinder $tariffFinder)
    {
        $this->contractFinder = $contractFinder;
        $this->tariffFinder = $tariffFinder;
    }

    /**
     * @Route("/", name="index")
     */
    public function index(): Response
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/TestController.php',
        ]);
    }

    /**
     * @Route("/clickhouse", name="clickhouse")
     *
     * @param Clickhouse $clickhouse
     *
     * @return Response
     */
    public function clickhouse(Clickhouse $clickhouse): Response
    {
        return $this->json($clickhouse->showDatabases());
    }

    /**
     * @Route("/billing/items/store", name="billing_items_store")
     *
     * @param BGBilling $billing
     *
     * @return Response
     */
    public function billing(BGBilling $billing): Response
    {
        $entityManager = $this->getDoctrine()->getManager();

        $contracts = $billing->execSQL('SELECT c.id, c.title, c.comment, GET_ADDRESS_NEW(c.id) AS address FROM contract c');
        foreach ($contracts as $contract) {
            $item = new Contract();
            $item->setTitle($contract['title'] ?? '');
            $item->setFullName($contract['comment'] ?? '');
            $item->setAddress($contract['address'] ?? '');
            $item->setProviderId($contract['id'] ?? 0);
            $entityManager->persist($item);
        }

        $tariffs = $billing->execSQL('SELECT t.id, t.title FROM tariff_plan t');
        foreach ($tariffs as $tariff) {
            $item = new Tariff();
            $item->setTitle($tariff['title'] ?? '');
            $item->setProviderId($tariff['id'] ?? 0);
            $entityManager->persist($item);
        }

        $entityManager->flush();

        return $this->json(true);
    }

    /**
     * @Route("/elastic/search/{title}/{address}", name="elastic_search")
     *
     * @param string $title
     * @param string $address
     *
     * @return Response
     */
    public function elasticSearch(string $title, string $address): Response
    {
        $results = [
            'contracts' => [],
            'tariffs' => [],
        ];

        $boolQuery = new BoolQuery();

        $query = new MatchQuery();
        $query->setField('fullName', $title);
        $boolQuery->addMust($query);

        $query = new MatchQuery();
        $query->setField('address', $address);
        $boolQuery->addMust($query);

        $contracts = $this->contractFinder->find($boolQuery, 20);
        /** @var Contract $contract */
        foreach ($contracts as $contract) {
            $results['contracts'][$contract->getProviderId()] = [
                'fullname' => $contract->getFullName(),
                'address' => $contract->getAddress(),
            ];
        }

        $tariffs = $this->tariffFinder->find($title, 5);
        /** @var Tariff $tariff */
        foreach ($tariffs as $tariff) {
            $results['tariffs'][$tariff->getProviderId()] = [
                'title' => $tariff->getTitle(),
            ];
        }

        return $this->json($results);
    }
}
