<?php

namespace App\Services;

use Enqueue\AmqpLib\AmqpConnectionFactory;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RabbitMQ
{
    private $context;

    public function __construct(ContainerInterface $container)
    {
        $factory = new AmqpConnectionFactory($container->getParameter('rabbit_mq_url'));
        $this->context = $factory->createContext();
    }

    public function public(string $exchangeName, string $routingKey, array $message)
    {
        $endpoint = $this->context->createTopic($exchangeName);
        $endpoint->setType(AmqpTopic::TYPE_DIRECT);
        $this->context->declareTopic($endpoint);
        $producer = $this->context->createProducer();
        $message = $this->context->createMessage(json_encode($message));
        $message->setRoutingKey($routingKey);
        $producer->send($endpoint, $message);
    }

    public function receive(string $exchangeName, string $routingKey): array
    {
        $queue = $this->context->createQueue(uniqid());
        $queue->setFlags(AmqpQueue::FLAG_DURABLE | AmqpQueue::FLAG_AUTODELETE);
        $this->context->declareQueue($queue);

        $endpoint = $this->context->createTopic($exchangeName);
        $endpoint->setType(AmqpTopic::TYPE_DIRECT);
        $this->context->declareTopic($endpoint);

        $this->context->bind(new AmqpBind($endpoint, $queue, $routingKey));
        $consumer = $this->context->createConsumer($queue);

        $result = [];
        $subscriptionConsumer = $this->context->createSubscriptionConsumer();
        $subscriptionConsumer->subscribe(
            $consumer,
            function(AmqpMessage $message, AmqpConsumer $consumer) use (&$result) {
                $consumer->acknowledge($message);
                $routingKey = $message->getRoutingKey();
                $result['message'] = json_decode($message->getBody(), true);
                $result['routing_key'] = $routingKey;

                return false;
            }
        );
        $subscriptionConsumer->consume();

        return $result;
    }
}
