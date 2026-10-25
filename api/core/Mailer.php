<?php
declare(strict_types=1);

require_once __DIR__ . '/Env.php';

// Sends a plain-text multipart email with optional private file attachments.
function send_project_email(string $to, string $subject, string $body, array $attachments = []): array
{
    $from = trim((string)Env::get('MAIL_FROM', 'no-reply@example.edu.gt'));
    // Local-only sender names such as no-reply@localhost are not valid From addresses.
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $smtpUser = trim((string)Env::get('MAIL_USERNAME', ''));
        $from = filter_var($smtpUser, FILTER_VALIDATE_EMAIL) ? $smtpUser : 'no-reply@example.edu.gt';
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return ['sent' => false, 'error' => 'El correo de destino no tiene un formato válido.'];

    $boundary = 'mixed_' . bin2hex(random_bytes(16));
    $headers = "From: {$from}\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
    $message = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n{$body}\r\n";
    foreach ($attachments as $attachment) {
        $path = (string)($attachment['path'] ?? '');
        if ($path === '' || !is_file($path) || !is_readable($path)) continue;
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string)($attachment['name'] ?? 'CV')));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = match ($extension) { 'pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', default => 'application/octet-stream' };
        $contents = file_get_contents($path);
        if (!is_string($contents)) continue;
        $message .= "--{$boundary}\r\nContent-Type: {$mime}; name=\"{$name}\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"{$name}\"\r\n\r\n" . chunk_split(base64_encode($contents)) . "\r\n";
    }
    $message .= "--{$boundary}--\r\n";
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    // SMTP is preferred when configured; native mail() remains the no-setup fallback.
    $host = trim((string)Env::get('MAIL_HOST', ''));
    if ($host === '') {
        $ok = @mail($to, $encodedSubject, $message, $headers);
        return ['sent' => $ok, 'error' => $ok ? null : 'PHP mail() no tiene un transporte saliente configurado en este servidor.'];
    }

    $port = (int)Env::get('MAIL_PORT', '587');
    $encryption = strtolower((string)Env::get('MAIL_ENCRYPTION', 'tls'));
    $username = (string)Env::get('MAIL_USERNAME', '');
    $password = (string)Env::get('MAIL_PASSWORD', '');
    $transport = $encryption === 'ssl' ? 'ssl://' : 'tcp://';
    $socket = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
    if (!is_resource($socket)) return ['sent' => false, 'error' => 'No se pudo conectar con el servidor SMTP configurado.'];
    stream_set_timeout($socket, 15);

    // Read a complete SMTP response and ensure it begins with an expected code.
    $expect = static function ($socket, array $codes): string {
        $response = '';
        do {
            $line = fgets($socket, 515);
            if ($line === false) throw new RuntimeException('Respuesta SMTP incompleta.');
            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-');
        if (!in_array(substr($response, 0, 3), $codes, true)) throw new RuntimeException('El servidor SMTP rechazó una operación.');
        return $response;
    };
    $command = static function ($socket, string $line, array $codes) use ($expect): string {
        if (fwrite($socket, $line . "\r\n") === false) throw new RuntimeException('No se pudo enviar un comando SMTP.');
        return $expect($socket, $codes);
    };

    try {
        $expect($socket, ['220']);
        $hello = gethostname() ?: 'localhost';
        $command($socket, 'EHLO ' . preg_replace('/[^A-Za-z0-9.-]/', '', $hello), ['250']);
        if ($encryption === 'tls') {
            $command($socket, 'STARTTLS', ['220']);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('No se pudo activar TLS.');
            $command($socket, 'EHLO ' . preg_replace('/[^A-Za-z0-9.-]/', '', $hello), ['250']);
        } elseif (!in_array($encryption, ['ssl', 'none'], true)) {
            throw new RuntimeException('Tipo de cifrado SMTP no válido.');
        }
        if ($username !== '') {
            $command($socket, 'AUTH LOGIN', ['334']);
            $command($socket, base64_encode($username), ['334']);
            $command($socket, base64_encode($password), ['235']);
        }
        $command($socket, 'MAIL FROM:<' . $from . '>', ['250']);
        $command($socket, 'RCPT TO:<' . $to . '>', ['250', '251']);
        $command($socket, 'DATA', ['354']);
        $raw = "To: {$to}\r\nSubject: {$encodedSubject}\r\n" . $headers . "\r\n" . $message;
        $raw = preg_replace('/(?m)^\./', '..', $raw) . "\r\n.\r\n";
        if (fwrite($socket, $raw) === false) throw new RuntimeException('No se pudo transmitir el mensaje.');
        $expect($socket, ['250']);
        $command($socket, 'QUIT', ['221']);
        fclose($socket);
        return ['sent' => true, 'error' => null];
    } catch (Throwable $error) {
        fclose($socket);
        error_log('Error de envío SMTP: ' . $error->getMessage());
        return ['sent' => false, 'error' => 'El servidor SMTP no aceptó el correo. Revisa la configuración SMTP en api/core/.env.'];
    }
}
