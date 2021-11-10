<?php

namespace App\Controller;

use Grpc\ChannelCredentials;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Testservice\Request;
use Testservice\TestServiceClient;

/**
 * @Route("/grpc", name="grpc_")
 */
class GrpcController extends AbstractController
{
    /**
     * @Route("/{message}", name="index", methods={"GET"})
     *
     * @param string $message
     *
     * @return Response
     */
    public function index(string $message): Response
    {
        $client = new TestServiceClient($this->getParameter('grpc_host'), [
            'credentials' => ChannelCredentials::createInsecure(),
        ]);
        $request = new Request();
        $request->setName($message);
        list($reply, $status) = $client->Do($request)->wait();

        return $this->json(0 == $status->code ? $reply->getMessage() : false);
    }
}
