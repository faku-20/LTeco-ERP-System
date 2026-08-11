-- Usuario interno que cobra comision cuando vende un distribuidor.

ALTER TABLE configuracion
    ADD COLUMN IF NOT EXISTS UsuarioComisionDistribuidorId INT NULL AFTER ComisionVendedor;

CREATE INDEX IF NOT EXISTS idx_configuracion_usuario_comision_distribuidor
    ON configuracion (UsuarioComisionDistribuidorId);
