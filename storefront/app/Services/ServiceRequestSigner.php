<?php

declare(strict_types=1);

namespace App\Services;

final class ServiceRequestSigner
{
    public function signature(
        string $secret,
        string $method,
        string $pathWithQuery,
        int $timestamp,
        string $nonce,
        string $rawBody,
    ): string {
        $canonical = implode("\n", [
            strtoupper($method),
            $pathWithQuery,
            (string) $timestamp,
            $nonce,
            hash('sha256', $rawBody),
        ]);

        return hash_hmac('sha256', $canonical, $secret);
    }

    public function verify(
        string $expected,
        string $provided,
    ): bool {
        return hash_equals($expected, strtolower($provided));
    }
}
