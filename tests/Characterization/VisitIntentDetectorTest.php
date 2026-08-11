<?php

declare(strict_types=1);

use Lteco\Application\Agenda\VisitIntentDetector;

final class VisitIntentDetectorTest
{
    public static function run(): void
    {
        $detector = new VisitIntentDetector();
        $now = new DateTimeImmutable('2026-07-10 12:00:00', new DateTimeZone('America/Montevideo'));

        $confirmed = $detector->detect('Cliente: Quiero verla en el showroom\nCliente: mañana a las 15', false, $now);
        Assert::isTrue('Agenda IA', 'agenda con intención fecha y hora', $confirmed['confirmed']);
        Assert::same('Agenda IA', 'fecha relativa correcta', '2026-07-11 15:00:00', $confirmed['date']?->format('Y-m-d H:i:s'));

        $fromPrompt = $detector->detect('Cliente: mañana a las 15', true, $now);
        Assert::isTrue('Agenda IA', 'acepta confirmación tras invitación automática', $fromPrompt['confirmed']);

        $splitConversation = $detector->detect("Equipo: Coordinamos visita al showroom\nCliente: mañana\nEquipo: ¿A qué hora?\nCliente: a las 16:30", false, $now);
        Assert::isTrue('Agenda IA', 'combina fecha y hora confirmadas en mensajes distintos', $splitConversation['confirmed']);
        Assert::same('Agenda IA', 'hora separada correcta', '2026-07-11 16:30:00', $splitConversation['date']?->format('Y-m-d H:i:s'));

        $missingHour = $detector->detect('Quiero pasar mañana por el showroom', false, $now);
        Assert::isFalse('Agenda IA', 'no agenda sin hora', $missingHour['confirmed']);

        $continuedConversation = $detector->detect('Cliente: Ok,si el otro sabado', false, $now);
        Assert::isTrue('Agenda IA', 'detecta aceptación de fecha como visita pendiente', $continuedConversation['wants_visit']);
        Assert::isFalse('Agenda IA', 'no inventa hora en una continuación', $continuedConversation['confirmed']);
        Assert::same('Agenda IA', 'otro sábado refiere a la semana siguiente', '2026-07-18', $continuedConversation['date_hint']?->format('Y-m-d'));

        $newDateWithoutTime = $detector->detect("Cliente: mañana a las 15\nEquipo: Podemos coordinar la visita\nCliente: dale, el domingo me parece bien", true, $now);
        Assert::isTrue('Agenda IA', 'mantiene intención ante nueva fecha sin hora', $newDateWithoutTime['wants_visit']);
        Assert::isFalse('Agenda IA', 'no hereda hora de una propuesta anterior', $newDateWithoutTime['confirmed']);
        Assert::same('Agenda IA', 'conserva nueva fecha con hora pendiente', '2026-07-12', $newDateWithoutTime['date_hint']?->format('Y-m-d'));

        $specs = $detector->detect('La batería carga de 5 a 6 hs y tiene 50 km de autonomía', false, $now);
        Assert::isFalse('Agenda IA', 'no confunde especificaciones con visita', $specs['confirmed']);

        $past = $detector->detect('Quiero pasar hoy al showroom a las 10', false, $now);
        Assert::isFalse('Agenda IA', 'no agenda horario pasado', $past['confirmed']);

        $explicit = $detector->detect('Agendame una visita el 12/07 a las 16:30', false, $now);
        Assert::isTrue('Agenda IA', 'acepta fecha explícita futura', $explicit['confirmed']);
        Assert::same('Agenda IA', 'fecha explícita correcta', '2026-07-12 16:30:00', $explicit['date']?->format('Y-m-d H:i:s'));
    }
}
