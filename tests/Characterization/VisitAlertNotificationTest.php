<?php

declare(strict_types=1);

final class VisitAlertNotificationTest
{
    public static function run(): void
    {
        $base = dirname(__DIR__, 2);
        $endpoint = (string)file_get_contents($base.'/lteco-panel/api/alerts/latest.php');
        $agendaSupport = (string)file_get_contents($base.'/src/Presentation/Panel/Support/agenda.php');
        $script = (string)file_get_contents($base.'/lteco-panel/assets/js/visit-alerts.js');
        $footer = (string)file_get_contents($base.'/src/Presentation/Panel/View/Includes/footer.php');
        $home = (string)file_get_contents($base.'/lteco-panel/inicio.php');
        $css = (string)file_get_contents($base.'/lteco-panel/assets/css/lteco.css');

        Assert::isTrue('Alerta visita global', 'endpoint exige administrador', str_contains($endpoint, 'requiereAdmin()'));
        Assert::isTrue('Alerta visita global', 'endpoint delega consulta', str_contains($endpoint, 'agendaLatestVisitAlertPayload'));
        Assert::isTrue('Alerta visita global', 'consulta devuelve visitas abiertas y pendientes', str_contains($agendaSupport, "Tipo IN ('visita_agendada','visita_pendiente') AND Estado = 'abierta'"));
        Assert::isTrue('Alerta visita global', 'footer limita a admin y superadmin', str_contains($footer, "function_exists('esAdmin') && esAdmin()"));
        Assert::isTrue('Alerta visita global', 'inicio limita a admin', str_contains($home, 'if (esAdmin())'));
        Assert::isTrue('Alerta visita global', 'sondeo cada quince segundos', str_contains($script, 'window.setInterval(poll, 15000)'));
        Assert::isTrue('Alerta visita global', 'evita repetir alerta vista', str_contains($script, 'lteco-last-visit-alert-id'));
        Assert::isTrue('Alerta visita global', 'modal enlaza agenda', str_contains($script, 'Ver agenda'));
        Assert::isTrue('Alerta visita global', 'visita incompleta enlaza alertas', str_contains($script, 'Visita por confirmar'));
        Assert::isTrue('Alerta visita global', 'soporta notificación del navegador', str_contains($script, 'new Notification'));
        Assert::isTrue('Alerta visita global', 'estilos responsive presentes', str_contains($css, '.visit-alert-modal-backdrop'));
    }
}
