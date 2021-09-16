package internal

import (
    "gopkg.in/yaml.v2"
    "io/ioutil"
    "log"
    "sync"
)

type Configuration struct {
    RabbitUrl string `yaml:"rabbit_url"`
    CallbackUrl string `yaml:"callback_url"`
    AuthUrl string `yaml:"auth_url"`
}

var (
    configuration *Configuration
    once sync.Once
)

func GetConfig() *Configuration {
    once.Do(func() {
        file, err := ioutil.ReadFile("./config/config.yaml")
        if err != nil {
            log.Println("Cannot read configuration:", err)
        }
        configuration = &Configuration{}
        if err = yaml.Unmarshal(file, configuration); err != nil {
            log.Fatal("Cannot unmarshal config.yaml", err.Error())
        }
    })

    return configuration
}
