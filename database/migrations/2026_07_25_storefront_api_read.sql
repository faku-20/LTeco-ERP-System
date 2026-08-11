-- Seguridad de la API privada panel <-> storefront.
-- La clave primaria compuesta impide reutilizar un nonce para la misma credencial.

CREATE TABLE IF NOT EXISTS storefront_api_nonce (
    KeyId VARCHAR(80) NOT NULL,
    Nonce VARCHAR(128) NOT NULL,
    CreadoEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ExpiraEn DATETIME NOT NULL,
    PRIMARY KEY (KeyId, Nonce),
    KEY idx_storefront_api_nonce_expira (ExpiraEn),
    KEY idx_storefront_api_nonce_rate (KeyId, CreadoEn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ventana compartida y bloqueada por fila: evita que solicitudes concurrentes
-- superen el límite mediante un count/insert no atómico.
CREATE TABLE IF NOT EXISTS storefront_api_rate_window (
    KeyId VARCHAR(80) NOT NULL,
    WindowStart DATETIME NOT NULL,
    RequestCount INT UNSIGNED NOT NULL DEFAULT 0,
    ExpiraEn DATETIME NOT NULL,
    PRIMARY KEY (KeyId, WindowStart),
    KEY idx_storefront_api_rate_window_expira (ExpiraEn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE configuracion
    ADD COLUMN IF NOT EXISTS StorefrontUpdatedAt TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

CREATE TABLE IF NOT EXISTS storefront_api_idempotency (
    IdempotencyKey CHAR(36) NOT NULL,
    Operation VARCHAR(80) NOT NULL,
    RequestHash CHAR(64) NOT NULL,
    HttpStatus SMALLINT UNSIGNED NULL,
    ResponseJson JSON NULL,
    ExpiraEn DATETIME NOT NULL,
    CreadoEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (IdempotencyKey),
    KEY idx_storefront_api_idempotency_expira (ExpiraEn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS storefront_reservation (
    ReservationId CHAR(36) NOT NULL,
    OrderUuid CHAR(36) NOT NULL,
    PaymentMethod ENUM('cash','card') NOT NULL,
    Estado ENUM('active','released','expired','consumed') NOT NULL DEFAULT 'active',
    Moneda CHAR(3) NOT NULL,
    Subtotal DECIMAL(15,2) NOT NULL,
    Descuento DECIMAL(15,2) NOT NULL DEFAULT 0,
    Total DECIMAL(15,2) NOT NULL,
    ExpiraEn DATETIME NOT NULL,
    LiberadaEn DATETIME NULL,
    CreadoEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ActualizadoEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ReservationId),
    UNIQUE KEY uq_storefront_reservation_order (OrderUuid),
    KEY idx_storefront_reservation_expiry (Estado, ExpiraEn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS storefront_reservation_item (
    ReservationId CHAR(36) NOT NULL,
    IdVehiculo VARCHAR(10) NOT NULL,
    IdProducto INT NOT NULL,
    VariantId CHAR(64) NOT NULL,
    Modelo VARCHAR(100) NOT NULL,
    CapacidadBateriaAh SMALLINT UNSIGNED NULL,
    Color VARCHAR(80) NOT NULL DEFAULT '',
    PrecioBruto DECIMAL(15,2) NOT NULL,
    TasaIVA DECIMAL(7,4) NOT NULL,
    MostrarEnWebAnterior TINYINT(1) NOT NULL,
    DestacadoWebAnterior TINYINT(1) NOT NULL,
    PRIMARY KEY (ReservationId, IdVehiculo),
    KEY idx_storefront_reserved_vehicle (IdVehiculo),
    KEY idx_storefront_reservation_item_variant (VariantId),
    CONSTRAINT fk_storefront_reservation_item_reservation FOREIGN KEY (ReservationId) REFERENCES storefront_reservation (ReservationId) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_storefront_reservation_item_vehicle FOREIGN KEY (IdVehiculo) REFERENCES vehiculo (IdVehiculo) ON UPDATE CASCADE,
    CONSTRAINT fk_storefront_reservation_item_product FOREIGN KEY (IdProducto) REFERENCES producto (IdProducto) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE storefront_reservation_item
    DROP INDEX IF EXISTS uq_storefront_reserved_vehicle,
    ADD INDEX IF NOT EXISTS idx_storefront_reserved_vehicle (IdVehiculo);
