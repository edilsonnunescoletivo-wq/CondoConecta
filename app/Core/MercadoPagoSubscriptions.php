<?php

declare(strict_types=1);

namespace App\Core;

final class MercadoPagoSubscriptions
{
    public static function enabled(): bool
    {
        return (getenv('MP_ACCESS_TOKEN') ?: '') !== '';
    }

    public static function createPending(string $email, string $reason, float $amount, string $externalReference, string $backUrl): array
    {
        return self::request('POST', '/preapproval', [
            'reason' => $reason,
            'external_reference' => $externalReference,
            'payer_email' => $email,
            'auto_recurring' => [
                'frequency' => 1,
                'frequency_type' => 'months',
                'transaction_amount' => round($amount, 2),
                'currency_id' => 'BRL',
            ],
            'back_url' => $backUrl,
            'status' => 'pending',
        ]);
    }

    public static function get(string $id): array
    {
        return self::request('GET', '/preapproval/' . rawurlencode($id));
    }

    public static function validateWebhook(string $signature, string $requestId, string $dataId): bool
    {
        $secret = getenv('MP_WEBHOOK_SECRET') ?: '';
        if ($secret === '' || $signature === '' || $requestId === '' || $dataId === '') return false;
        $parts = [];
        foreach (explode(',', $signature) as $part) {
            [$k,$v] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$k] = $v;
        }
        $ts = $parts['ts'] ?? '';
        $v1 = $parts['v1'] ?? '';
        if ($ts === '' || $v1 === '') return false;
        $manifest = 'id:' . strtolower($dataId) . ';request-id:' . $requestId . ';ts:' . $ts . ';';
        $expected = hash_hmac('sha256', $manifest, $secret);
        return hash_equals($expected, $v1);
    }

    private static function request(string $method, string $path, ?array $body = null): array
    {
        $token = getenv('MP_ACCESS_TOKEN') ?: '';
        if ($token === '') throw new \RuntimeException('Mercado Pago não configurado.');
        if (!function_exists('curl_init')) throw new \RuntimeException('Extensão cURL não disponível.');
        $headers = ['Authorization: Bearer ' . $token, 'Accept: application/json'];
        if ($body !== null) $headers[] = 'Content-Type: application/json';
        $ch = curl_init('https://api.mercadopago.com' . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 25,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($raw === false || $error !== '') throw new \RuntimeException('Falha de comunicação com Mercado Pago.');
        $data = json_decode((string)$raw, true);
        if (!is_array($data) || $status < 200 || $status >= 300) throw new \RuntimeException('Mercado Pago recusou a operação.');
        return $data;
    }
}
