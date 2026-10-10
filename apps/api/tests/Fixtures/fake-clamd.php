<?php

// Minimal clamd stand-in for tests: accepts one zINSTREAM session per connection and answers
// "stream: Eicar-Test-Signature FOUND" when the payload contains the EICAR marker, else "stream: OK".
// Usage: php fake-clamd.php <port-file>
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($server === false) {
    fwrite(STDERR, $error);
    exit(1);
}
file_put_contents($argv[1], (string) parse_url('tcp://'.stream_socket_get_name($server, false), PHP_URL_PORT));

$deadline = time() + 30;
while (time() < $deadline && ($conn = @stream_socket_accept($server, 30)) !== false) {
    $command = '';
    while (! str_ends_with($command, "\0")) {
        $command .= fread($conn, 1);
    }
    $payload = '';
    while (true) {
        $len = unpack('N', fread($conn, 4))[1];
        if ($len === 0) {
            break;
        }
        while ($len > 0) {
            $chunk = fread($conn, $len);
            $payload .= $chunk;
            $len -= strlen($chunk);
        }
    }
    fwrite($conn, str_contains($payload, 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE') ? "stream: Eicar-Test-Signature FOUND\0" : "stream: OK\0");
    fclose($conn);
}
