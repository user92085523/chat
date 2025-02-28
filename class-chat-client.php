<?php
class ChatClient{
    const ADDRESS = '127.0.0.1';
    const PORT = 8080;
    const BYTE_SIZE = 1024;
    private Socket $socket;
    private bool $is_name_set = false;
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
        while ($this->kill === false) {
            $this->update();
            sleep(1);
        }
        socket_close($this->socket);
    }

    private function update()
    {
        if (! $this->is_name_set) {
            $this->connectToServerAndSetName();
        }
        $input = null;
        $input = substr(fgets(STDIN), 0, -1);
        if (strlen($input) != 0) {
            if ($input == "sys/kill") {
                $this->kill = true;
            }
            socket_send($this->socket, $input, self::BYTE_SIZE, 0);
        }
        $this->chatUpdate();
    }

    private function connectToServerAndSetName()
    {
        if (! socket_connect($this->socket, self::ADDRESS, self::PORT)) {
            exit();
        }
        $null = null;
        $welcome_msg = null;
        socket_recv($this->socket, $welcome_msg, self::BYTE_SIZE, 0);
        echo "$welcome_msg\n";
        while (! $this->is_name_set) {
            $msg = "sys/set/name:";
            $name = substr(fgets(STDIN), 0, -1);
            if (strlen($name) != 0) {
                socket_send($this->socket, $msg .= $name, self::BYTE_SIZE, 0);
                $this->is_name_set = true;
            }
            $this->chatUpdate();
        }
    }

    private function chatUpdate()
    {
        $cp_socket[] = $this->socket;
        $null = null;
        $readable = socket_select($cp_socket, $null, $null, 0);
        if ($readable) {
            $recv_msg = null;
            socket_recv($this->socket, $recv_msg, self::BYTE_SIZE, 0);
            echo "$recv_msg\n";
        }
    }
}