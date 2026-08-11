<?php

declare(strict_types=1);

namespace Lteco\Application\Ecommerce;

use RuntimeException;

final class StorefrontApiException extends RuntimeException
{
    public int $status;
    public string $errorCode;
    public bool $retryable;

    public function __construct(
        int $status,
        string $errorCode,
        string $message,
        bool $retryable = false,
    ) {
        parent::__construct($message);
        $this->status = $status;
        $this->errorCode = $errorCode;
        $this->retryable = $retryable;
    }
}
