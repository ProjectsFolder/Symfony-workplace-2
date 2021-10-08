package main

import (
    uuid "github.com/nu7hatch/gouuid"
    "io/ioutil"
    "log"
    "net/http"
    "rabbit-sync/internal"
    "strings"
)

func main() {
    configuration := internal.GetConfig()

    rabbit := internal.NewRabbit(configuration.RabbitUrl)
    if err := rabbit.Connect(); err != nil {
        log.Fatalln("unable to connect to rabbit", err)
    }

    u, err := uuid.NewV4()
    qName := u.String()
    consumerConfig := internal.ConsumerConfig {
        ExchangeName: "test_topic.v1",
        ExchangeType: "topic",
        RoutingKey:   configuration.RabbitRoutingKey,
        QueueName:    qName,
    }
    consumer := internal.NewConsumer(consumerConfig, rabbit)
    messages, err := consumer.Start()
    if err != nil {
        log.Fatalln("Unable to start consumer", err)
    }

    log.Println("[", qName, "] Running ...")
    log.Println("[", qName, "] Press CTRL+C to exit ...")

    ws := internal.NewWebSocket("/ws", true)
    ws.CreateHandler()
    go http.ListenAndServe(":8080", nil)

    for msg := range messages {
        message := string(msg.Body)
        reader := strings.NewReader(message)
        log.Println("[", qName, "] Consumed:", message)

        ws.NotifyAll(1, []byte(message))

        req, err := http.NewRequest("POST", configuration.CallbackUrl, reader)
        if err != nil {
            log.Println("Unable to create request", err)
            continue
        }
        req.Header.Set("Accept", "application/json")
        resp, err := http.DefaultClient.Do(req)
        if err != nil {
            log.Println("Unable to call callback", err)
            continue
        }

        bytes, err := ioutil.ReadAll(resp.Body)
        if err != nil {
            log.Println("Unable to read response", err)
            continue
        }
        log.Println("[", qName, "] Callback-response:", string(bytes))
        if err := resp.Body.Close(); err != nil {
            log.Println("Unable to close response", err)
            continue
        }

        if err := msg.Ack(false); err != nil {
            log.Println("Unable to acknowledge the message, dropped", err)
            continue
        }
    }

    log.Println("[", qName, "] Exiting ...")
}
