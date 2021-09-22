<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2Client;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/keycloak", name="keycloak_")
 */
class KeycloakController extends AbstractController
{
    private $clientRegistry;

    public function __construct(ClientRegistry $clientRegistry)
    {
        $this->clientRegistry = $clientRegistry;
    }

    /**
     * @Route("/auth", name="auth", methods={"GET"})
     *
     * @return Response
     */
    public function check(): Response
    {
        /** @var OAuth2Client $client */
        $client = $this->clientRegistry->getClient('keycloak');

        return $client->redirect();
    }

    /**
     * @IsGranted("ROLE_USER")
     * @Route("/", name="index", methods={"GET"})
     *
     * @return Response
     */
    public function index(): Response
    {
        $result = ['page' => 'index'];
        $user = $this->getUser();
        if (!empty($user)) {
            $result['user'] = $user->getUserIdentifier();
            $result['roles'] = $user->getRoles();
        }

        return $this->json($result);
    }

    /**
     * @IsGranted("ROLE_ADMIN")
     * @Route("/admin", name="admin", methods={"GET"})
     *
     * @return Response
     */
    public function admin(): Response
    {
        $result = ['page' => 'admin'];
        $user = $this->getUser();
        if (!empty($user)) {
            $result['user'] = $user->getUserIdentifier();
            $result['roles'] = $user->getRoles();
        }

        return $this->json($result);
    }
}
