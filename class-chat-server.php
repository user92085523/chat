<?php
class ChatServer
{
    const ADDRESS = '127.0.0.1';
    const PORT = 8080;
    const BYTE_SIZE = 1024;
    private Socket $master_socket;
    private array $clients = [];
    private array $cp_sockets = [];
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
            usleep(5000);
        }
    }

    private function update()
    {
        $this->allowNewConnection();
        if (! $this->clients) return;
        $this->chatUpdate();
    }

    private function allowNewConnection()
    {
        if (! $new_connection = socket_accept($this->master_socket)) return;
        $default_name = 'anonymous';
        $name = $this->getValidName($default_name);
        $client['socket'] = $new_connection;
        $client['name'] = $name;
        #機能拡張の際のdata追加はここで
        $this->clients[count($this->clients)] = $client;
        $msg = "$name がサーバーに接続しました";
        echo "$msg\n";
        $this->sendToAll($msg);
    }

    private function chatUpdate()
    {
        $this->setCpSockets();
        if ($this->isMsgReceived()) {
            foreach(array_keys($this->cp_sockets) as $idx) {
                #server,clientともに応答間隔を多めに取りすぎないこと(１秒など)。短時間で接続切断繰り返した際にsocket関連のphp warning出る場合あり
                #warning表示の際でも、問題のsocketは適切に処理されるのでサーバーの動作自体に影響はない
                $recv_msg = null;
                $var = socket_recv($this->clients[$idx]['socket'], $recv_msg, self::BYTE_SIZE, 0);
                $sender_name = $this->clients[$idx]['name'];
                if ($recv_msg === null) {
                    echo "$sender_name : type=\"null\" を受信しました\n";
                    $logout_msg = "$sender_name がサーバーから切断しました";
                    $this->sendToAllExcept($idx, $logout_msg);
                    socket_close($this->clients[$idx]['socket']);
                    unset($this->clients[$idx]);
                    echo "$logout_msg\n";
                    continue;
                }
                echo "$sender_name : \"$recv_msg\" を受信しました\n";
                $this->sendMsg($idx, $recv_msg);
            }
        }
        $this->clients = array_values($this->clients);
        $this->clearCpSockets();
    }

    private function sendMsg($idx, $msg)
    {
        if ($this->isSysCmd($msg)) {
            $sys_cmd = substr($msg, 4);
            $this->sysCmdHandler($idx, $sys_cmd);
        } else {
            $name = $this->clients[$idx]['name'];
            $msg = "$name :$msg";
            $this->sendToAllExcept($idx, $msg);
        }
    }

    private function isSysCmd(string $msg)
    {
        if (substr($msg, 0, 4) == "sys/") return true;
        return false;
    }

    private function sysCmdHandler($idx, $sys_cmd)
    {
        $buff = explode('=', $sys_cmd);
        if (count($buff) != 2 or strlen($buff[0]) == 0 or strlen($buff[1]) == 0 or $buff[0] == ' ' or $buff[1] == ' ') {
            $err_msg = "有効なシステムコマンドではありません";
            $this->sendToClient($idx, $err_msg);
            echo "$err_msg\n";
        } else {
            if ($buff[0] == 'name') {
                $prev_name = $this->clients[$idx]['name'];
                if (! $this->doesNameExist($buff[1])) {
                    $valid_name = $buff[1];
                } else {
                    $valid_name = $this->getValidName($buff[1]);
                }
                $this->clients[$idx]['name'] = $valid_name;
                $log_msg = "$prev_name は $valid_name に名前を変更しました";
                $this->sendToAll($log_msg);
                echo "$log_msg\n";
            }
        }
    }

    private function getValidName($name)
    {
        $buff = '';
        $cnt = 0;
        do {
            if ($cnt == 0) {
                $buff = $name;
            } else {
                $buff = $name . $cnt;
            }
            $cnt++;
        } while ($this->doesNameExist($buff));
        return $buff;
    }

    private function doesNameExist($name):bool
    {
        if (! $this->clients) return false;
        foreach($this->clients as $client) {
            if ($client['name'] == $name) {
                return true;
            }
        }
        return false;
    }

    private function sendToAll(?string $msg)
    {
        foreach($this->clients as $client) {
            socket_send($client['socket'], $msg, self::BYTE_SIZE, 0);
        }
    }

    private function sendToAllExcept(int $idx, ?string $msg)
    {
        foreach($this->clients as $client) {
            if ($client['socket'] != $this->cp_sockets[$idx]) {
                socket_send($client['socket'], $msg, self::BYTE_SIZE, 0);
            }
        }
    }

    private function sendToClient(int $idx, ?string $msg)
    {
        socket_send($this->clients[$idx]['socket'], $msg, self::BYTE_SIZE, 0);
    }

    private function setCpSockets()
    {
        foreach($this->clients as $client) {
            $this->cp_sockets[] = $client['socket'];
        }
    }

    private function clearCpSockets()
    {
        $this->cp_sockets = [];
    }

    private function isMsgReceived():bool
    {
        $null = null;
        if (socket_select($this->cp_sockets, $null, $null, 0)) {
            return true;
        }
        return false;
    }
}