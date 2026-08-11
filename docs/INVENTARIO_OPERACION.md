# Inventario operativo

## Modelo de ownership

El stock central de `producto.Stock` representa unidades bajo control directo de Lteco.

El stock de `distribuidor_stock.Cantidad` representa unidades ya transferidas fisicamente a un distribuidor. La asignacion de stock a distribuidor consume `producto.Stock`; la venta posterior del distribuidor consume solamente `distribuidor_stock`.

## Repuestos

Flujo esperado:

1. Asignacion a distribuidor: `producto.Stock` baja y `distribuidor_stock.Cantidad` sube.
2. Venta del distribuidor: baja solo `distribuidor_stock.Cantidad`.
3. Anulacion de venta del distribuidor: sube solo `distribuidor_stock.Cantidad`.
4. Venta central: baja `producto.Stock`.
5. Anulacion de venta central: sube `producto.Stock`.

## Vehiculos

El estado `Vendido` no debe asignarse manualmente. Solo puede originarse desde una venta registrada.

Si un vehiculo asignado a distribuidor se vende y luego se anula, la unidad vuelve al stock del distribuidor. No vuelve al stock central. El producto queda sin stock central y no publicado.

## Ajustes manuales de stock

La edicion manual de repuestos exige motivo cuando cambia la cantidad. La auditoria debe registrar stock anterior, stock nuevo, delta y motivo.

## Reconciliacion read-only

El panel incluye el comando:

```bash
php lteco-panel/scripts/inventory_reconcile.php
```

El comando solo ejecuta consultas `SELECT`. Devuelve `OK`, `WARN` o `ERROR` por control y termina con codigo `1` si encuentra inconsistencias severas.
