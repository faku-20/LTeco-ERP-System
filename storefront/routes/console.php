<?php

use App\Models\Order;
use App\Services\CheckoutCancellationService;
use App\Services\CheckoutPanelOrderService;
use App\Services\CheckoutPanelStatusService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Cache;
use App\Models\SecurityAuditEvent;
use App\Services\StorefrontReadiness;
use App\Models\OutboxEvent;
use App\Models\PrivacyRequest;
use App\Services\PrivacyPanelSyncService;
use Illuminate\Support\Facades\Mail;
use App\Services\StorefrontCatalogService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('storefront:expire-reservations', function (CheckoutCancellationService $cancellations): int {
    $expired = 0;
    Order::query()
        ->whereIn('status', ['awaiting_payment', 'pickup_coordination_pending'])
        ->whereNotNull('panel_reservation_id')
        ->where('expires_at', '<=', now())
        ->orderBy('id')
        ->chunkById(100, function ($orders) use ($cancellations, &$expired): void {
            foreach ($orders as $order) {
                try {
                    $cancellations->cancel($order, 'expired');
                    $expired++;
                } catch (Throwable $e) {
                    Log::warning('No se pudo vencer una reserva de storefront.', [
                        'order_uuid' => $order->public_uuid,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    $this->info("Reservas vencidas: {$expired}");
    return self::SUCCESS;
})->purpose('Libera en el panel las reservas de compra vencidas');

Schedule::command('storefront:expire-reservations')->everyMinute()->withoutOverlapping();

Artisan::command('storefront:sync-panel-orders', function (CheckoutPanelOrderService $panelOrders): int {
    $synced = 0;
    Order::query()->whereNull('panel_order_id')->whereNotNull('panel_reservation_id')
        ->whereIn('status', ['awaiting_payment','pickup_coordination_pending'])
        ->orderBy('id')->chunkById(100, function ($orders) use ($panelOrders, &$synced): void {
            foreach ($orders as $order) {
                try { $panelOrders->sync($order); $synced++; }
                catch (Throwable $e) { Log::warning('Pedido pendiente de sincronizar con panel.', ['order_uuid'=>$order->public_uuid,'error'=>$e->getMessage()]); }
            }
        });
    $this->info("Pedidos sincronizados: {$synced}");
    return self::SUCCESS;
})->purpose('Reintenta registrar en el panel los pedidos web reservados');

Schedule::command('storefront:sync-panel-orders')->everyMinute()->withoutOverlapping();

Artisan::command('storefront:sync-panel-statuses', function (CheckoutPanelStatusService $statuses): int {
    $synced=0;
    Order::query()->whereNotNull('panel_order_id')->where(function($query):void{$query->whereIn('status',['awaiting_payment','pickup_coordination_pending','paid','preparing','ready_for_pickup','refund_pending','payment_exception'])->orWhereNull('panel_order_number');})
        ->orderBy('id')->chunkById(100,function($orders)use($statuses,&$synced):void{foreach($orders as$order){try{$statuses->sync($order);$synced++;}catch(Throwable$e){Log::warning('No se pudo actualizar el estado del pedido desde el panel.',['order_uuid'=>$order->public_uuid,'error'=>$e->getMessage()]);}}});
    $this->info("Estados sincronizados: {$synced}");return self::SUCCESS;
})->purpose('Actualiza en la cuenta del cliente el pago, preparación y entrega del panel');

Schedule::command('storefront:sync-panel-statuses')->everyMinute()->withoutOverlapping();

Artisan::command('storefront:heartbeat',function():int{Cache::put('storefront:scheduler-heartbeat',now()->toISOString(),now()->addMinutes(10));$this->info('Heartbeat actualizado.');return self::SUCCESS;})->purpose('Registra actividad del scheduler sin exponer datos');
Schedule::command('storefront:heartbeat')->everyMinute()->withoutOverlapping();

Artisan::command('storefront:doctor',function(StorefrontReadiness $readiness):int{$failed=false;foreach($readiness->checks()as$name=>$ok){$this->line(($ok?'[OK] ':'[FAIL] ').$name);$failed=$failed||!$ok;}return$failed?self::FAILURE:self::SUCCESS;})->purpose('Valida la configuración mínima antes de publicar el ecommerce');

Artisan::command('storefront:catalog-refresh', function (StorefrontCatalogService $catalog): int {
    try {
        $result = $catalog->load();
        $models = $result['models'];

        $this->info('Fuente en tiempo real: '.($result['realtime'] ? 'sí' : 'no'));
        $this->info('Modelos agrupados: '.$models->count());
        $this->info('Variantes recibidas: '.$models->sum('cantidad_variantes'));
        $this->info('Unidades disponibles: '.$models->sum(
            fn (object $model): int => collect($model->variantes)->sum(
                fn (array $variant): int => (int) ($variant['availability']['quantity'] ?? 0),
            ),
        ));

        $this->table(
            ['Modelo', 'Slug', 'Variantes', 'Unidades', 'Estado'],
            $models->map(fn (object $model): array => [
                $model->nombre,
                $model->slug,
                $model->cantidad_variantes,
                collect($model->variantes)->sum(
                    fn (array $variant): int => (int) ($variant['availability']['quantity'] ?? 0),
                ),
                $model->disponible ? 'Disponible' : 'Agotado',
            ])->all(),
        );

        if (! $result['realtime']) {
            $this->error('El panel no respondió; se mostró el catálogo editorial de respaldo.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    } catch (Throwable $exception) {
        $this->error('No se pudo refrescar el catálogo: '.$exception->getMessage());
        return self::FAILURE;
    }
})->purpose('Consulta, agrupa y muestra el catálogo público vigente del panel');

Artisan::command('storefront:mail-check {--send=}',function(StorefrontReadiness$readiness):int{
    $configured=$readiness->checks()['mail_configured']??false;
    if(!$configured){$this->error('Correo: configuración incompleta.');return self::FAILURE;}
    $target=trim((string)$this->option('send'));
    if($target===''){$this->info('Correo: configuración OK. No se envió ningún mensaje.');return self::SUCCESS;}
    if(filter_var($target,FILTER_VALIDATE_EMAIL)===false){$this->error('Correo: destino inválido.');return self::FAILURE;}
    try{Mail::raw('La verificación de correo del storefront finalizó correctamente.',fn($message)=>$message->to($target)->subject('Prueba de correo LTecobike'));}
    catch(Throwable$e){Log::error('Falló la prueba explícita de correo.',['error'=>$e->getMessage()]);$this->error('Correo: envío fallido.');return self::FAILURE;}
    $this->info('Correo: mensaje de prueba aceptado.');return self::SUCCESS;
})->purpose('Valida correo sin enviar; --send=correo realiza una prueba explícita');

Artisan::command('storefront:privacy-maintenance {--execute}',function():int{
    $cutoff=now()->subDays((int)config('storefront_privacy.audit_retention_days',730));$audit=SecurityAuditEvent::query()->where('occurred_at','<',$cutoff)->count();$this->info("Eventos de seguridad vencidos: {$audit}");
    if(!$this->option('execute')){$this->comment('Simulación: no se eliminó información.');return self::SUCCESS;}
    if(!config('storefront_privacy.maintenance_enabled')){$this->error('La política de retención no está habilitada.');return self::FAILURE;}
    SecurityAuditEvent::query()->where('occurred_at','<',$cutoff)->delete();$this->info('Mantenimiento aplicado según la política configurada.');return self::SUCCESS;
})->purpose('Simula o aplica la retención aprobada de registros técnicos');
Schedule::command('storefront:privacy-maintenance')->dailyAt('03:20')->withoutOverlapping();

Artisan::command('storefront:sync-privacy-requests',function(PrivacyPanelSyncService $panel):int{$synced=0;OutboxEvent::query()->where('event_type','privacy.requested')->whereIn('status',['pending','failed'])->where('available_at','<=',now())->orderBy('id')->limit(50)->get()->each(function(OutboxEvent$event)use($panel,&$synced):void{try{$request=PrivacyRequest::query()->where('public_uuid',$event->aggregate_uuid)->firstOrFail();$panel->sync($request,$event->idempotency_key);$event->update(['status'=>'processed','processed_at'=>now(),'last_error'=>null]);$synced++;}catch(Throwable$e){$event->update(['status'=>'failed','attempts'=>$event->attempts+1,'available_at'=>now()->addMinutes(min(60,2**min(5,$event->attempts+1))),'last_error'=>mb_substr($e->getMessage(),0,1000)]);}});$this->info("Solicitudes sincronizadas: {$synced}");return self::SUCCESS;})->purpose('Reintenta registrar en el panel las solicitudes de privacidad');
Schedule::command('storefront:sync-privacy-requests')->everyMinute()->withoutOverlapping();

Artisan::command('storefront:sync-privacy-statuses',function(PrivacyPanelSyncService$panel):int{$synced=0;PrivacyRequest::query()->whereIn('status',['submitted','in_review'])->orderBy('id')->limit(100)->get()->each(function(PrivacyRequest$request)use($panel,&$synced):void{try{$panel->syncStatus($request);$synced++;}catch(Throwable$e){Log::warning('No se pudo actualizar una solicitud de privacidad desde el panel.',['request_uuid'=>$request->public_uuid,'error'=>$e->getMessage()]);}});$this->info("Estados de privacidad sincronizados: {$synced}");return self::SUCCESS;})->purpose('Actualiza en la cuenta del cliente el estado resuelto en el panel');
Schedule::command('storefront:sync-privacy-statuses')->everyMinute()->withoutOverlapping();
