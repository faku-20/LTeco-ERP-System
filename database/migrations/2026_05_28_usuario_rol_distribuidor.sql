-- Agrega rol Distribuidor a la tabla usuario y FK a distribuidor.
-- Ejecutar sobre la base lteco_db.

SET NAMES utf8mb4;

ALTER TABLE usuario
    MODIFY COLUMN Rol ENUM('Superadmin','Administrador','Vendedor','Distribuidor') NOT NULL DEFAULT 'Vendedor';

ALTER TABLE usuario
    ADD COLUMN IdDistribuidor INT(11) NULL DEFAULT NULL AFTER Rol;

ALTER TABLE usuario
    ADD CONSTRAINT fk_usuario_distribuidor
        FOREIGN KEY (IdDistribuidor) REFERENCES distribuidor(IdDistribuidor)
        ON DELETE SET NULL ON UPDATE CASCADE;
