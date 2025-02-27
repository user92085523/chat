<?php
class ChatServer
{
    const ADDRESS = '127.0.0.1';
    const PORT = 8080;
    const BYTE_LEN = 1024;
    private Socket $sock;
    private array $clients = [];
    private array $cp_clients = [];
    private array $recv_msgs = [];
    function __construct()
    {
        $this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (! socket_bind($this->sock, self::ADDRESS, self::PORT)) {
            exit;
        }
        if (! socket_listen($this->sock, 10)) {
            exit;
        }
        if (! socket_set_option($this->sock, SOL_SOCKET, SO_REUSEADDR, 1)) {
            exit;
        }
        if (! socket_set_nonblock($this->sock)) {
            exit;
        }
    }
    function start()
    {
        $stop = false;
        while ($stop == false) {
            $this->update();
        }
    }
    private function update()
    {
        $this->allowNewConnection();
        if ($this->isNoneConnection()) return;
        $d = $this->monitorSocketsRead();
        if ($d) {
            $this->chatUpdate();
        }
        $this->recv_msgs = [];
    }
    private function chatUpdate()
    {
        $this->recvMsg();
        foreach($this->recv_msgs as $m) {
            if ($m != "\x0a") {
                echo $m;
            }
        }
        foreach($this->recv_msgs as $msg) {
            if ($msg == "\x0a") {
                continue;
            }
            foreach($this->clients as $client) {
                foreach($this->cp_clients as $sender) {
                    if ($sender != $client) {
                        socket_send($client, $msg, self::BYTE_LEN, 0);
                    }
                }
            }
        }
    }
    private function allowNewConnection()
    {
        if ($client = socket_accept($this->sock)) {
            // socket_set_nonblock($client);
            $this->clients[] = $client;
            echo "NEW USER CONNECTED";
        }
    }
    private function isNoneConnection(): bool
    {
        return ! (bool)$this->clients;
    }
    private function monitorSocketsRead()
    {
        $this->cp_clients = $this->clients;
        $null = null;
        return socket_select($this->cp_clients, $null, $null, null);
    }
    private function recvMsg()
    {
        foreach($this->cp_clients as $client) {
            $recv_msg = null;
            socket_recv($client, $recv_msg, self::BYTE_LEN, 0);
            if ($recv_msg != null) {
                $this->recv_msgs[] = $recv_msg;
            }
        }
    }
}