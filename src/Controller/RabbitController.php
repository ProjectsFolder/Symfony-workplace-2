<?php

namespace App\Controller;

use App\Services\RabbitMQ;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
     * @Route("/{key}/set", name="set")
     *
     * @param string $key
     *
     * @return Response
     */
    public function setMessage(string $key): Response
    {
        $this->rabbit->public('test_topic.v1', $key, ['message' => 'Hello, Rabbit!']);

        return $this->json('success');
    }

    /**
     * @Route("/{key}/get", name="get")
     *
     * @param string $key
     *
     * @return Response
     */
    public function getMessage(string $key): Response
    {
        $message = $this->rabbit->receive('test_topic.v1', $key);

        return $this->json($message);
    }

    /**
     * @Route("/callback", name="callback", methods={"POST"})
     *
     * @param Request $request
     *
     * @return Response
     */
    public function callbackMessage(Request $request): Response
    {
        $content = json_decode($request->getContent(), true);
        $content['from'] = 'Symfony!';
        $data = [
            'success' => true,
            'data' => $content,
        ];

        return $this->json($data);
    }
}
