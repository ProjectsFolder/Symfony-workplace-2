<?php

namespace App\EventSubscriber;

use App\Annotations\TokenAuthenticated;
use App\Utils\SystemUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

class TokenAuthEventSubscriber implements EventSubscriberInterface
{
    private $apiToken;
    private $systemUtils;

    public function __construct(SystemUtils $systemUtils, ContainerInterface $container)
    {
        $this->systemUtils = $systemUtils;
        $this->apiToken = $container->getParameter('app_api_token');
    }

    public function onKernelController(ControllerEvent $event)
    {
        $controller = $event->getController();
        $method = null;

        if (is_array($controller)) {
            $method = $controller[1];
            $controller = $controller[0];
        }

        try {
            $annotations = $this->systemUtils->getAnnotationsByClass(get_class($controller), $method);
            $checkToken = in_array(TokenAuthenticated::class, $annotations);
        } catch (Throwable $throwable) {
            $checkToken = true;
        }

        if ($checkToken) {
            $token = $event->getRequest()->query->get('token');
            if ($token != $this->apiToken) {
                $event->setController(
                    function () {
                        return new JsonResponse('This action needs a valid token!', 401);
                    }
                );
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
}
