package internal

import (
	"errors"
	mq "github.com/streadway/amqp"
)

type Rabbit struct {
	address string
	connection *mq.Connection
}

func NewRabbit(address string) *Rabbit {
	return &Rabbit{
		address: address,
	}
}

func (r *Rabbit) Connect() error {
	if r.connection == nil || r.connection.IsClosed() {
		con, err := mq.Dial(r.address)
		if err != nil {
			return err
		}
		r.connection = con
	}

	return nil
}

func (r *Rabbit) Connection() (*mq.Connection, error) {
	if r.connection == nil || r.connection.IsClosed() {
		return nil, errors.New("connection is not open")
	}

	return r.connection, nil
}

func (r *Rabbit) Channel() (*mq.Channel, error) {
	chn, err := r.connection.Channel()
	if err != nil {
		return nil, err
	}

	return chn, nil
}
