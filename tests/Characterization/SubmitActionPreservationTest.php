<?php

declare(strict_types=1);

final class SubmitActionPreservationTest
{
    public static function run(): void
    {
        $base = dirname(__DIR__, 2);
        $script = (string)file_get_contents($base.'/lteco-panel/assets/js/ui-v4-alerts.js');
        $alerts = (string)file_get_contents($base.'/lteco-panel/notificaciones/index.php');
        $alertEndpoint = (string)file_get_contents($base.'/lteco-panel/notificaciones/estado.php');
        $actions = (string)file_get_contents($base.'/lteco-panel/ia/acciones.php');
        $actionEndpoint = (string)file_get_contents($base.'/lteco-panel/ia/accion_estado.php');
        $aiSupport = (string)file_get_contents($base.'/src/Presentation/Panel/Support/ai.php');

        Assert::isTrue('Submit con múltiples acciones', 'usa el submitter nativo con fallback', str_contains($script, 'event.submitter || form.__ltecoSubmitter'));
        Assert::isTrue('Submit con múltiples acciones', 'preserva name y value antes de bloquear botones', (bool)preg_match('/preserveSubmitterValue\(form\);\s*setSubmittingState\(form\);/', $script));
        Assert::isTrue('Submit con múltiples acciones', 'guarda el valor en un campo exitoso', str_contains($script, "data-lteco-submitter-value='1'"));
        Assert::isTrue('Submit con múltiples acciones', 'alertas envían estado por botón', str_contains($alerts, 'name="estado" value="leida"'));
        Assert::isTrue('Submit con múltiples acciones', 'endpoint de alertas recibe estado', str_contains($alertEndpoint, "\$_POST['estado']"));
        Assert::isTrue('Submit con múltiples acciones', 'acciones IA envían la acción por botón', str_contains($actions, 'name="accion" value="confirmar"'));
        Assert::isTrue('Submit con múltiples acciones', 'endpoint IA valida las tres acciones', str_contains($actionEndpoint, "['confirmar', 'rechazar', 'ejecutar']"));
        Assert::isTrue('Submit con múltiples acciones', 'filtro vacío consulta todos los estados', str_contains($aiSupport, "if (\$estado !== '')"));
    }
}
