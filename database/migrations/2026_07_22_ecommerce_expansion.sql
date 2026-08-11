CREATE TABLE IF NOT EXISTS ecommerce_metrica_diaria (
  Fecha DATE NOT NULL, Evento VARCHAR(50) NOT NULL, Cantidad INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (Fecha,Evento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ecommerce_limite_acceso (
  ClaveHash CHAR(64) NOT NULL, VentanaInicio DATETIME NOT NULL, Intentos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (ClaveHash), KEY idx_ecommerce_limite_ventana (VentanaInicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
