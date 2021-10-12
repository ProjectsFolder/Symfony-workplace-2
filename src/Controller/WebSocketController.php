<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/ws", name="ws_")
 */
class WebSocketController extends AbstractController
{
    /**
     * @Route("/index", name="index", methods={"GET"})
     *
     * @param Request $request
     *
     * @return Response
     */
    public function index(Request $request): Response
    {
        return $this->render(
            'ws/index.html.twig',
            ['cookie' => $request->cookies->get('PHPSESSID')]
        );
    }

    /**
     * @Route("/check/auth", name="check_auth", methods={"GET"})
     *
     * @return Response
     */
    public function checkAuth(): Response
    {
        return new Response();
    }
}
