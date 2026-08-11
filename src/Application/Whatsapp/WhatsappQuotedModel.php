<?php

declare(strict_types=1);

namespace Lteco\Application\Whatsapp;

final class WhatsappQuotedModel
{
    private const MODELS = [
        'q8_350w' => 'Q8 350W',
        'q8_500w' => 'Q8 500W',
        'ly_500w' => 'LY 500W',
        'sl_500w' => 'SL 500W',
    ];

    public static function fromTemplate(string $template): ?string
    {
        if (!str_starts_with($template, 'auto_respuesta_')) {
            return null;
        }

        foreach (self::MODELS as $key => $name) {
            if (str_contains($template, $key)) {
                return $name;
            }
        }

        return null;
    }
}
