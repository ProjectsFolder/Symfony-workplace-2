package main

import (
    "net/http"
    "rabbit-sync/internal"
    "regexp"
)

func main() {
    config := internal.GetConfig()
    regex, _ := regexp.Compile(`^ws://(.*:\d*)(/.*)`)
    matches := regex.FindStringSubmatch(config.WebsocketUrl)
    ws := internal.NewWebSocket(matches[2], true)
    ws.CreateHandler()
    ws.SetReceiver(func(ws *internal.WebSocket, msgType int, msg []byte) {
        ws.NotifyAll(msgType, msg)
    })

    _ = http.ListenAndServe(matches[1], nil)
}
