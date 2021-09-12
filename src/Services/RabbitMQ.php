<?php

namespace App\Services;

use Enqueue\AmqpLib\AmqpConnectionFactory;
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

    public function public(string $exchangeName, array $message)
    {
        $endpoint = $this->context->createTopic($exchangeName);
        $endpoint->setType(AmqpTopic::TYPE_FANOUT);
        $this->context->declareTopic($endpoint);
        $producer = $this->context->createProducer();
        $producer->send($endpoint, $this->context->createMessage(json_encode($message)));
    }

    public function receive(string $exchangeName)
    {
        $queue = $this->context->createQueue(uniqid());
        $queue->setFlags(AmqpQueue::FLAG_IFUNUSED | AmqpQueue::FLAG_AUTODELETE | AmqpQueue::FLAG_EXCLUSIVE);
        $this->context->declareQueue($queue);

        $endpoint = $this->context->createTopic($exchangeName);
        $endpoint->setType(AmqpTopic::TYPE_FANOUT);
        $this->context->declareTopic($endpoint);

        $this->context->bind(new AmqpBind($endpoint, $queue));
        $consumer = $this->context->createConsumer($queue);
        $message = $consumer->receive();
        if (!empty($message)) {
            $consumer->acknowledge($message);

            return json_decode($message->getBody());
        }

        return false;
    }
}
