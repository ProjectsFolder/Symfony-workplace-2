<?php

namespace App\Security;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class KeycloakAuthenticator extends OAuth2Authenticator
{
    use TargetPathTrait;

    private $client;
    private $router;
    private $userProvider;

    public function __construct(
        ClientRegistry $clientRegistry,
        RouterInterface $router,
        KeycloakUserProvider $userProvider
    ) {
        $this->client = $clientRegistry->getClient('keycloak');
        $this->router = $router;
        $this->userProvider = $userProvider;
    }

    public function supports(Request $request): ?bool
    {
        return
            'keycloak_auth' == $request->attributes->get('_route') &&
            $request->query->has('state') &&
            $request->query->has('session_state') &&
            $request->query->has('code')
        ;
    }

    public function authenticate(Request $request): PassportInterface
    {
        $accessToken = $this->fetchAccessToken($this->client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function() use ($accessToken) {
                return $this->userProvider->loadUserByToken($accessToken);
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $targetUrl = $this->getTargetPath($request->getSession(), $firewallName);
        if (empty($targetUrl)) {
            $targetUrl = $this->router->generate('keycloak_index');
        }

        return new RedirectResponse($targetUrl);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        return new Response($message, Response::HTTP_FORBIDDEN);
    }
}
