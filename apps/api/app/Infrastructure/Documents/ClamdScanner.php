<?php

namespace App\Infrastructure\Documents;

use App\Domain\Documents\Contracts\DocumentScanner;
use App\Domain\Documents\Enums\ScanStatus;
use RuntimeException;

/**
 * ClamAV daemon client using the INSTREAM command over TCP (zINSTREAM: chunks prefixed with a
 * 4-byte big-endian length, terminated by a zero-length chunk). Replies: "stream: OK",
 * "stream: <signature> FOUND" or "... ERROR".
 */
final class ClamdScanner implements DocumentScanner
{
    private const CHUNK = 65536;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly int $timeout,
    ) {}

    public function isConfigured(): bool
    {
        return true;
    }

    public function scan($stream): array
    {
        $socket = @stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $error, $this->timeout);
        if ($socket === false) {
            return [ScanStatus::Error, "clamd unreachable: {$error}"];
        }
        stream_set_timeout($socket, $this->timeout);

        try {
            $this->write($socket, "zINSTREAM\0");
            while (! feof($stream)) {
                $chunk = fread($stream, self::CHUNK);
                if ($chunk === false) {
                    throw new RuntimeException('Could not read the file.');
                }
                if ($chunk !== '') {
                    $this->write($socket, pack('N', strlen($chunk)).$chunk);
                }
            }
            $this->write($socket, pack('N', 0));
            $reply = trim((string) stream_get_contents($socket), "\0\r\n ");
        } catch (RuntimeException $e) {
            return [ScanStatus::Error, $e->getMessage()];
        } finally {
            fclose($socket);
        }

        return match (true) {
            str_ends_with($reply, 'OK') => [ScanStatus::Clean, null],
            str_ends_with($reply, 'FOUND') => [ScanStatus::Infected, substr(trim(substr($reply, strpos($reply, ':') + 1)), 0, -6) ?: 'malware'],
            default => [ScanStatus::Error, substr($reply, 0, 200)],
        };
    }

    /**
     * @param  resource  $socket
     */
    private function write($socket, string $data): void
    {
        while ($data !== '') {
            $written = fwrite($socket, $data);
            if ($written === false || $written === 0) {
                throw new RuntimeException('clamd connection closed while sending.');
            }
            $data = substr($data, $written);
        }
    }
}
