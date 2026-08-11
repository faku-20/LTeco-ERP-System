<?php

declare(strict_types=1);

final class TelegramNotificationTest
{
    public static function run(): void
    {
        $base = dirname(__DIR__, 2);
        $support = (string) file_get_contents($base . '/src/Presentation/Panel/Support/telegram.php');
        $migration = (string) file_get_contents($base . '/database/migrations/2026_08_05_000000_b5_runtime_schema.sql');
        $wrapper = (string) file_get_contents($base . '/lteco-panel/includes/telegram.php');
        $cron = (string) file_get_contents($base . '/lteco-panel/cron/ecommerce.php');

        Assert::isTrue('Telegram', 'usa Bot API sendMessage', str_contains($support, 'api.telegram.org/bot') && str_contains($support, 'sendMessage'));
        Assert::isTrue('Telegram', 'no duplica entrega por pedido y chat', str_contains($migration, 'uq_telegram_tipo_ref_chat'));
        Assert::isTrue('Telegram', 'declara variables de entorno', str_contains($support, 'LTECO_TELEGRAM_BOT_TOKEN') && str_contains($support, 'LTECO_TELEGRAM_CHAT_IDS'));
        Assert::isTrue('Telegram', 'mensaje escapa HTML', str_contains($support, 'telegramEscape'));
        Assert::isTrue('Telegram', 'cron carga helper', str_contains($cron, "includes/telegram.php"));
        Assert::isTrue('Telegram', 'cron ofrece check', str_contains($cron, '--telegram-check'));
        Assert::isTrue('Telegram', 'cron ofrece test', str_contains($cron, '--telegram-test'));
        Assert::isTrue('Telegram', 'cron procesa pedidos web', str_contains($cron, 'telegramProcesarPedidosWeb'));
        Assert::isTrue('Telegram', 'web push queda opt-in para ventas', str_contains($cron, 'LTECO_WEB_SALES_NOTIFY_WEB_PUSH'));
        Assert::isTrue('Telegram', 'wrapper apunta a soporte', str_contains($wrapper, 'Support/telegram.php'));
    }
}
