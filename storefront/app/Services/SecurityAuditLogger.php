<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\SecurityAuditEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class SecurityAuditLogger
{
    /** @param array<string,mixed> $fields */
    public function record(Request$request,string$action,string$objectType,?string$objectReference=null,array$fields=[],?User$user=null):void
    {
        try{
            $user??=$request->user();$key=(string)config('storefront_privacy.audit_hash_key');if($key==='')$key=(string)config('app.key');
            $ip=$request->ip();$ua=preg_replace('/[^\x20-\x7E]/','',(string)$request->userAgent());
            SecurityAuditEvent::query()->create([
                'user_id'=>$user?->id,'actor_type'=>$user?'customer':'anonymous','actor_reference'=>$user?->public_uuid,
                'action'=>$action,'object_type'=>$objectType,'object_reference'=>$objectReference,
                'fields'=>$this->safeFields($fields),'ip_hash'=>$ip?hash_hmac('sha256',$ip,$key):null,
                'user_agent_summary'=>Str::limit((string)$ua,190,''),'correlation_id'=>$request->attributes->get('correlation_id'),'occurred_at'=>now(),
            ]);
        }catch(\Throwable$e){Log::warning('No se pudo registrar un evento de seguridad.',['action'=>$action,'error'=>$e->getMessage()]);}
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    private function safeFields(array$fields):array
    {
        $allowed=['changed_fields','result','request_type','address_id','order_uuid'];return array_intersect_key($fields,array_flip($allowed));
    }
}
