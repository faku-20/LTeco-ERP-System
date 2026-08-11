<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ServiceRequestSigner;
use PHPUnit\Framework\TestCase;

final class ServiceRequestSignerTest extends TestCase
{
    public function test_signature_uses_the_documented_canonical_format(): void
    {
        $signer = new ServiceRequestSigner();
        $body = '{"order_uuid":"123"}';
        $canonical = implode("\n", [
            'POST',
            '/internal/v1/panel-events',
            '1721923200',
            '018f1234-1234-7123-8123-123456789abc',
            hash('sha256', $body),
        ]);

        self::assertSame(
            hash_hmac('sha256', $canonical, 'test-secret'),
            $signer->signature(
                'test-secret',
                'post',
                '/internal/v1/panel-events',
                1721923200,
                '018f1234-1234-7123-8123-123456789abc',
                $body,
            ),
        );
    }

    public function test_verify_rejects_a_different_signature(): void
    {
        $signer = new ServiceRequestSigner();

        self::assertFalse($signer->verify(str_repeat('a', 64), str_repeat('b', 64)));
    }
}
