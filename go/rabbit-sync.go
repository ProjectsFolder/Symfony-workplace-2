package main

import (
    "encoding/json"
    "fmt"
    uuid "github.com/nu7hatch/gouuid"
    "io/ioutil"
    "log"
    "net/http"
    "os"
    "rabbit-sync/internal"
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

    rabbit := internal.NewRabbit(configuration.RabbitUrl)
    if err := rabbit.Connect(); err != nil {
        log.Fatalln("unable to connect to rabbit", err)
    }

    u, err := uuid.NewV4()
    qName := u.String()
    consumerConfig := internal.ConsumerConfig {
        ExchangeName: "test_topic.v1",
        ExchangeType: "fanout",
        RoutingKey:   "",
        QueueName:    qName,
    }
    consumer := internal.NewConsumer(consumerConfig, rabbit)
    messages, err := consumer.Start()
    if err != nil {
        log.Fatalln("Unable to start consumer", err)
    }

    log.Println("[", qName, "] Running ...")
    log.Println("[", qName, "] Press CTRL+C to exit ...")

    for msg := range messages {
        message := string(msg.Body)
        reader := strings.NewReader(message)
        log.Println("[", qName, "] Consumed:", message)

        req, err := http.NewRequest("POST", configuration.CallbackUrl, reader)
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
