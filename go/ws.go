package main

import (
	"github.com/gorilla/websocket"
	"log"
	"net/http"
)

var upgrader = websocket.Upgrader{
	ReadBufferSize:  1024,
	WriteBufferSize: 1024,
}

func main() {
	http.HandleFunc("/ws", func(w http.ResponseWriter, r *http.Request) {
		upgrader.CheckOrigin = func(r *http.Request) bool { return true }
		conn, err := upgrader.Upgrade(w, r, nil)
		if err != nil {
			log.Println("Cannot create ws:", err)
			return
		}

		for {
			msgType, msg, err := conn.ReadMessage()
			if err != nil {
				log.Println("Cannot read message:", err)
				return
			}

			log.Printf("%s sent: %s\n", conn.RemoteAddr(), string(msg))

			if err = conn.WriteMessage(msgType, msg); err != nil {
				log.Println("Cannot write message:", err)
				return
			}
		}
	})

	_ = http.ListenAndServe(":8080", nil)
}
