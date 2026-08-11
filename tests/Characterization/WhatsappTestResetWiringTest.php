<?php

declare(strict_types=1);

final class WhatsappTestResetWiringTest
{
    public static function run(): void
    {
        $base = dirname(__DIR__, 2);
        $endpoint = (string)file_get_contents($base.'/lteco-panel/configuracion/mantenimiento/whatsapp_reset_prueba.php');
        $repository = (string)file_get_contents($base.'/src/Infrastructure/Repository/WhatsappRepository.php');
        $aiSupport = (string)file_get_contents($base.'/src/Presentation/Panel/Support/ai.php');

        Assert::isTrue('Reset prueba WhatsApp', 'endpoint exige Superadmin', str_contains($endpoint, 'requiereSuperadmin()'));
        Assert::isTrue('Reset prueba WhatsApp', 'endpoint exige POST', str_contains($endpoint, 'requirePost()'));
        Assert::isTrue('Reset prueba WhatsApp', 'endpoint valida CSRF', str_contains($endpoint, 'verifyCsrfOrFail()'));
        Assert::isTrue('Reset prueba WhatsApp', 'usa teléfono de prueba configurado', str_contains($endpoint, "configEnv('LTECO_WHATSAPP_TEST_PHONE'"));
        Assert::isFalse('Reset prueba WhatsApp', 'no acepta un teléfono arbitrario por POST', str_contains($endpoint, 'telefono_reset'));

        Assert::isTrue('Reset prueba WhatsApp', 'guarda marca consumible', str_contains($repository, 'whatsapp_test_reset'));
        Assert::isTrue('Reset prueba WhatsApp', 'programa marca pendiente', str_contains($repository, "Estado = 'pendiente'"));
        Assert::isTrue('Reset prueba WhatsApp', 'consume marca tras el envío', str_contains($repository, "Estado = 'consumido'"));
        Assert::isFalse('Reset prueba WhatsApp', 'no borra conversaciones', str_contains($repository, 'DELETE FROM commercial_inbox_message'));
        Assert::isFalse('Reset prueba WhatsApp', 'no borra notificaciones', str_contains($repository, 'DELETE FROM notificacion_whatsapp'));

        Assert::isTrue('Reset prueba WhatsApp', 'reclama la excepción una sola vez', str_contains($aiSupport, 'reclamarResetPrueba'));
        Assert::isTrue('Reset prueba WhatsApp', 'libera la excepción si no envía', str_contains($aiSupport, 'liberarResetPrueba'));
        Assert::isTrue('Reset prueba WhatsApp', 'completa la excepción al enviar', str_contains($aiSupport, 'completarResetPrueba'));
        Assert::isTrue('Reset prueba WhatsApp', 'mantiene protección de historial global', str_contains($aiSupport, 'IdInbox < ?'));
    }
}
