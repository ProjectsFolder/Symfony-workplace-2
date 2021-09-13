package internal

import (
	mq "github.com/streadway/amqp"
	"log"
)

type ConsumerConfig struct {
	ExchangeName string
	ExchangeType string
	RoutingKey   string
	QueueName    string
	CallbackUrl  string
}

type Consumer struct {
	config ConsumerConfig
	rabbit *Rabbit
}

func NewConsumer(config ConsumerConfig, rabbit *Rabbit) *Consumer {
	return &Consumer{
		config: config,
		rabbit: rabbit,
	}
}

func (c *Consumer) Start() (<-chan mq.Delivery, error) {
	con, err := c.rabbit.Connection()
	if err != nil {
		return nil, err
	}

	chn, err := con.Channel()
	if err != nil {
		return nil, err
	}

	if err := chn.ExchangeDeclare(
		c.config.ExchangeName,
		c.config.ExchangeType,
		false,
		false,
		false,
		false,
		nil,
	); err != nil {
		return nil, err
	}

	if _, err := chn.QueueDeclare(
		c.config.QueueName,
		true,
		true,
		true,
		false,
		nil,
	); err != nil {
		return nil, err
	}

	if err := chn.QueueBind(
		c.config.QueueName,
		c.config.RoutingKey,
		c.config.ExchangeName,
		false,
		nil,
	); err != nil {
		return nil, err
	}

	messages, err := chn.Consume(
		c.config.QueueName,
		"",
		false,
		false,
		false,
		false,
		nil,
	)
	if err != nil {
		log.Println("CRITICAL: Unable to start consumer")

		return nil, err
	}

	return messages, nil
}
