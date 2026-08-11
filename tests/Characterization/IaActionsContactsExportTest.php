<?php

declare(strict_types=1);

final class IaActionsContactsExportTest
{
    public static function run(): void
    {
        $base = dirname(__DIR__, 2);
        $page = (string)file_get_contents($base.'/lteco-panel/ia/acciones.php');
        $export = (string)file_get_contents($base.'/lteco-panel/ia/acciones_exportar.php');
        $support = (string)file_get_contents($base.'/src/Presentation/Panel/Support/ai.php');

        Assert::isTrue('Exportar contactos IA', 'botón conserva filtros', str_contains($page, "http_build_query(['estado' => \$estado, 'tipo' => \$tipo])"));
        Assert::isTrue('Exportar contactos IA', 'endpoint exige administrador', str_contains($export, 'requiereAdmin()'));
        Assert::isTrue('Exportar contactos IA', 'CSV usa protección contra fórmulas', str_contains($export, 'csvFilaSegura'));
        Assert::isFalse('Exportar contactos IA', 'CSV no incluye datos adicionales', str_contains($export, "['Nombre', 'Telefono'"));
        Assert::isTrue('Exportar contactos IA', 'CSV contiene solo el teléfono', str_contains($export, "csvFilaSegura([\$row['ClienteTelefono'] ?? ''])"));
        Assert::isTrue('Exportar contactos IA', 'exportación queda auditada', str_contains($export, 'IA_EXPORTAR_CONTACTOS'));
        Assert::isTrue('Exportar contactos IA', 'teléfonos se normalizan', str_contains($support, 'whatsappFormatearTelefono'));
        Assert::isTrue('Exportar contactos IA', 'teléfonos se deduplican', str_contains($support, 'isset($contacts[$phone])'));
    }
}
