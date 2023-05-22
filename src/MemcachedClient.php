<?php

namespace MemcachedClient;

use InvalidArgumentException;
use RuntimeException;

class MemcachedClient
{
    private const MAX_VALUE_SIZE = 1048576; // 1 MB
    private const RESPONSE_STORED = 'STORED';
    private const RESPONSE_DELETED = 'DELETED';
    private const RESPONSE_NOT_FOUND = 'NOT_FOUND';
    private const RESPONSE_END = 'END';

    private $socket;

    public function __construct(string $host = '127.0.0.1', int $port = 11211)
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->socket === false) {
            throw new RuntimeException('Failed to create socket');
        }

        if (!socket_connect($this->socket, $host, $port)) {
            socket_close($this->socket);
            throw new RuntimeException('Failed to connect to server');
        }

        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 7, 'usec' => 0]);
        socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 7, 'usec' => 0]);
    }

    public function __destruct()
    {
        socket_close($this->socket);
    }

    public function set(string $key, $value, int $expiration = 0, int $flags = 0): bool
    {
        if (strpos($key, ' ') !== false) {
            throw new InvalidArgumentException('Key should not contain spaces');
        }

        if (strlen($value) > self::MAX_VALUE_SIZE) {
            throw new InvalidArgumentException('Value is too large');
        }

        $command = sprintf("set %s %d %d %d\r\n%s\r\n", $key, $flags, $expiration, strlen($value), $value);
        $result = $this->sendCommand($command);

        return $result === self::RESPONSE_STORED;
    }

    public function get(string $key)
    {
        if (strpos($key, ' ') !== false) {
            throw new InvalidArgumentException('Key should not contain spaces');
        }

        $command = sprintf("get %s\r\n", $key);
        $result = $this->sendCommand($command);

        if (is_array($result) && count($result) >= 2 && $result[0] === $key) {
            return $result[1];
        }

        return null;
    }

    public function delete(string $key): bool
    {
        if (strpos($key, ' ') !== false) {
            throw new InvalidArgumentException('Key should not contain spaces');
        }

        $command = sprintf("delete %s\r\n", $key);
        $result = $this->sendCommand($command);

        return $result === self::RESPONSE_DELETED;
    }

    private function sendCommand(string $command)
    {
        $bytesSent = socket_write($this->socket, $command, strlen($command));
        if ($bytesSent === false || $bytesSent !== strlen($command)) {
            throw new RuntimeException('Could not send command to server');
        }

        $response = '';
        while (true) {
            $data = socket_read($this->socket, 1024);
            if ($data === false || $data === '' || $data === "\r\n") {
                break;
            }
            $response .= $data;
        }

        $lines = explode("\r\n", $response);
        $lastLine = array_pop($lines);

        if ($lastLine !== self::RESPONSE_END) {
            throw new RuntimeException('Invalid response from server');
        }

        if (strncmp($command, 'get', 3) === 0) {
            $values = [];
            $socket = $this->socket;
            foreach ($lines as $line) {
                $parts = explode(' ', $line);
                if (count($parts) !== 3 || $parts[0] !== 'VALUE' || $parts[1] !== $key) {
                    throw new RuntimeException('Invalid response from server');
                }
                $valueLength = (int)$parts[2];
                $value = socket_read($socket, $valueLength);
                socket_read($socket, 2); // Пропускаем \r\n после значения
                $values[] = $value;
            }
            return $values;
        }

        return trim($lastLine);
    }
}
