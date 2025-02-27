<?php
class ChatClient{
    const ADDRESS = '127.0.0.1';
    const PORT = 8080;
    const BYTE_LEN = 1024;
    private Socket $sock;
    function __construct()
    {
        $this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (! socket_connect($this->sock, self::ADDRESS, self::PORT)) {
            exit();
        }
        if (! socket_set_nonblock($this->sock)) {
            exit();
        }
        // if (! stream_set_blocking(STDIN, false)) {
        //     exit();
        // }
    }
    function start()
    {
        $n = 0;
        while ($n != 100) {
            $input = fgets(STDIN);
            socket_send($this->sock, $input, self::BYTE_LEN, 0);
            $recv_msg = null;
            $null = null;
            socket_recv($this->sock, $recv_msg, self::BYTE_LEN, 0);
            echo "$recv_msg\n";
        }
    }
}