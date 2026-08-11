ALTER TABLE crm_visita
    ADD COLUMN IF NOT EXISTS StorefrontRequestUuid CHAR(36) NULL AFTER IdVisita,
    ADD COLUMN IF NOT EXISTS ClienteCorreo VARCHAR(190) NULL AFTER ClienteTelefono,
    ADD UNIQUE INDEX IF NOT EXISTS uq_crm_visita_storefront_request (StorefrontRequestUuid);

