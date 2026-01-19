<?php

namespace Core;

class JWT
{
    private string $secret;

    public function __construct()
    {
        $this->secret = getenv('JWT_SECRET') ?: 'default_secret_please_change';
    }

    public function generate(array $payload): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload['iat'] = time();
        $payload['exp'] = time() + (60 * 60 * 24); // 24 hours

        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode(json_encode($payload));

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $this->secret, true);
        $base64UrlSignature = $this->base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public function validate(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;

        $validSignature = hash_hmac('sha256', $header . "." . $payload, $this->secret, true);
        $validBase64UrlSignature = $this->base64UrlEncode($validSignature);

        if (!hash_equals($validBase64UrlSignature, $signature)) {
            return null;
        }

        $decodedPayload = json_decode($this->base64UrlDecode($payload), true);

        if (isset($decodedPayload['exp']) && $decodedPayload['exp'] < time()) {
            return null;
        }

        return $decodedPayload;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(str_replace('-_', '+/', $data));
    }
}
