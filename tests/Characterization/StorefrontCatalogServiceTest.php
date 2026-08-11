<?php

declare(strict_types=1);

use Lteco\Application\Ecommerce\CatalogDataSource;
use Lteco\Application\Ecommerce\CatalogService;
use Lteco\Application\Ecommerce\CommercialTermsService;
use Lteco\Application\Ecommerce\PublicMediaUrl;

final class StorefrontCatalogServiceTest
{
    public static function run(): void
    {
        $source = new class implements CatalogDataSource {
            public function catalogSnapshot(): array { return ['units' => [
                ['IdVehiculo'=>'V1','IdProducto'=>1,'Modelo'=>'Q8-500W','CapacidadBateriaAh'=>20,'Color'=>' Beige ','Slug'=>'q8','DescripcionWeb'=>'Moto','PrecioVenta'=>'67000','Moneda'=>'uyu','TasaIVA'=>'22','OrdenWeb'=>20,'Estado'=>'Disponible','Stock'=>1],
                ['IdVehiculo'=>'V2','IdProducto'=>2,'Modelo'=>' q8-500w ','CapacidadBateriaAh'=>20,'Color'=>'beige','Slug'=>'q8-2','DescripcionWeb'=>'Moto 2','PrecioVenta'=>'67000.00','Moneda'=>'UYU','TasaIVA'=>'22','OrdenWeb'=>10,'Estado'=>'Disponible','Stock'=>1],
                ['IdVehiculo'=>'V3','IdProducto'=>3,'Modelo'=>'SL-500W','CapacidadBateriaAh'=>20,'Color'=>'Rosa','Slug'=>'sl','DescripcionWeb'=>'SL','PrecioVenta'=>'65000','Moneda'=>'UYU','TasaIVA'=>'22','OrdenWeb'=>30,'Estado'=>'Sin stock','Stock'=>0],
            ], 'images' => [
                ['IdVehiculo'=>'V1','RutaImagen'=>'uploads/vehiculos/q8.webp','EsPrincipal'=>1,'OrdenImagen'=>1],
                ['IdVehiculo'=>'V2','RutaImagen'=>'uploads/vehiculos/q8-lateral.webp','EsPrincipal'=>0,'OrdenImagen'=>2],
                ['IdVehiculo'=>'V3','RutaImagen'=>'uploads/vehiculos/sl.jpg','EsPrincipal'=>1,'OrdenImagen'=>1],
            ]]; }
        };
        $result = (new CatalogService($source, new PublicMediaUrl('https://media.example.test/lteco-panel')))
            ->catalog('2026-07-25T23:00:00Z');
        Assert::same('Storefront catalog', 'agrupa stock equivalente', 2, $result['data'][0]['availability']['quantity']);
        Assert::same('Storefront catalog', 'precio como decimal', '67000.00', $result['data'][0]['price']['gross']);
        Assert::same('Storefront catalog', 'URL de medio pública', 'https://media.example.test/lteco-panel/uploads/vehiculos/q8.webp', $result['data'][0]['gallery'][0]['url']);
        Assert::same('Storefront catalog', 'combina galerías de unidades equivalentes', 2, count($result['data'][0]['gallery']));
        Assert::same('Storefront catalog', 'respeta el menor orden web del grupo', 'Q8-500W', $result['data'][0]['model']);
        Assert::same('Storefront catalog', 'cuenta variantes publicadas aunque estén agotadas', 2, $result['meta']['count']);
        Assert::same('Storefront catalog', 'variante agotada queda publicada con cantidad cero', 0, $result['data'][1]['availability']['quantity']);

        $terms = (new CommercialTermsService())->terms(
            ['TasaIVA'=>22.0,'DescuentoContado'=>5.0],
            ['Visa'=>[6], 'Mastercard'=>[18,6,18]],
            '2026-07-25T23:00:00Z'
        );
        Assert::same('Storefront terms', 'descuento decimal', '5.00', $terms['data']['cash_discount_pct']);
        Assert::same('Storefront terms', 'Mastercard ordenada y única', [6,18], $terms['data']['cards'][0]['installments']);
    }
}
