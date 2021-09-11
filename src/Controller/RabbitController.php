<?php

namespace App\Controller;

use Enqueue\AmqpLib\AmqpConnectionFactory;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use Interop\Queue\Exception\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/rabbit", name="rabbit_")
 */
class RabbitController extends AbstractController
{
    /**
     * @Route("/set", name="_set")
     *
     * @return Response
     *
     * @throws Exception
     */
    public function setMessage(): Response
    {
        $factory = new AmqpConnectionFactory('amqp://guest:secret@localhost:5672');
        $context = $factory->createContext();

        $queue = $context->createQueue('test_queue.v1');
        $queue->addFlag(AmqpQueue::FLAG_DURABLE);
        $context->declareQueue($queue);

        $endpoint = $context->createTopic('test_topic.v1');
        $endpoint->setType(AmqpTopic::TYPE_FANOUT);
        $context->declareTopic($endpoint);

        $context->bind(new AmqpBind($endpoint, $queue));
        $producer = $context->createProducer();

        $producer->send($endpoint, $context->createMessage('this is test message'));
        $producer->send($queue, $context->createMessage('this is test message 1'));

        return $this->json('success');
    }

    /**
     * @Route("/get", name="_get")
     *
     * @return Response
     */
    public function getMessage(): Response
    {
        $factory = new AmqpConnectionFactory('amqp://guest:secret@localhost:5672');
        $context = $factory->createContext();

        $queue = $context->createQueue('test_queue.v1');
        $queue->addFlag(AmqpQueue::FLAG_DURABLE);
        $context->declareQueue($queue);

        $consumer = $context->createConsumer($queue);
        $message = $consumer->receive(1000);
        if (!empty($message)) {
            $consumer->acknowledge($message);
        }

        return $this->json(!empty($message) ? $message->getBody() : false);
    }
}
