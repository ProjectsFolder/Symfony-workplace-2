package main

import (
    "encoding/json"
    "errors"
    "fmt"
    uuid "github.com/nu7hatch/gouuid"
    mq "github.com/streadway/amqp"
    "io/ioutil"
    "log"
    "net/http"
    "os"
    "strings"
)

type Configuration struct {
    RabbitUrl string
    CallbackUrl string
}

func main() {
    file, _ := os.Open("config.json")
    decoder := json.NewDecoder(file)
    configuration := Configuration{}
    if err := decoder.Decode(&configuration); err != nil {
        fmt.Println("Cannot read configuration:", err)
    }
    if err := file.Close(); err != nil {
        log.Println("Unable to close file", err)
    }

    rabbit := NewRabbit(configuration.RabbitUrl)
    if err := rabbit.Connect(); err != nil {
        log.Fatalln("unable to connect to rabbit", err)
    }

    u, err := uuid.NewV4()
    qName := u.String()
    consumerConfig := ConsumerConfig {
        ExchangeName: "test_topic.v1",
        ExchangeType: "fanout",
        RoutingKey:   "",
        QueueName:    qName,
        CallbackUrl:  configuration.CallbackUrl,
    }
    consumer := NewConsumer(consumerConfig, rabbit)
    messages, err := consumer.Start();
    if err != nil {
        log.Fatalln("Unable to start consumer", err)
    }

    log.Println("[", qName, "] Running ...")
    log.Println("[", qName, "] Press CTRL+C to exit ...")

    for msg := range messages {
        message := string(msg.Body)
        reader := strings.NewReader(message)
        log.Println("[", qName, "] Consumed:", message)

        req, err := http.NewRequest("POST", "http://127.0.0.1:8003/rabbit/callback", reader)
        if err != nil {
            log.Println("Unable to create request", err)
        }
        req.Header.Set("Accept", "application/json")
        resp, err := http.DefaultClient.Do(req)
        if err != nil {
            log.Println("Unable to call callback", err)
        }

        bytes, err := ioutil.ReadAll(resp.Body)
        if err != nil {
            log.Println("Unable to read response", err)
        }
        log.Println("[", qName, "] Callback-response:", string(bytes))
        if err := resp.Body.Close(); err != nil {
            log.Println("Unable to close response", err)
        }

        if err := msg.Ack(false); err != nil {
            log.Println("Unable to acknowledge the message, dropped", err)
        }
    }

    log.Println("[", qName, "] Exiting ...")
}

// RABBIT

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

// CONSUMER

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
