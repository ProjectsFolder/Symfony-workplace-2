<?php

namespace App\Controller;

use App\Services\RabbitMQ;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/rabbit", name="rabbit_")
 */
class RabbitController extends AbstractController
{
    private $rabbit;

    public function __construct(RabbitMQ $rabbit)
    {
        $this->rabbit = $rabbit;
    }

    /**
     * @Route("/set", name="_set")
     *
     * @return Response
     */
    public function setMessage(): Response
    {
        $this->rabbit->public('test_topic.v1', ['message' => 'Hello, Rabbit!']);

        return $this->json('success');
    }

    /**
     * @Route("/get", name="_get")
     *
     * @return Response
     */
    public function getMessage(): Response
    {
        $message = $this->rabbit->receive('test_topic.v1');

        return $this->json($message);
    }
}
