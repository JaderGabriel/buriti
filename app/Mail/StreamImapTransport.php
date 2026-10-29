<?php

namespace App\Mail;

use RuntimeException;

class StreamImapTransport implements ImapTransport
{
    /** @param resource $stream */
    public function __construct(private mixed $stream) {}

    public static function connect(ImapConfig $config): self
    {
        $remote = $config->encryption === 'ssl'
            ? 'ssl://'.$config->host.':'.$config->port
            : 'tcp://'.$config->host.':'.$config->port;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => filter_var($config->host, FILTER_VALIDATE_IP) === false,
                'peer_name' => $config->host,
                'SNI_enabled' => true,
            ],
        ]);

        $stream = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            $config->timeout,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if (! is_resource($stream)) {
            throw new RuntimeException('IMAP: não ligou a '.$config->host.':'.$config->port.' ('.$errstr.').');
        }

        stream_set_timeout($stream, $config->timeout);

        return new self($stream);
    }

    public function readLine(): string
    {
        $line = fgets($this->stream);
        if ($line === false) {
            throw new RuntimeException('IMAP: ligação fechada.');
        }

        return rtrim($line, "\r\n");
    }

    public function readBytes(int $length): string
    {
        $buffer = '';

        while (strlen($buffer) < $length) {
            $chunk = fread($this->stream, $length - strlen($buffer));
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('IMAP: leitura incompleta.');
            }
            $buffer .= $chunk;
        }

        return $buffer;
    }

    public function write(string $data): void
    {
        $written = fwrite($this->stream, $data);
        if ($written === false || $written !== strlen($data)) {
            throw new RuntimeException('IMAP: falha a escrever.');
        }
    }

    public function enableCrypto(): void
    {
        $ok = @stream_socket_enable_crypto($this->stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if ($ok !== true) {
            throw new RuntimeException('IMAP: STARTTLS falhou.');
        }
    }

    public function close(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }
}
