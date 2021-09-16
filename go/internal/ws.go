package internal

import (
	"github.com/gorilla/websocket"
	"log"
	"net/http"
)

type WebSocket struct {
	secure    bool
	pattern   string
	receiver  func(ws *WebSocket, msgType int, msg []byte)
	clients   map[*websocket.Conn]*WebSocketClient
	notifyAll chan *WebSocketMessage
	config    *Configuration
}

type WebSocketMessage struct {
	msgType int
	msg     []byte
}

type WebSocketClient struct {
	connection *websocket.Conn
	sender     chan *WebSocketMessage
	auth       string
}

func NewWebSocket(pattern string, secure bool) *WebSocket {
	return &WebSocket{
		secure:    secure,
		pattern:   pattern,
		clients:   make(map[*websocket.Conn]*WebSocketClient),
		notifyAll: make(chan *WebSocketMessage),
		config:    GetConfig(),
	}
}

func (ws *WebSocket) CreateHandler() {
	http.HandleFunc(ws.pattern, func(w http.ResponseWriter, r *http.Request) {
		auth := r.URL.Query().Get("auth")
		upgrader := websocket.Upgrader{
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
			sender:     make(chan *WebSocketMessage),
			auth:       auth,
		}
		ws.clients[conn] = client

		for {
			msgType, msg, err := client.connection.ReadMessage()
			if err != nil {
				log.Println("Cannot read message:", err)
				delete(ws.clients, client.connection)
				return
			}

			if ws.secure && !ws.CheckAuth(client) {
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
			message := <-ws.notifyAll
			for connection, client := range ws.clients {
				if ws.secure && !ws.CheckAuth(client) {
					delete(ws.clients, connection)
					continue
				}

				if err := connection.WriteMessage(message.msgType, message.msg); err != nil {
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
		msg:     msg,
	}
}

func (ws *WebSocket) GetClients() map[*websocket.Conn]*WebSocketClient {
	return ws.clients
}

func (ws *WebSocket) CheckAuth(client *WebSocketClient) bool {
	req, err := http.NewRequest("GET", ws.config.AuthUrl, nil)
	if err != nil {
		log.Println("Unable to create request", err)
	}

	cookie := http.Cookie{
		Name:  "PHPSESSID",
		Value: client.auth,
		Path:  "/",
	}
	req.AddCookie(&cookie)
	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		log.Println("Unable to auth ws", err)
		return false
	}
	if resp.StatusCode != 200 {
		log.Println("Unable to auth ws", resp.Status)
		return false
	}

	return true
}
