<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\VisitController;
use App\Services\StorefrontCatalogService;
use Illuminate\Support\Facades\Route;

Route::get('/robots.txt',function(){
    $indexable=(bool)config('storefront_seo.indexable',false);
    $body=$indexable
        ? "User-agent: *\nAllow: /\n\nSitemap: ".url('/sitemap.xml')."\n"
        : "User-agent: *\nDisallow: /\n";
    return response($body,200,['Content-Type'=>'text/plain; charset=UTF-8']);
})->name('seo.robots');

Route::get('/sitemap.xml',function(){
    $base=[
        ['path'=>'/','changefreq'=>'weekly','priority'=>'1.0'],
        ['path'=>'/modelos','changefreq'=>'weekly','priority'=>'0.9'],
        ['path'=>'/nosotros','changefreq'=>'monthly','priority'=>'0.5'],
        ['path'=>'/contacto','changefreq'=>'monthly','priority'=>'0.7'],
        ['path'=>'/agenda','changefreq'=>'monthly','priority'=>'0.6'],
        ['path'=>'/calculadora-ahorro','changefreq'=>'monthly','priority'=>'0.6'],
        ['path'=>'/privacidad','changefreq'=>'yearly','priority'=>'0.3'],
        ['path'=>'/terminos','changefreq'=>'yearly','priority'=>'0.3'],
    ];
    try {
        foreach (app(StorefrontCatalogService::class)->load()['models'] as $model) {
            $base[]=['path'=>'/modelos/'.$model->slug,'changefreq'=>'weekly','priority'=>'0.85'];
        }
    } catch (Throwable) { }
    $lastmod=now()->toDateString();
    $urls=collect($base)
        ->unique('path')
        ->map(fn(array $item)=>'<url><loc>'.htmlspecialchars(url($item['path']),ENT_XML1|ENT_QUOTES,'UTF-8').'</loc><lastmod>'.$lastmod.'</lastmod><changefreq>'.$item['changefreq'].'</changefreq><priority>'.$item['priority'].'</priority></url>')
        ->implode('');
    return response('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$urls.'</urlset>',200,['Content-Type'=>'application/xml; charset=UTF-8']);
})->name('seo.sitemap');

Route::view('/', 'catalogo')
    ->name('catalogo');

Route::get(
    '/modelos',
    function (
        StorefrontCatalogService $catalog
    ) {
        $result = $catalog->load();

        return view(
            'pages.modelos',
            [
                'motos' => $result['models'],
                'datosEnTiempoRealDisponibles' => (
                    $result['realtime']
                ),
            ],
        );
    },
)
    ->name('modelos.index');

Route::get(
    '/modelos/{slug}',
    function (
        string $slug,
        StorefrontCatalogService $catalog
    ) {
        $moto = $catalog->find($slug);

        abort_if(
            $moto === null,
            404,
        );

        return view(
            'modelo',
            [
                'slug' => $slug,
                'moto' => $moto,
            ],
        );
    },
)->name('modelos.show');

Route::view('/nosotros', 'pages.nosotros')
    ->name('nosotros');

Route::get('/contacto', [ContactController::class, 'show'])->name('contacto');
Route::post('/contacto', [ContactController::class, 'store'])
    ->middleware('throttle:contact')
    ->name('contacto.store');

Route::view('/calculadora-ahorro','pages.calculadora-ahorro')
    ->name('savings.index');

Route::get('/agenda',[VisitController::class,'create'])->name('visits.create');
Route::post('/agenda',[VisitController::class,'store'])->middleware('throttle:visit')->name('visits.store');

Route::view('/privacidad', 'pages.privacidad')
    ->name('privacidad');

Route::view('/terminos', 'pages.terminos')
    ->name('terminos');

Route::get('/carrito',[CartController::class,'index'])->name('cart.index');
Route::post('/carrito',[CartController::class,'store'])->middleware('throttle:cart')->name('cart.store');
Route::patch('/carrito/{item}',[CartController::class,'update'])->middleware('throttle:cart')->name('cart.update');
Route::delete('/carrito/{item}',[CartController::class,'destroy'])->middleware('throttle:cart')->name('cart.destroy');

Route::middleware('guest')->group(
    function (): void {
        Route::get(
            '/ingresar',
            [
                AuthenticatedSessionController::class,
                'create',
            ],
        )->name('login');

        Route::post(
            '/ingresar',
            [
                AuthenticatedSessionController::class,
                'store',
            ],
        )
            ->middleware('throttle:login')
            ->name('login.store');
        Route::get('/clave/olvidada',[PasswordResetLinkController::class,'create'])->name('password.request');
        Route::post('/clave/olvidada',[PasswordResetLinkController::class,'store'])->middleware('throttle:password-reset')->name('password.email');
        Route::get('/clave/restablecer/{token}',[NewPasswordController::class,'create'])->name('password.reset');
        Route::post('/clave/restablecer',[NewPasswordController::class,'store'])->middleware('throttle:password-reset')->name('password.update');

        Route::get(
            '/registro',
            [
                RegisteredUserController::class,
                'create',
            ],
        )->name('register');

        Route::post(
            '/registro',
            [
                RegisteredUserController::class,
                'store',
            ],
        )
            ->middleware('throttle:register')
            ->name('register.store');
    },
);

Route::middleware('auth')->group(
    function (): void {
        Route::get(
            '/email/verificar',
            [
                EmailVerificationController::class,
                'notice',
            ],
        )->name('verification.notice');

        Route::get(
            '/email/verificar/{id}/{hash}',
            [
                EmailVerificationController::class,
                'verify',
            ],
        )
            ->middleware([
                'signed',
                'throttle:verification',
            ])
            ->name('verification.verify');

        Route::post(
            '/email/verificacion-notificacion',
            [
                EmailVerificationController::class,
                'send',
            ],
        )
            ->middleware('throttle:verification')
            ->name('verification.send');

        Route::get('/cuenta',[AccountController::class,'show'])
            ->middleware('verified')
            ->name('account.dashboard');

        Route::middleware('verified')->group(function (): void {
            Route::patch('/cuenta/datos',[AccountController::class,'update'])->middleware('throttle:account')->name('account.update');
            Route::patch('/cuenta/clave',[AccountController::class,'password'])->middleware('throttle:account')->name('account.password');
            Route::post('/cuenta/direcciones',[AccountController::class,'storeAddress'])->middleware('throttle:account')->name('account.addresses.store');
            Route::patch('/cuenta/direcciones/{address}',[AccountController::class,'updateAddress'])->middleware('throttle:account')->name('account.addresses.update');
            Route::delete('/cuenta/direcciones/{address}',[AccountController::class,'destroyAddress'])->middleware('throttle:account')->name('account.addresses.destroy');
            Route::post('/cuenta/privacidad/solicitudes',[PrivacyController::class,'store'])->middleware('throttle:privacy')->name('account.privacy.store');
            Route::post('/cuenta/privacidad/exportar',[PrivacyController::class,'export'])->middleware('throttle:privacy')->name('account.privacy.export');
            Route::get('/comprar', [CheckoutController::class, 'index'])->name('checkout.index');
            Route::post('/comprar', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');
            Route::get('/pedidos/{order}', [CheckoutController::class, 'show'])->name('orders.show');
            Route::get('/pedidos/{order}/comprobante',[CheckoutController::class,'receipt'])->name('orders.receipt');
            Route::post('/pedidos/{order}/cancelar', [CheckoutController::class, 'cancel'])
                ->middleware('throttle:order-cancel')->name('orders.cancel');
        });

        Route::post(
            '/salir',
            [
                AuthenticatedSessionController::class,
                'destroy',
            ],
        )->name('logout');
    },
);
