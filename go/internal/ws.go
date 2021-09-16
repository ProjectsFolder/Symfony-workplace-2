package internal

import (
    "github.com/gorilla/websocket"
    "log"
    "net/http"
)

type WebSocket struct {
    pattern string
    receiver func(ws *WebSocket, msgType int, msg []byte)
    clients map[*websocket.Conn] *WebSocketClient
    notifyAll chan *WebSocketMessage
}

type WebSocketMessage struct {
    msgType int
    msg []byte
}

type WebSocketClient struct {
    connection *websocket.Conn
    sender chan *WebSocketMessage
}

func NewWebSocket(pattern string) *WebSocket {
    return &WebSocket{
        pattern: pattern,
        clients: make(map[*websocket.Conn] *WebSocketClient),
        notifyAll: make(chan *WebSocketMessage),
    }
}

func (ws *WebSocket) CreateHandler() {
    http.HandleFunc(ws.pattern, func(w http.ResponseWriter, r *http.Request) {
        upgrader := websocket.Upgrader {
            ReadBufferSize:  1024,
            WriteBufferSize: 1024,
        }
        upgrader.CheckOrigin = func(r *http.Request) bool { return true }
        conn, err := upgrader.Upgrade(w, r, nil)
        if err != nil {
            log.Println("Cannot create ws:", err)
            return
        }

        client := &WebSocketClient{
            connection: conn,
            sender: make(chan *WebSocketMessage),
        }
        ws.clients[conn] = client

        for {
            msgType, msg, err := conn.ReadMessage()
            if err != nil {
                log.Println("Cannot read message:", err)
                delete(ws.clients, client.connection)
                return
            }

            if ws.receiver != nil {
                ws.receiver(ws, msgType, msg)
            }
        }
    })

    go func(ws *WebSocket) {
        for {
            message := <- ws.notifyAll
            for connection := range ws.clients {
                err := connection.WriteMessage(message.msgType, message.msg)
                if err != nil {
                    log.Println("Cannot send message to ws:", err)
                    delete(ws.clients, connection)
                } else {
                    log.Println("Send message to ws:", string(message.msg))
                }
            }
        }
    }(ws)
}

func (ws *WebSocket) SetReceiver(callback func(ws *WebSocket, msgType int, msg []byte)) {
    ws.receiver = callback
}

func (ws *WebSocket) NotifyAll(msgType int, msg []byte) {
    ws.notifyAll <- &WebSocketMessage{
        msgType: msgType,
        msg: msg,
    }
}

func (ws *WebSocket) GetClients() map[*websocket.Conn]*WebSocketClient {
    return ws.clients
}
