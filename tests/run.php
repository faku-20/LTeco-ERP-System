<?php

declare(strict_types=1);

/**
 * Runner de tests sin dependencias.
 *
 * Uso:  php tests/run.php
 * Sale con código 1 si alguna aserción falla (apto para CI/pre-commit).
 *
 * No toca la base de datos, no incluye páginas del panel y no carga $pdo.
 * Solo ejercita los cálculos puros congelados en tests/Characterization/.
 */

// Autoloader PSR-4 mínimo para Lteco\ -> src/. No incluye shared/app_config.php
// a propósito: los tests no deben levantar la app (headers, $pdo, configureRuntime).
spl_autoload_register(static function (string $clase): void {
    $prefijo = 'Lteco\\';
    if (strncmp($clase, $prefijo, strlen($prefijo)) !== 0) {
        return;
    }
    $relativo = substr($clase, strlen($prefijo));
    $ruta = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relativo) . '.php';
    if (is_file($ruta)) {
        require_once $ruta;
    }
});

require __DIR__ . '/Support/Assert.php';
require __DIR__ . '/Characterization/ReferenciaCalculoVenta.php';
require __DIR__ . '/Characterization/VentaCalculoTest.php';
require __DIR__ . '/Characterization/VentaSupportTest.php';
require __DIR__ . '/Characterization/VentaDomainSupportTest.php';
require __DIR__ . '/Characterization/VentaGuardarWiringTest.php';
require __DIR__ . '/Characterization/VentaDistribuidorWiringTest.php';
require __DIR__ . '/Characterization/VentaPersistenceDataTest.php';
require __DIR__ . '/Characterization/ConfiguracionComercialVentaTest.php';
require __DIR__ . '/Characterization/VentaListadoTest.php';
require __DIR__ . '/Characterization/VentaListadoWiringTest.php';
require __DIR__ . '/Characterization/VentaConsultaViewTest.php';
require __DIR__ . '/Characterization/VehiculoEstadoWiringTest.php';
require __DIR__ . '/Characterization/PostventaServiceWiringTest.php';
require __DIR__ . '/Characterization/PostventaConsultaWiringTest.php';
require __DIR__ . '/Characterization/PostventaIntervencionWiringTest.php';
require __DIR__ . '/Characterization/VehiculoCrearWiringTest.php';
require __DIR__ . '/Characterization/VehiculoEditarWiringTest.php';
require __DIR__ . '/Characterization/VehiculoListadoWiringTest.php';
require __DIR__ . '/Characterization/VehiculoConsultaWiringTest.php';
require __DIR__ . '/Characterization/DistribuidorStockWiringTest.php';
require __DIR__ . '/Characterization/DistribuidorPedidoWiringTest.php';
require __DIR__ . '/Characterization/DistribuidorPedidoAdminWiringTest.php';
require __DIR__ . '/Characterization/DistribuidorVentaCalculoTest.php';
require __DIR__ . '/Characterization/DistribuidorConsultaWiringTest.php';
require __DIR__ . '/Characterization/DistribuidorReporteWiringTest.php';
require __DIR__ . '/Characterization/DistribuidorCuentaWiringTest.php';
require __DIR__ . '/Characterization/DistribuidorCrudWiringTest.php';
require __DIR__ . '/Characterization/DistribuidorCommonLimpiezaTest.php';
require __DIR__ . '/Characterization/RepuestoCrudWiringTest.php';
require __DIR__ . '/Characterization/RepuestoConsultaWiringTest.php';
require __DIR__ . '/Characterization/ClienteCrudWiringTest.php';
require __DIR__ . '/Characterization/ClienteConsultaWiringTest.php';
require __DIR__ . '/Characterization/ClienteExportWiringTest.php';
require __DIR__ . '/Characterization/GastoConsultaWiringTest.php';
require __DIR__ . '/Characterization/GastoCrudWiringTest.php';
require __DIR__ . '/Characterization/ImportacionConsultaWiringTest.php';
require __DIR__ . '/Characterization/ImportacionCrudWiringTest.php';
require __DIR__ . '/Characterization/BalanceCalculoTest.php';
require __DIR__ . '/Characterization/BalanceWiringTest.php';
require __DIR__ . '/Characterization/UsuarioConsultaWiringTest.php';
require __DIR__ . '/Characterization/UsuarioCrudWiringTest.php';
require __DIR__ . '/Characterization/UsuarioMfaWiringTest.php';
require __DIR__ . '/Characterization/ConfiguracionWiringTest.php';
require __DIR__ . '/Characterization/MantenimientoBackupComandoTest.php';
require __DIR__ . '/Characterization/MantenimientoWiringTest.php';
require __DIR__ . '/Characterization/AuditoriaConsultaWiringTest.php';
require __DIR__ . '/Characterization/BusquedaWiringTest.php';
require __DIR__ . '/Characterization/DashboardCalculoTest.php';
require __DIR__ . '/Characterization/DashboardWiringTest.php';
require __DIR__ . '/Characterization/OlaHInfraWiringTest.php';
require __DIR__ . '/Characterization/InitialAutoReplyBuilderTest.php';
require __DIR__ . '/Characterization/WhatsappTestResetWiringTest.php';
require __DIR__ . '/Characterization/MetaWebhookSignatureTest.php';
require __DIR__ . '/Characterization/WhatsappQuotedModelTest.php';
require __DIR__ . '/Characterization/VisitIntentDetectorTest.php';
require __DIR__ . '/Characterization/AgendaAiWiringTest.php';
require __DIR__ . '/Characterization/VisitAlertNotificationTest.php';
require __DIR__ . '/Characterization/WebPushNotificationTest.php';
require __DIR__ . '/Characterization/TelegramNotificationTest.php';
require __DIR__ . '/Characterization/HubNavigationTest.php';
require __DIR__ . '/Characterization/SubmitActionPreservationTest.php';
require __DIR__ . '/Characterization/N8nWhatsappWiringTest.php';
require __DIR__ . '/Characterization/IaActionsContactsExportTest.php';
require __DIR__ . '/Characterization/MobileOperationalTablesTest.php';
require __DIR__ . '/Characterization/PanelHistoryBackTest.php';
require __DIR__ . '/Characterization/EcommerceWiringTest.php';
require __DIR__ . '/Characterization/PublicWebContentTest.php';
require __DIR__ . '/Characterization/StorefrontApiAuthenticationTest.php';
require __DIR__ . '/Characterization/StorefrontCatalogServiceTest.php';
require __DIR__ . '/Characterization/StorefrontReservationServiceTest.php';
require __DIR__ . '/Characterization/StorefrontContainerPermissionsTest.php';
require __DIR__ . '/Characterization/EcommerceTransactionalNotificationTest.php';
require __DIR__ . '/Characterization/StorefrontVisitBridgeTest.php';
require __DIR__ . '/Characterization/B0ContainmentTest.php';
require __DIR__ . '/Characterization/B1TestingSafetyTest.php';
require __DIR__ . '/Characterization/B2BCommercialRulesWiringTest.php';
require __DIR__ . '/Characterization/B2CLegacyEcommerceRetirementTest.php';
require __DIR__ . '/Characterization/B3InventoryIntegrityWiringTest.php';
require __DIR__ . '/Characterization/B4SecurityHardeningTest.php';
require __DIR__ . '/Characterization/B5DbLifecycleWiringTest.php';
require __DIR__ . '/Characterization/B6OperationalHealthTest.php';

