<?php

declare(strict_types=1);

use Lteco\Application\Ecommerce\ServiceNonceStore;
use Lteco\Application\Ecommerce\ServiceRequestAuthenticator;
use Lteco\Application\Ecommerce\StorefrontApiException;

final class StorefrontApiAuthenticationTest
{
    public static function run(): void
    {
        $nonces = new class implements ServiceNonceStore {
            /** @var array<string,bool> */ public array $values = [];
            public int $rateCount = 0;
            public function consumeRateSlot(string $keyId, int $windowStartUnix, int $limit): bool
            {
                if ($this->rateCount >= $limit) return false;
                $this->rateCount++;
                return true;
            }
            public function remember(string $keyId, string $nonce, int $expiresUnix): bool {
                $key = $keyId . ':' . $nonce;
                if (isset($this->values[$key])) return false;
                $this->values[$key] = true;
                return true;
            }
            public function purgeExpired(int $nowUnix): void {}
        };
        $auth = new ServiceRequestAuthenticator($nonces, [
            'storefront-current' => ['secret' => 'test-secret', 'scopes' => ['storefront.catalog.read']],
        ]);
        $now = 1_800_000_000;
        $path = '/api/storefront/v1/catalog?locale=es-UY';
        $nonce = 'c26d5660-e504-4e9c-8d9c-49cc3ef7ee88';
        $canonical = "GET\n{$path}\n{$now}\n{$nonce}\n" . hash('sha256', '');
        $headers = [
            'x-lteco-key-id' => 'storefront-current',
            'x-lteco-timestamp' => (string) $now,
            'x-lteco-nonce' => $nonce,
            'x-lteco-signature' => hash_hmac('sha256', $canonical, 'test-secret'),
            'x-correlation-id' => 'd5526518-60b8-4d22-8c1d-691f4704579f',
        ];

        $result = $auth->authenticate('GET', $path, '', $headers, 'storefront.catalog.read', $now);
        Assert::same('Storefront API auth', 'acepta firma canónica', 'storefront-current', $result['key_id']);
        Assert::same('Storefront API auth', 'preserva correlation id válido', $headers['x-correlation-id'], $result['correlation_id']);

        self::expectCode('rechaza replay', 'replay_detected', fn() => $auth->authenticate('GET', $path, '', $headers, 'storefront.catalog.read', $now));

        $bad = $headers;
        $bad['x-lteco-nonce'] = 'otro-nonce';
        self::expectCode('rechaza firma alterada', 'invalid_signature', fn() => $auth->authenticate('GET', $path, '', $bad, 'storefront.catalog.read', $now));

        $expiredNonce = 'c26d5660-e504-4e9c-8d9c-49cc3ef7ee89';
        $expired = $headers;
        $expired['x-lteco-timestamp'] = (string) ($now - 301);
        $expired['x-lteco-nonce'] = $expiredNonce;
        $expired['x-lteco-signature'] = hash_hmac('sha256', "GET\n{$path}\n" . ($now - 301) . "\n{$expiredNonce}\n" . hash('sha256', ''), 'test-secret');
        self::expectCode('rechaza timestamp vencido', 'expired_request', fn() => $auth->authenticate('GET', $path, '', $expired, 'storefront.catalog.read', $now));

        $scopeNonce = 'c26d5660-e504-4e9c-8d9c-49cc3ef7ee90';
        $scope = $headers;
        $scope['x-lteco-nonce'] = $scopeNonce;
        $scope['x-lteco-signature'] = hash_hmac('sha256', "GET\n{$path}\n{$now}\n{$scopeNonce}\n" . hash('sha256', ''), 'test-secret');
        self::expectCode('rechaza scope', 'insufficient_scope', fn() => $auth->authenticate('GET', $path, '', $scope, 'storefront.sales.write', $now));

        $limitedNonces = new class implements ServiceNonceStore {
            public function consumeRateSlot(string $keyId, int $windowStartUnix, int $limit): bool { return false; }
            public function remember(string $keyId, string $nonce, int $expiresUnix): bool { return true; }
            public function purgeExpired(int $nowUnix): void {}
        };
        $limited = new ServiceRequestAuthenticator($limitedNonces, [
            'storefront-current' => ['secret' => 'test-secret', 'scopes' => ['storefront.catalog.read']],
        ]);
        self::expectCode('rechaza ventana agotada', 'rate_limited', fn() => $limited->authenticate('GET', $path, '', $headers, 'storefront.catalog.read', $now));
    }

    private static function expectCode(string $field, string $code, callable $callback): void
    {
        try {
            $callback();
            Assert::isTrue('Storefront API auth', $field, false);
        } catch (StorefrontApiException $e) {
            Assert::same('Storefront API auth', $field, $code, $e->errorCode);
        }
    }
}
