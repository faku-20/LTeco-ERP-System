<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\PrivacyRequest;
use RuntimeException;

final class PrivacyPanelSyncService
{
    public function __construct(private readonly PanelApiClient $panel){}
    public function sync(PrivacyRequest$request,string$idempotencyKey):void
    {
        $request->loadMissing('user');$details=$request->request_details_encrypted;
        $response=$this->panel->createPrivacyRequest(['request_uuid'=>$request->public_uuid,'user_uuid'=>$request->user->public_uuid,'type'=>$request->type,'name'=>$request->user->full_name,'email'=>$request->user->email,'details'=>(string)($details['details']??''),'due_at'=>$request->due_at->toISOString()],$idempotencyKey);
        if(!$response->successful())throw new RuntimeException('No se pudo registrar la solicitud en el panel.');
    }
    public function syncStatus(PrivacyRequest$request):void
    {
        $response=$this->panel->privacyRequestStatus($request->public_uuid);if(!$response->successful())throw new RuntimeException('No se pudo consultar la solicitud en el panel.');$data=$response->json('data');if(!is_array($data)||!in_array($data['status']??null,['submitted','in_review','resolved','rejected'],true))throw new RuntimeException('El panel devolvió un estado de privacidad inválido.');$request->update(['status'=>$data['status'],'resolution_manifest'=>['response'=>$data['response']??null,'panel_updated_at'=>$data['updated_at']??null],'resolved_at'=>!empty($data['resolved_at'])?$data['resolved_at']:null]);
    }
}
