<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PrivacyRequest;
use App\Services\SecurityAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\OutboxEvent;
use App\Services\PrivacyPanelSyncService;
use Illuminate\Support\Facades\Log;

final class PrivacyController extends Controller
{
    public function store(Request $request,SecurityAuditLogger $audit,PrivacyPanelSyncService $panel):RedirectResponse
    {
        $data=$request->validate(['type'=>['required',Rule::in(['access','correction','suppression','objection'])],'details'=>['nullable','string','max:2000']]);$user=$request->user();
        if($user->privacyRequests()->where('type',$data['type'])->whereIn('status',['submitted','in_review'])->exists())throw ValidationException::withMessages(['privacy'=>'Ya existe una solicitud abierta de ese tipo.']);
        $privacy=PrivacyRequest::query()->create(['user_id'=>$user->id,'type'=>$data['type'],'status'=>'submitted','request_details_encrypted'=>['details'=>trim((string)($data['details']??''))],'due_at'=>now()->addWeekdays((int)config('storefront_privacy.request_due_business_days',5))]);
        $event=OutboxEvent::query()->create(['aggregate_type'=>'privacy_request','aggregate_uuid'=>$privacy->public_uuid,'event_type'=>'privacy.requested','payload'=>[],'status'=>'pending','available_at'=>now()]);
        try{$panel->sync($privacy,$event->idempotency_key);$event->update(['status'=>'processed','processed_at'=>now()]);}catch(\Throwable$e){Log::warning('Solicitud de privacidad pendiente de sincronizar con el panel.',['request_uuid'=>$privacy->public_uuid,'error'=>$e->getMessage()]);}
        $audit->record($request,'privacy.requested','privacy_request',$privacy->public_uuid,['request_type'=>$privacy->type],$user);
        return back()->with('status','Recibimos tu solicitud. La responderemos por un canal verificado dentro del plazo aplicable.');
    }

    public function export(Request $request,SecurityAuditLogger $audit):StreamedResponse
    {
        $data=$request->validate(['current_password'=>['required','string']]);$user=$request->user();if(!Hash::check($data['current_password'],$user->password))throw ValidationException::withMessages(['current_password'=>'La contraseña actual no es correcta.']);
        $user->load(['profile','addresses','orders.items','consents','privacyRequests']);
        $payload=['generated_at'=>now()->toISOString(),'account'=>['public_id'=>$user->public_uuid,'first_name'=>$user->first_name,'last_name'=>$user->last_name,'email'=>$user->email,'email_verified_at'=>$user->email_verified_at?->toISOString(),'created_at'=>$user->created_at?->toISOString()],
            'profile'=>$user->profile?->only(['customer_type','legal_name','phone_encrypted','cedula_encrypted','rut_encrypted','status']),
            'addresses'=>$user->addresses->map(fn($a)=>['type'=>$a->type,'line1'=>$a->line1_encrypted,'line2'=>$a->line2_encrypted,'city'=>$a->city_encrypted,'department'=>$a->department_encrypted,'postal_code'=>$a->postal_code_encrypted,'country'=>$a->country,'is_primary'=>$a->is_primary])->values(),
            'orders'=>$user->orders->map(fn($o)=>['public_id'=>$o->public_uuid,'status'=>$o->status,'payment_method'=>$o->payment_method,'currency'=>$o->currency,'subtotal'=>$o->subtotal,'discount'=>$o->discount,'total'=>$o->total,'created_at'=>$o->created_at?->toISOString(),'items'=>$o->items->map(fn($i)=>$i->only(['model','battery_ah','color','gross','vat_included','currency']))->values()])->values(),
            'consents'=>$user->consents->map(fn($c)=>$c->only(['purpose','document_version','document_hash','accepted_at','withdrawn_at']))->values(),
            'privacy_requests'=>$user->privacyRequests->map(fn($p)=>['public_id'=>$p->public_uuid,'type'=>$p->type,'status'=>$p->status,'created_at'=>$p->created_at?->toISOString(),'due_at'=>$p->due_at?->toISOString(),'resolved_at'=>$p->resolved_at?->toISOString()])->values()];
        $audit->record($request,'privacy.exported','user',$user->public_uuid,['result'=>'downloaded'],$user);$json=json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        return response()->streamDownload(static function()use($json):void{echo$json;},'mis-datos-ltecobike-'.now()->format('Ymd').'.json',['Content-Type'=>'application/json; charset=UTF-8','Cache-Control'=>'no-store, private','X-Content-Type-Options'=>'nosniff']);
    }
}
