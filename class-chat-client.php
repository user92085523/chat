<?php
class ChatClient{
    const ADDRESS = '127.0.0.1';
    const PORT = 8080;
    const BYTE_SIZE = 1024;
    private Socket $socket;
    private array $cp_socket = [];
    private bool $kill = false;
    
    function __construct()
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (! socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1)) {
            exit();
        }
        if (! stream_set_blocking(STDIN, false)) {
            exit();
        }
    }

    function start()
    {
        if (! $this->connectToServer()) {
            return;
        }
        while ($this->kill === false) {
            $this->update();
            usleep(5000);
        }
    }

    private function connectToServer()
    {
        if (! socket_connect($this->socket, self::ADDRESS,self::PORT)) {
            echo "サーバーとの接続に失敗しました\n";
            return false;
        }
        echo "サーバーとの接続に成功しました\n";
        return true;
    }

    private function update()
    {
        $this->chatUpdate();
    }

    private function chatUpdate()
    {
        $this->recvMsg();
        $input = fgets(STDIN);
        if ($input) {
            $this->sendMsg(substr($input, 0, -1));
        }
    }

    private function recvMsg()
    {
        $this->setCpSocket();
        $null = null;
        if (! socket_select($this->cp_socket, $null, $null, 0)) {
            return;
        }
        $recv_msg = null;
        socket_recv($this->socket, $recv_msg, self::BYTE_SIZE, 0);
        echo "$recv_msg\n";
        $this->clearCpSocket();
    }

    private function sendMsg(string $msg)
    {
        socket_send($this->socket, $msg, self::BYTE_SIZE, 0);
    }
    private function setCpSocket()
    {
        $this->cp_socket[] = $this->socket;
    }

    private function clearCpSocket()
    {
        $this->cp_socket = [];
    }
}