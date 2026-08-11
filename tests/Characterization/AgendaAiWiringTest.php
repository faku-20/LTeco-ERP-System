<?php

declare(strict_types=1);

final class AgendaAiWiringTest
{
    public static function run(): void
    {
        $base = dirname(__DIR__, 2);
        $agenda = (string)file_get_contents($base.'/src/Presentation/Panel/Support/agenda.php');
        $webhook = (string)file_get_contents($base.'/lteco-panel/whatsapp_webhook.php');
        $agendaPage = (string)file_get_contents($base.'/lteco-panel/agenda/index.php');
        $agendaHourPage = (string)file_get_contents($base.'/lteco-panel/agenda/hora.php');
        $alertsPage = (string)file_get_contents($base.'/lteco-panel/notificaciones/index.php');
        $analyzePage = (string)file_get_contents($base.'/lteco-panel/ia/acciones_analizar.php');
        $commercial = (string)file_get_contents($base.'/lteco-panel/comercial/index.php');
        $home = (string)file_get_contents($base.'/lteco-panel/inicio.php');
        $distributorStart = strpos($home, 'if ($esDist)');
        $adminStart = strpos($home, '} elseif ($esAdmin)', $distributorStart !== false ? $distributorStart : 0);
        $distributorHome = $distributorStart !== false && $adminStart !== false
            ? substr($home, $distributorStart, $adminStart - $distributorStart)
            : '';

        Assert::isTrue('Agenda IA wiring', 'webhook evalúa conversación', str_contains($webhook, 'agendaMaybeScheduleFromInbox'));
        Assert::isTrue('Agenda IA wiring', 'crea visita persistente', str_contains($agenda, 'INSERT INTO crm_visita'));
        Assert::isTrue('Agenda IA wiring', 'permite visita con hora pendiente', str_contains((string)file_get_contents($base.'/database/migrations/2026_08_05_000000_b5_runtime_schema.sql'), 'HoraConfirmada TINYINT(1) NOT NULL DEFAULT 1'));
        Assert::isTrue('Agenda IA wiring', 'fecha sola persiste hora no confirmada', str_contains($agenda, '$hourConfirmed ? 1 : 0'));
        Assert::isTrue('Agenda IA wiring', 'agenda muestra hora pendiente', str_contains($agendaPage, 'Hora pendiente'));
        Assert::isTrue('Agenda IA wiring', 'agenda permite confirmar hora', str_contains($agendaPage, "agenda/hora.php"));
        Assert::isTrue('Agenda IA wiring', 'endpoint confirma hora mediante soporte', str_contains($agendaHourPage, 'agendaConfirmVisitHour'));
        Assert::isTrue('Agenda IA wiring', 'crea alerta interna', str_contains($agenda, 'INSERT INTO internal_alert'));
        Assert::isTrue('Agenda IA wiring', 'registra visita pendiente sin inventar horario', str_contains($agenda, 'agendaRecordPendingVisit'));
        Assert::isTrue('Agenda IA wiring', 'crea acción para completar visita', str_contains($agenda, "'completar_visita'"));
        Assert::isTrue('Agenda IA wiring', 'análisis manual también revisa agenda', str_contains($analyzePage, 'agendaAnalyzeSavedConversations'));
        Assert::isTrue('Agenda IA wiring', 'actualiza estado del lead', str_contains($agenda, "Estado = 'visita_agendada'"));
        Assert::isTrue('Agenda IA wiring', 'genera evento n8n', str_contains($agenda, "n8nStoreEvent(\$pdo, 'visita_agendada'"));
        Assert::isTrue('Agenda IA wiring', 'despacha visita por webhook n8n', str_contains($agenda, "n8nDispatch(\$pdo, 'visita_agendada'"));
        Assert::isTrue('Agenda IA wiring', 'evita duplicado por horario', str_contains($agenda, 'INTERVAL 45 MINUTE'));
        Assert::isTrue('Agenda IA wiring', 'agenda restringe distribuidores', str_contains($agendaPage, 'requiereNoDistribuidor'));
        Assert::isTrue('Agenda IA wiring', 'alertas restringen distribuidores', str_contains($alertsPage, 'requiereNoDistribuidor'));
        Assert::isTrue('Agenda IA wiring', 'comercial enlaza agenda', str_contains($commercial, "agenda/index.php"));
        Assert::isTrue('Agenda IA wiring', 'vendedor tiene agenda en inicio', str_contains($home, "'label' => 'Agenda'"));
        Assert::isTrue('Agenda IA wiring', 'identifica navegación del distribuidor', $distributorHome !== '');
        Assert::isFalse('Agenda IA wiring', 'distribuidor no ve agenda', str_contains($distributorHome, 'agenda/index.php'));
        Assert::isFalse('Agenda IA wiring', 'distribuidor no ve alertas', str_contains($distributorHome, 'notificaciones/index.php'));
        Assert::isTrue('Agenda IA wiring', 'comercial exige rol administrador', str_contains($commercial, 'requiereAdmin()'));
    }
}
