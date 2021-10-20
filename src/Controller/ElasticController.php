<?php

namespace App\Controller;

use App\Entity\Contract;
use App\Entity\Tariff;
use App\Services\BGBilling;
use Elastica\Client;
use Elastica\Document;
use Elastica\Query;
use Elastica\Query\BoolQuery;
use Elastica\Query\MatchQuery;
use Elastica\Query\Wildcard;
use Elastica\Request;
use Elastica\Search;
use Elastica\Type\Mapping;
use FOS\ElasticaBundle\Finder\TransformedFinder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request as HttpRequest;

/**
 * @Route("/elastic", name="elastic_")
 */
class ElasticController extends AbstractController
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
     * @Route("/billing/items/store", name="billing_items_store")
     *
     * @param BGBilling $billing
     *
     * @return Response
     */
    public function store(BGBilling $billing): Response
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
     * @Route("/search/{title}/{address}", name="search")
     *
     * @param string $title
     * @param string $address
     *
     * @return Response
     */
    public function search(string $title, string $address): Response
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

    /**
     * @Route("/ruflin1/{name}/{address}", name="ruflin1")
     *
     * @param string $name
     * @param string $address
     *
     * @return Response
     */
    public function ruflin1(string $name, string $address): Response
    {
        $client = new Client(['url' => $this->getParameter('elastica_url')]);
        $index = $client->getIndex('contracts');
        $type = $index->getType('contract');
        $query = [
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'match' => [
                                'fullName' => $name,
                            ],
                        ],
                        [
                            'match' => [
                                'address' => $address,
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $path = $index->getName() . '/' . $type->getName() . '/_search';
        $response = $client->request($path, Request::GET, $query);

        return $this->json($response->getData());
    }

    /**
     * @Route("/ruflin2/{name}/{address}", name="ruflin2")
     *
     * @param string $name
     * @param string $address
     *
     * @return Response
     */
    public function ruflin2(string $name, string $address): Response
    {
        $client = new Client(['url' => $this->getParameter('elastica_url')]);

        $matchQuery1 = new MatchQuery();
        $matchQuery1->setField('fullName', $name);
        $matchQuery2 = new MatchQuery();
        $matchQuery2->setField('address', $address);
        $boolQuery = new BoolQuery();
        $boolQuery->addMust([$matchQuery1, $matchQuery2]);
        $query = new Query($boolQuery);

        $contracts = [];
        $search = new Search($client);
        $result = $search->addIndex('contracts')->search($query, 10);
        foreach ($result as $item) {
            $contracts[] = $item->getData();
        }

        return $this->json($contracts);
    }

    /**
     * @Route("/ruflin3", name="ruflin3")
     *
     * @param HttpRequest $request
     *
     * @return Response
     */
    public function ruflin3(HttpRequest $request): Response
    {
        $client = new Client(['url' => $this->getParameter('elastica_url')]);

        $index = $client->getIndex('test_index');
        if (!$index->exists()) {
            $index->create();
        } elseif ($request->query->has('reload')) {
            $index->delete();
            $index->create();
        }

        $type = $index->getType('test_type');
        if (!$type->exists()) {
            $mapping = new Mapping();
            $mapping->setType($type);
            $mapping->setProperties([
                'id' => ['type' => 'integer'],
                'user' => [
                    'type' => 'object',
                    'properties' => [
                        'name'      => ['type' => 'text'],
                        'fullName'  => ['type' => 'text', 'boost' => 2],
                    ],
                ],
                'msg' => ['type' => 'text', 'analyzer' => 'russian'],
            ]);
            $mapping->send();

            for ($i = 1; $i < 15; ++$i) {
                $id = time();
                $item = [
                    'id' => $id,
                    'user' => [
                        'name' => "user_$id",
                        'fullName' => "fullName_$i"
                    ],
                    'msg' => 'Привет, умный поиск!'
                ];
                $document = new Document($id, $item);
                $type->addDocument($document);
                sleep(1);
            }

            $type->getIndex()->refresh();
        }

        $wildcardQuery = new Wildcard();
        $wildcardQuery->setValue('user.name', 'user_*');
        $matchQuery = new MatchQuery();
        $matchQuery->setField('msg', 'умнейшие');
        $boolQuery = new BoolQuery();
        $boolQuery->addMust([$wildcardQuery, $matchQuery]);
        $query = new Query($boolQuery);

        $items = [];
        $search = new Search($client);
        $result = $search->addIndex('test_index')->search($query, 10);
        foreach ($result as $item) {
            $items[] = $item->getData();
        }

        return $this->json($items);
    }
}
