<?php

declare(strict_types=1);

namespace Lteco\Application\Whatsapp;

final class InitialAutoReplyEligibility
{
    public function shouldReply(string $message, string $replyToMessageId = ''): bool
    {
        if (trim($replyToMessageId) !== '') {
            return false;
        }

        $normalized = mb_strtolower(trim($message));
        if ($normalized === '') {
            return false;
        }
        if (preg_match('/^\[[^]]+\]$/u', $normalized) && $normalized !== '[unsupported]') {
            return false;
        }

        return !preg_match('/^(?:ok(?:ay)?|si|sí|dale|perfecto|de acuerdo|claro|gracias|genial|listo|bárbaro|barbaro)\b/u', $normalized);
    }
}
