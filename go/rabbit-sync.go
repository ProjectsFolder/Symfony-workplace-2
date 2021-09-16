package main

import (
    uuid "github.com/nu7hatch/gouuid"
    "gopkg.in/yaml.v2"
    "io/ioutil"
    "log"
    "net/http"
    "rabbit-sync/internal"
    "strings"
)

type Configuration struct {
    RabbitUrl string `yaml:"rabbit_url"`
    CallbackUrl string `yaml:"callback_url"`
}

func main() {
    file, err := ioutil.ReadFile("./config/config.yaml")
    if err != nil {
        log.Println("Cannot read configuration:", err)
    }
    configuration := Configuration{}
    if err = yaml.Unmarshal(file, &configuration); err != nil {
        log.Fatal("Cannot unmarshal config.yaml", err.Error())
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

    ws := internal.NewWebSocket("/ws")
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
