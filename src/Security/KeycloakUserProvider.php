<?php

namespace App\Security;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class KeycloakUserProvider implements UserProviderInterface
{
    private $client;

    public function __construct(ClientRegistry $clientRegistry)
    {
        $this->client = $clientRegistry->getClient('keycloak');
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return $class == InMemoryUser::class;
    }

    public function loadUserByToken(AccessToken $token): UserInterface
    {
        $user = $this->client->fetchUserFromToken($token);
        $userData = $user->toArray();

        return new InMemoryUser($userData['preferred_username'], '', $userData['roles']);
    }

    public function loadUserByIdentifier(string $identifier): ?UserInterface
    {
        return null;
    }

    public function loadUserByUsername(string $username): ?UserInterface
    {
        return $this->loadUserByIdentifier($username);
    }

    public function __call($name, $arguments)
    {
        // TODO: Implement @method UserInterface loadUserByIdentifier(string $identifier)
    }
}
