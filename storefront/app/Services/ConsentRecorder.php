<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ConsentRecorder
{
    public function record(Request$request,User$user,string$purpose,string$version,string$document):ConsentRecord
    {
        $key=(string)config('storefront_privacy.audit_hash_key');$ip=$request->ip();$ua=preg_replace('/[^\x20-\x7E]/','',(string)$request->userAgent());
        return ConsentRecord::query()->create(['user_id'=>$user->id,'purpose'=>$purpose,'document_version'=>$version,'document_hash'=>hash('sha256',$document),'accepted_at'=>now(),'ip_hash'=>$ip?hash_hmac('sha256',$ip,$key):null,'user_agent_summary'=>Str::limit((string)$ua,190,'')]);
    }
}
