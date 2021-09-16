package main

import (
    "net/http"
    "rabbit-sync/internal"
)

func main() {
    ws := internal.NewWebSocket("/ws", true)
    ws.CreateHandler()
    ws.SetReceiver(func(ws *internal.WebSocket, msgType int, msg []byte) {
        ws.NotifyAll(msgType, msg)
    })

    _ = http.ListenAndServe(":8080", nil)
}
