<?php

declare(strict_types=1);

final class MobileOperationalTablesTest
{
    public static function run(): void
    {
        $base = dirname(__DIR__, 2);
        $agenda = (string)file_get_contents($base.'/lteco-panel/agenda/index.php');
        $alerts = (string)file_get_contents($base.'/lteco-panel/notificaciones/index.php');
        $actions = (string)file_get_contents($base.'/lteco-panel/ia/acciones.php');
        $whatsapp = (string)file_get_contents($base.'/lteco-panel/whatsapp/index.php');
        $css = (string)file_get_contents($base.'/lteco-panel/assets/css/lteco.css');
        $push = (string)file_get_contents($base.'/lteco-panel/assets/js/web-push.js');

        foreach (['Agenda' => $agenda, 'Alertas' => $alerts, 'IA acciones' => $actions, 'WhatsApp' => $whatsapp] as $screen => $source) {
            Assert::isTrue('QA mobile', $screen.' usa fichas móviles', str_contains($source, 'mobile-cards'));
            Assert::isTrue('QA mobile', $screen.' define etiquetas móviles', str_contains($source, 'data-label='));
        }
        Assert::isTrue('QA mobile', 'oculta encabezado de tabla en móvil', str_contains($css, '.mobile-cards thead'));
        Assert::isTrue('QA mobile', 'acciones ocupan ancho disponible', str_contains($css, '.mobile-cards td.mobile-card-actions'));
        Assert::isTrue('QA mobile', 'reserva espacio para campana', str_contains($css, 'padding-bottom: 88px'));
        Assert::isTrue('QA mobile', 'oculta activador push después de suscribir', str_contains($push, 'button.hidden = active'));
    }
}
