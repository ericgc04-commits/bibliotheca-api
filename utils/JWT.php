<?php
/**
 * utils/JWT.php
 * Minimal JWT implementation using HMAC-SHA256.
 * No external dependencies required.
 */

class JWT {

    /**
     * Creates a signed JWT token.
     *
     * @param array $payload  Data to encode (e.g. ['id' => 1, 'role' => 'admin'])
     * @param int   $expiry   Lifetime in seconds (default from config)
     */
    public static function encode(array $payload, int $expiry = JWT_EXPIRY): string {
        $header = self::base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));

        $payload['iat'] = time();
        $payload['exp'] = time() + $expiry;

        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        $signature = self::base64UrlEncode(
            hash_hmac('sha256', "$header.$payloadEncoded", JWT_SECRET, true)
        );

        return "$header.$payloadEncoded.$signature";
    }

    /**
     * Verifies and decodes a JWT.
     *
     * @return array  Decoded payload
     * @throws RuntimeException  On invalid or expired token
     */
    public static function decode(string $token): array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid token format');
        }

        [$header, $payloadEncoded, $signature] = $parts;

        $expectedSig = self::base64UrlEncode(
            hash_hmac('sha256', "$header.$payloadEncoded", JWT_SECRET, true)
        );

        // Constant-time comparison to prevent timing attacks
        if (!hash_equals($expectedSig, $signature)) {
            throw new RuntimeException('Invalid token signature');
        }

        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);
        if (!$payload) {
            throw new RuntimeException('Invalid token payload');
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new RuntimeException('Token has expired');
        }

        return $payload;
    }

    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }
}
