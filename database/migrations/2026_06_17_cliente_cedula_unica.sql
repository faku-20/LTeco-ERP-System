-- Unicidad de cédula de cliente.
-- La cédula se guarda como solo dígitos (sin puntos ni guion). Dos clientes no
-- pueden compartir cédula. Las cédulas vacías quedan en NULL (permitidas para
-- clientes Empresa/RUT y datos legacy; el índice único admite múltiples NULL).
--
-- IMPORTANTE: antes de correr esto, resolvé cualquier cédula duplicada existente
-- (no nula). Con duplicados, la creación del índice único falla.

-- Normaliza a solo dígitos y pasa vacíos a NULL (idempotente).
UPDATE cliente
SET Cedula = NULLIF(REGEXP_REPLACE(Cedula, '[^0-9]', ''), '')
WHERE Cedula IS NOT NULL;

-- Índice único (idempotente en MariaDB).
ALTER TABLE cliente
  ADD UNIQUE INDEX IF NOT EXISTS uq_cliente_cedula (Cedula);
