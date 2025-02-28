<?php
class ChatServer
{
    const ADDRESS = '127.0.0.1';
    const PORT = 8080;
    const BYTE_SIZE = 1024;
    const WELCOME_MSG = "WELCOME enter your name:";
    private Socket $master_socket;
    private array $clients = [];
    private array $sockets = [];
    private bool $kill = false;
    function __construct()
    {
        $this->master_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (! socket_bind($this->master_socket, self::ADDRESS, self::PORT)) {
            exit;
        }
        if (! socket_listen($this->master_socket, 10)) {
            exit;
        }
        if (! socket_set_option($this->master_socket, SOL_SOCKET, SO_REUSEADDR, 1)) {
            exit;
        }
        if (! socket_set_nonblock($this->master_socket)) {
            exit;
        }
    }

    function start()
    {
        while ($this->kill === false) {
            $this->update();
        }
    }

    private function update()
    {
        $this->allowNewConnection();
        if (! $this->clients) {
            return;
        }
        $this->setSockets();
        if ($this->isNewMsg()) {
            $this->chatUpdate();
        }

        #ソケットの破棄、変数の初期化など　未実装
        $this->sockets = [];
        #sleep(1);
    }

    private function allowNewConnection()
    {
        if ($new_socket = socket_accept($this->master_socket)) {
            echo "created new connection\n";
            $welcome_msg = "WELCOME ENTER YOUR NAME:";
            $client['socket'] = $new_socket;
            $client['name'] = null;
            socket_send($client['socket'], $welcome_msg, self::BYTE_SIZE, 0);
            $this->clients[] = $client;
        }
    }

    private function setSockets()
    {
        foreach($this->clients as $client) {
            $this->sockets[] = $client['socket'];
        }
    }

    private function isNewMsg()
    {
        $null = null;
        $readable_socket_cnt = socket_select($this->sockets, $null, $null, 0);
        return $readable_socket_cnt;
    }

    private function chatUpdate()
    {
        $readable_sockets_idx = array_keys($this->sockets);
        foreach($readable_sockets_idx as $idx) {
            $recv_msg = null;
            socket_recv($this->clients[$idx]['socket'], $recv_msg, self::BYTE_SIZE, 0);
            if (! $this->doesClientHaveName($this->clients[$idx])) {
                $this->setClientName($idx, $recv_msg);
                continue;
            }
            #echo "name is set\n";
            $this->sendMsg($idx, $recv_msg);
        }
    }

    private function doesClientHaveName($client): bool
    {
        if ($client['name'] === null) {
            return false;
        }
        return true;
    }

    private function setClientName(int $idx, ?string $recv_msg)
    {
        $pos = stripos($recv_msg, ":");
        if ($pos) {
            $name = substr($recv_msg, $pos + 1);
            $this->clients[$idx]['name'] = $name;
        } else {
            $this->clients[$idx]['name'] = "SET_NAME_FAILED";
        }
        echo "{$this->clients[$idx]['name']} has joined\n";
    }

    private function sendMsg(int $idx, ?string $recv_msg)
    {
        #sys/kill未実装socket閉じろ
        foreach($this->clients as $client) {
            if ($client['socket'] === $this->clients[$idx]['socket']) {
                continue;
            }
            $sender_name = $this->clients[$idx]['name'];
            socket_send($client['socket'], $sender_name . ": " . $recv_msg, self::BYTE_SIZE, 0);
        }
        echo "$recv_msg\n";
    }
}