<?php

declare(strict_types=1);

final class PublicWebContentTest
{
    public static function run(): void
    {
        $root = dirname(__DIR__, 2);
        require_once $root . '/public-web/includes/contenido-modelos.php';

        $q8 = contenidoModeloPublico('Q8-500W');
        $sl = contenidoModeloPublico('SL-500W');
        $contacto = (string) file_get_contents($root . '/public-web/contacto.php');
        $portada = (string) file_get_contents($root . '/public-web/index.php');
        $header = (string) file_get_contents($root . '/public-web/includes/header.php');
        $styles = (string) file_get_contents($root . '/public-web/assets/css/styles.css');

        Assert::isTrue('Public web', 'Q8 conserva 12Ah', str_contains($q8['especificaciones']['Batería'], '12Ah'));
        Assert::isTrue('Public web', 'Q8 conserva 20Ah', str_contains($q8['especificaciones']['Batería'], '20Ah'));
        Assert::isFalse('Public web', 'Q8 no reintroduce 15Ah', str_contains(json_encode($q8), '15Ah'));
        Assert::isTrue('Public web', 'SL informa canasto opcional', str_contains($sl['especificaciones']['Accesorio opcional'], 'Canasto delantero'));
        Assert::isFalse('Public web', 'contacto admite ecommerce', str_contains($contacto, 'No vendemos desde la web'));
        Assert::isTrue('Public web', 'portada usa WhatsApp real', str_contains($portada, '092 000 086'));
        Assert::isTrue('Public web', 'cómo comprar abre términos', str_contains($header, "publicBaseUrl('terminos.php')"));
        Assert::isFalse('Public web', 'no referencia hero inexistente', str_contains($styles, "url('../img/hero.png')"));
    }
}
