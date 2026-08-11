<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class HealthController extends Controller
{
    public function live():JsonResponse{return response()->json(['status'=>'ok'])->header('Cache-Control','no-store');}

    public function ready():JsonResponse
    {
        try{DB::connection()->select('SELECT 1');DB::connection('catalog')->select('SELECT 1');return response()->json(['status'=>'ok'])->header('Cache-Control','no-store');}
        catch(\Throwable$e){Log::error('Storefront no está listo.',['correlation_id'=>request()->attributes->get('correlation_id'),'exception'=>get_class($e)]);return response()->json(['status'=>'unavailable'],503)->header('Cache-Control','no-store');}
    }
}
