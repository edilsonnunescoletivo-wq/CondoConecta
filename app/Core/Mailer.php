<?php

declare(strict_types=1);

namespace App\Core;

final class Mailer
{
    public static function send(array $config, string $to, string $subject, string $html, string $text = ''): bool
    {
        $mail = $config['mail'] ?? [];
        $driver = (string) ($mail['driver'] ?? 'mail');
        $fromAddress = trim((string) ($mail['from_address'] ?? ''));
        $fromName = trim((string) ($mail['from_name'] ?? ($config['name'] ?? 'CondoConecta')));

        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if ($driver === 'log') {
            error_log("[CondoConecta mail] TO={$to} SUBJECT={$subject}\n" . ($text !== '' ? $text : strip_tags($html)));
            return true;
        }

        if ($driver !== 'mail') {
            return false;
        }

        $safeName = preg_replace('/[\r\n]+/', ' ', $fromName) ?: 'CondoConecta';
        $safeSubject = preg_replace('/[\r\n]+/', ' ', $subject) ?: 'CondoConecta';
        $boundary = '=_condoconecta_' . bin2hex(random_bytes(12));
        $plain = $text !== '' ? $text : trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        $headers = [
            'MIME-Version: 1.0',
            'From: ' . $safeName . ' <' . $fromAddress . '>',
            'Reply-To: ' . $fromAddress,
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $plain . "\r\n\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $html . "\r\n\r\n";
        $body .= "--{$boundary}--\r\n";

        return mail($to, $safeSubject, $body, implode("\r\n", $headers));
    }
}
