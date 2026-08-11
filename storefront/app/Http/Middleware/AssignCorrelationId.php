<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class AssignCorrelationId
{
    public function handle(Request$request,Closure$next):Response
    {
        $id=(string)$request->header('X-Correlation-Id','');if(!Str::isUuid($id))$id=(string)Str::uuid();
        $request->attributes->set('correlation_id',$id);$response=$next($request);$response->headers->set('X-Correlation-Id',$id);return$response;
    }
}
