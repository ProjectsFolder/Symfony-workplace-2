<?php

namespace App\Controller;

use App\Annotations\TokenAuthenticated;
use Grpc\ChannelCredentials;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Testservice\Request;
use Testservice\TestServiceClient;

/**
 * @Route("/grpc", name="grpc_")
 * @TokenAuthenticated
 */
class GrpcController extends AbstractController
{
    /**
     * @Route("/{name}/{beautiful?0}", name="index", methods={"GET"})
     *
     * @param string $name
     * @param int $beautiful
     *
     * @return Response
     */
    public function index(string $name, int $beautiful): Response
    {
        $client = new TestServiceClient($this->getParameter('grpc_host'), [
            'credentials' => ChannelCredentials::createInsecure(),
        ]);
        $request = new Request();
        $request->setName($name);
        $request->setBeautiful((bool) $beautiful);
        $response = $client->Do($request);
        sleep(5);
        list($reply, $status) = $response->wait();

        return $this->json(0 == $status->code ? $reply->getMessage() : $status->details ?? 'false');
    }
}
