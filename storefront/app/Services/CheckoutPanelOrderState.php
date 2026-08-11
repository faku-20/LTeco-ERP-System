<?php

declare(strict_types=1);

namespace App\Services;

final class CheckoutPanelOrderState
{
    /** @var array<string,string> */
    private const PANEL_TO_LOCAL = [
        'PendientePago' => 'awaiting_payment',
        'PagoEnRevision' => 'pickup_coordination_pending',
        'Pagado' => 'paid',
        'Preparando' => 'preparing',
        'Listo' => 'ready_for_pickup',
        'Entregado' => 'delivered',
        'Cancelado' => 'cancelled',
        'Vencido' => 'expired',
        'ReembolsoPendiente' => 'refund_pending',
        'Reembolsado' => 'refunded',
        'ExcepcionPagoSinStock' => 'payment_exception',
    ];

    /** @var array<string,int> */
    private const LOCAL_ORDER = [
        'draft' => 0,
        'reservation_pending' => 10,
        'awaiting_payment' => 20,
        'pickup_coordination_pending' => 20,
        'paid' => 30,
        'preparing' => 40,
        'ready_for_pickup' => 50,
        'delivered' => 60,
        'cancelled' => 90,
        'expired' => 90,
        'payment_exception' => 90,
        'refund_pending' => 95,
        'refunded' => 100,
    ];

    /** @var array<int,string> */
    private const TERMINAL_LOCAL = [
        'delivered',
        'cancelled',
        'expired',
        'payment_exception',
        'refunded',
    ];

    public static function localForPanel(string $panelStatus): ?string
    {
        return self::PANEL_TO_LOCAL[$panelStatus] ?? null;
    }

    public static function shouldApply(string $currentLocalStatus, string $nextLocalStatus): bool
    {
        if ($currentLocalStatus === $nextLocalStatus) {
            return false;
        }

        if (in_array($currentLocalStatus, self::TERMINAL_LOCAL, true)) {
            return false;
        }

        $currentOrder = self::LOCAL_ORDER[$currentLocalStatus] ?? null;
        $nextOrder = self::LOCAL_ORDER[$nextLocalStatus] ?? null;

        return $currentOrder !== null && $nextOrder !== null && $nextOrder >= $currentOrder;
    }

    /** @return array<int,string> */
    public static function knownPanelStatuses(): array
    {
        return array_keys(self::PANEL_TO_LOCAL);
    }
}
