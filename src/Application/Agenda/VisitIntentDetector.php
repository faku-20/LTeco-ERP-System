<?php

declare(strict_types=1);

namespace Lteco\Application\Agenda;

use DateTimeImmutable;
use DateTimeZone;

final class VisitIntentDetector
{
    /** @return array{wants_visit:bool,confirmed:bool,date:?DateTimeImmutable,date_hint:?DateTimeImmutable,confidence:string,reason:string} */
    public function detect(
        string $conversation,
        bool $recentVisitPrompt = false,
        ?DateTimeImmutable $now = null
    ): array {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('America/Montevideo'));
        $normalized = mb_strtolower(trim($conversation));
        $wantsVisit = $recentVisitPrompt || $this->containsAny($normalized, [
            'paso', 'pasar', 'voy', 'verla', 'ver la moto', 'ver el modelo', 'showroom',
            'local', 'agenda', 'agendar', 'agendame', 'cita', 'coordinar', 'visita',
        ]);

        $lines = array_values(array_filter(array_map('trim', preg_split('/\R+/u', $normalized) ?: [])));
        $lines = array_reverse($lines !== [] ? $lines : [$normalized]);
        $date = null;
        $time = null;

        $latestDate = $this->inferDate($lines[0], $now);
        $latestTime = $this->inferTime($lines[0]);

        // Una fecha nueva sin hora reemplaza la propuesta anterior. No debe
        // heredar una hora vieja de otra visita dentro del historial reciente.
        if ($latestDate !== null && $latestTime === null) {
            $date = $latestDate;
        } else {
            foreach ($lines as $line) {
                $date ??= $this->inferDate($line, $now);
                $time ??= $this->inferTime($line);
                if ($date !== null && $time !== null) {
                    break;
                }
            }
        }

        if (!$wantsVisit && $date !== null && preg_match('/\b(?:ok(?:ay)?|si|sí|dale|perfecto|de acuerdo)\b/u', $normalized)) {
            $wantsVisit = true;
        }

        $appointment = null;
        if ($date !== null && $time !== null) {
            $appointment = $date->setTime($time['hour'], $time['minute']);
        }

        $confirmed = $wantsVisit
            && $appointment !== null
            && $time['confidence'] === 'alta'
            && $appointment > $now;

        $reason = match (true) {
            $confirmed => 'La conversación confirma una visita al showroom con fecha y hora claras.',
            $wantsVisit && $date !== null => 'Hay intención de visita y fecha, pero falta una hora clara.',
            $wantsVisit => 'Hay intención de visita, pero faltan fecha y hora claras.',
            default => '',
        };

        return [
            'wants_visit' => $wantsVisit,
            'confirmed' => $confirmed,
            'date' => $appointment,
            'date_hint' => $date,
            'confidence' => $confirmed ? 'alta' : ($wantsVisit && $date !== null ? 'media' : 'baja'),
            'reason' => $reason,
        ];
    }

    private function inferDate(string $text, DateTimeImmutable $now): ?DateTimeImmutable
    {
        if (str_contains($text, 'hoy')) {
            return $now->setTime(0, 0);
        }
        if (str_contains($text, 'mañana') || str_contains($text, 'manana')) {
            return $now->modify('+1 day')->setTime(0, 0);
        }

        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{2,4}))?\b/u', $text, $matches)) {
            $day = (int)$matches[1];
            $month = (int)$matches[2];
            $year = !empty($matches[3]) ? (int)$matches[3] : (int)$now->format('Y');
            $year = $year < 100 ? 2000 + $year : $year;
            if (!checkdate($month, $day, $year)) {
                return null;
            }
            $date = $now->setDate($year, $month, $day)->setTime(0, 0);
            return $date < $now->setTime(0, 0) && empty($matches[3]) ? $date->modify('+1 year') : $date;
        }

        $weekDays = [
            'lunes' => 1, 'martes' => 2, 'miercoles' => 3, 'miércoles' => 3,
            'jueves' => 4, 'viernes' => 5, 'sabado' => 6, 'sábado' => 6, 'domingo' => 7,
        ];
        foreach ($weekDays as $needle => $targetDay) {
            if (!str_contains($text, $needle)) {
                continue;
            }
            $days = ($targetDay - (int)$now->format('N') + 7) % 7;
            if (preg_match('/\b(?:el\s+)?otro\s+'.preg_quote($needle, '/').'\b/u', $text)) {
                $days += 7;
            }
            return $now->modify('+'.$days.' days')->setTime(0, 0);
        }

        return null;
    }

    /** @return array{hour:int,minute:int,confidence:string}|null */
    private function inferTime(string $text): ?array
    {
        $matches = [];
        $found = preg_match('/(?:a las|para las|sobre las|tipo|a eso de las)\s*(\d{1,2})(?:[:\.](\d{2}))?\s*(?:hs|h|horas)?/u', $text, $matches)
            || preg_match('/\b(\d{1,2})(?:[:\.](\d{2}))\b/u', $text, $matches)
            || preg_match('/\b(\d{1,2})\s*(?:hs|h|horas)\b/u', $text, $matches);
        if (!$found) {
            return null;
        }

        $hour = (int)$matches[1];
        $minute = isset($matches[2]) && $matches[2] !== '' ? (int)$matches[2] : 0;
        $afternoon = str_contains($text, 'pm') || str_contains($text, 'tarde') || str_contains($text, 'noche');
        if ($afternoon && $hour >= 1 && $hour <= 11) {
            $hour += 12;
        }
        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return null;
        }

        $confidence = $hour >= 8 && $hour <= 20 ? 'alta' : 'media';
        return ['hour' => $hour, 'minute' => $minute, 'confidence' => $confidence];
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }
        return false;
    }
}
