<?php

declare(strict_types=1);

final class PanelHistoryBackTest
{
    public static function run(): void
    {
        $base = dirname(__DIR__, 2);
        $header = (string)file_get_contents($base.'/src/Presentation/Panel/View/Includes/header.php');
        $home = (string)file_get_contents($base.'/lteco-panel/inicio.php');
        $script = (string)file_get_contents($base.'/lteco-panel/assets/js/panel-history-back.js');
        $css = (string)file_get_contents($base.'/lteco-panel/assets/css/lteco.css');

        Assert::isTrue('Botón atrás', 'layout carga control global', str_contains($header, 'panel-history-back.js'));
        Assert::isTrue('Botón atrás', 'inicio carga control global', str_contains($home, 'panel-history-back.js'));
        Assert::isTrue('Botón atrás', 'layout define destino seguro', str_contains($header, 'data-panel-back-fallback'));
        Assert::isTrue('Botón atrás', 'usa historial del panel', str_contains($script, 'window.history.back()'));
        Assert::isTrue('Botón atrás', 'evita volver fuera del panel', str_contains($script, "previous.pathname.indexOf('/lteco-panel/')"));
        Assert::isTrue('Botón atrás', 'tiene etiqueta accesible', str_contains($script, 'Volver a la pantalla anterior'));
        Assert::isTrue('Botón atrás', 'tiene estilos responsive', str_contains($css, '.panel-history-back'));
    }
}
