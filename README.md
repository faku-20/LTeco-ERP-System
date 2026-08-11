# LTeco ERP System

LTeco ERP System es un sistema ERP genérico para gestión comercial, ventas, stock, postventa, distribuidores, ecommerce e integraciones operativas.

La marca del producto es LTeco ERP System. La marca visible de cada instalación se configura desde el panel en `Configuración -> Identidad de empresa`, para que el cliente vea su nombre, logo, colores y datos empresariales. Una instalación nueva arranca como `ERP` / `Sistema ERP` hasta que se configure la empresa.

## Superficies

| Superficie | Ruta | Stack | URL local |
|------------|------|-------|-----------|
| Panel interno | `lteco-panel/` | PHP 8.2+, Apache, PDO | `http://127.0.0.1:8081/lteco-panel/` |
| Web pública legacy | `public-web/` | PHP 8.2+, Apache, PDO | `http://127.0.0.1:8080/public-web/` |
| Storefront ecommerce | `storefront/` | Laravel 13, PHP 8.4, Nginx, PHP-FPM | `http://127.0.0.1:8082/` |

## Identidad De Empresa

La identidad instalada se guarda en la tabla `empresa` y permite definir:

- Nombre de la empresa
- Logo y favicon
- Color principal y secundario
- Razón social y RUT
- Teléfono, email, dirección y sitio web
- Pie de comprobantes/documentos
- Visibilidad discreta de `Powered by LTeco ERP`

Este enfoque mantiene un único producto genérico y evita tocar código por cada cliente.

## Stack

- PHP `>=8.2` en el proyecto raíz.
- PHP `8.4` en el storefront.
- Composer en raíz para autoload PSR-4 `Lteco\\` y `minishlink/web-push`.
- Laravel `13.x` y Livewire `4.x` en `storefront/`.
- MySQL/MariaDB como base comercial.
- Docker Compose para levantar panel, web pública, worker ecommerce y storefront.

## Setup Rápido

```bash
git clone git@github.com:faku-20/LTeco-ERP-System.git
cd LTeco-ERP-System
cp .env.example .env
docker compose up -d --build
scripts/migrate.sh --list
scripts/migrate.sh
```

Para el storefront:

```bash
cp storefront/.env.example storefront/.env
docker compose -f docker-compose.storefront.yml up -d --build
```

## Tests

```bash
docker compose -f docker-compose.storefront.test.yml build storefront_test
docker compose -f docker-compose.storefront.test.yml run --rm storefront_test
```
