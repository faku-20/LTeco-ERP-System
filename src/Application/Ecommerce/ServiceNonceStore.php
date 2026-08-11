<?php

declare(strict_types=1);

namespace Lteco\Application\Ecommerce;

interface ServiceNonceStore
{
    public function consumeRateSlot(string $keyId, int $windowStartUnix, int $limit): bool;

    public function remember(string $keyId, string $nonce, int $expiresUnix): bool;

    public function purgeExpired(int $nowUnix): void;
}
