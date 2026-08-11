# LTECOBIKE B5 - DB lifecycle, backup, restore y deploy

## Migraciones

El panel usa `schema_migrations` como ledger controlado por `scripts/migrate.sh`.

Comandos:

```bash
./scripts/migrate.sh --list --allow-production
./scripts/migrate.sh --dry-run --allow-production
./scripts/migrate.sh --baseline-existing --allow-production
./scripts/migrate.sh --allow-production --allow-destructive
```

En producción `--allow-production` es obligatorio. Las migraciones con `DROP`,
`TRUNCATE`, `DELETE FROM`, `MODIFY COLUMN`, `CHANGE COLUMN` o `RENAME TABLE`
requieren además `--allow-destructive`.

Una DB vacía aplica primero `database/baseline/2026_08_05_current_schema.sql`.
Una DB existente debe registrarse con `--baseline-existing` para no reejecutar
schema histórico.

## Backup

Backup automático/manual:

```bash
/opt/ltecobike/scripts/backup_db.sh
```

El script no ejecuta `.env` como shell. Lee configuración mediante
`shared/app_config.php` y admite valores con espacios o caracteres especiales
razonables.

Salida esperada:

- dump `.sql.gz`;
- checksum `.sha256`;
- `backup-status.json`;
- log de cron en `/opt/backups/ltecobike/backup-cron.log`.

Retención: 14 días. La limpieza solo borra nombres que cumplen
`lteco_db_YYYY-MM-DD_HH-MM-SS.sql.gz` dentro del directorio de backups.

Cron instalado:

```cron
17 2 * * * /opt/ltecobike/scripts/backup_db.sh >> /opt/backups/ltecobike/backup-cron.log 2>&1
```

## Restore

Restore productivo desde web está deshabilitado. La restauración productiva debe
ser una operación administrativa/CLI, con backup previo verificado y ventana
controlada.

Validaciones mínimas antes de restaurar:

```bash
cd /opt/backups/ltecobike
gzip -t lteco_db_YYYY-MM-DD_HH-MM-SS.sql.gz
sha256sum -c lteco_db_YYYY-MM-DD_HH-MM-SS.sql.gz.sha256
```

Drill aislado:

1. Usar DB test dedicada o DB temporal local.
2. Vaciar schema aislado.
3. Importar el backup.
4. Validar tablas esenciales, `schema_migrations`, stock negativo, ventas sin
   detalle, vendidos sin venta y `inventory_reconcile.php`.
5. Resetear DB aislada inmediatamente.

## Deploy runbook

1. `git status --short`.
2. `./scripts/preflight-panel.sh`.
3. `./scripts/backup_db.sh` y verificar `backup-status.json`.
4. Activar mantenimiento si el cambio no es expand-only.
5. `./scripts/migrate.sh --list --allow-production`.
6. Aplicar migraciones con flags explícitos.
7. Deploy panel.
8. Deploy workers.
9. Deploy Storefront.
10. Limpiar caches si corresponde.
11. Smoke tests read-only de panel/web/storefront.
12. `./scripts/test-panel-fast.sh`, `./scripts/test-panel-critical.sh`,
    `./scripts/test-storefront.sh`, `./scripts/test-critical.sh`.
13. Si falla sin schema incompatible: volver release anterior.
14. Si falla con schema incompatible: aplicar corrective migration o restore,
    no prometer downgrade automático.

## Expand / contract

Para cambios incompatibles:

1. expand schema compatible;
2. deploy código compatible;
3. backfill/migración;
4. contract posterior en otro deploy.

## Least privilege SQL pendiente

Requiere usuario administrativo MariaDB. No guardar password root/admin en Git.

Snapshot:

```sql
SHOW GRANTS FOR 'lteco_user'@'%';
```

Aplicación propuesta:

```sql
REVOKE ALL PRIVILEGES ON `ltecobike`.* FROM 'lteco_user'@'%';
REVOKE ALL PRIVILEGES ON `lteco_db`.* FROM 'lteco_user'@'%';
REVOKE ALL PRIVILEGES ON `lteco_db_poo`.* FROM 'lteco_user'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON `lteco_db_poo`.* TO 'lteco_user'@'%';
FLUSH PRIVILEGES;
```

Rollback:

```sql
GRANT ALL PRIVILEGES ON `ltecobike`.* TO 'lteco_user'@'%';
GRANT ALL PRIVILEGES ON `lteco_db`.* TO 'lteco_user'@'%';
GRANT ALL PRIVILEGES ON `lteco_db_poo`.* TO 'lteco_user'@'%';
FLUSH PRIVILEGES;
```

Validación posterior:

```sql
SHOW GRANTS FOR 'lteco_user'@'%';
```

Y ejecutar:

```bash
./scripts/preflight-panel.sh
./scripts/test-panel-fast.sh
./scripts/test-panel-critical.sh
./scripts/test-storefront.sh
./scripts/test-critical.sh
```