VentaCalculoTest::run();
VentaSupportTest::run();
VentaDomainSupportTest::run();
VentaGuardarWiringTest::run();
VentaDistribuidorWiringTest::run();
VentaPersistenceDataTest::run();
ConfiguracionComercialVentaTest::run();
VentaListadoTest::run();
VentaListadoWiringTest::run();
VentaConsultaViewTest::run();
VehiculoEstadoWiringTest::run();
PostventaServiceWiringTest::run();
PostventaConsultaWiringTest::run();
PostventaIntervencionWiringTest::run();
VehiculoCrearWiringTest::run();
VehiculoEditarWiringTest::run();
VehiculoListadoWiringTest::run();
VehiculoConsultaWiringTest::run();
DistribuidorStockWiringTest::run();
DistribuidorPedidoWiringTest::run();
DistribuidorPedidoAdminWiringTest::run();
DistribuidorVentaCalculoTest::run();
DistribuidorConsultaWiringTest::run();
DistribuidorReporteWiringTest::run();
DistribuidorCuentaWiringTest::run();
DistribuidorCrudWiringTest::run();
DistribuidorCommonLimpiezaTest::run();
RepuestoCrudWiringTest::run();
RepuestoConsultaWiringTest::run();
ClienteCrudWiringTest::run();
ClienteConsultaWiringTest::run();
ClienteExportWiringTest::run();
GastoConsultaWiringTest::run();
GastoCrudWiringTest::run();
ImportacionConsultaWiringTest::run();
ImportacionCrudWiringTest::run();
BalanceCalculoTest::run();
BalanceWiringTest::run();
UsuarioConsultaWiringTest::run();
UsuarioCrudWiringTest::run();
UsuarioMfaWiringTest::run();
ConfiguracionWiringTest::run();
MantenimientoBackupComandoTest::run();
MantenimientoWiringTest::run();
AuditoriaConsultaWiringTest::run();
BusquedaWiringTest::run();
DashboardCalculoTest::run();
DashboardWiringTest::run();
OlaHInfraWiringTest::run();
InitialAutoReplyBuilderTest::run();
WhatsappTestResetWiringTest::run();
MetaWebhookSignatureTest::run();
WhatsappQuotedModelTest::run();
VisitIntentDetectorTest::run();
AgendaAiWiringTest::run();
VisitAlertNotificationTest::run();
WebPushNotificationTest::run();
TelegramNotificationTest::run();
HubNavigationTest::run();
SubmitActionPreservationTest::run();
N8nWhatsappWiringTest::run();
IaActionsContactsExportTest::run();
MobileOperationalTablesTest::run();
PanelHistoryBackTest::run();
EcommerceWiringTest::run();
PublicWebContentTest::run();
StorefrontApiAuthenticationTest::run();
StorefrontCatalogServiceTest::run();
StorefrontReservationServiceTest::run();
StorefrontContainerPermissionsTest::run();
EcommerceTransactionalNotificationTest::run();
StorefrontVisitBridgeTest::run();
B0ContainmentTest::run();
B1TestingSafetyTest::run();
B2BCommercialRulesWiringTest::run();
B2CLegacyEcommerceRetirementTest::run();
B3InventoryIntegrityWiringTest::run();
B4SecurityHardeningTest::run();
B5DbLifecycleWiringTest::run();
B6OperationalHealthTest::run();

echo "\n";
if (Assert::$failed === 0) {
    echo sprintf("OK — %d aserciones pasaron.\n", Assert::$passed);
    exit(0);
}

echo sprintf("FALLÓ — %d ok, %d fallaron:\n", Assert::$passed, Assert::$failed);
foreach (Assert::$failures as $f) {
    echo ' - ' . $f . "\n";
}
exit(1);
