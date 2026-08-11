# Cambio de rama y recreación de servicios

Este archivo conserva una operación histórica. La rama operativa actual es
`main`; `migracion-poo-total` no es una rama necesaria para ejecutar el sistema.
No copies `.env.main` o `.env.poo` sin confirmar que existan y correspondan al
entorno. Antes de cambiar de rama, preservá cualquier cambio sin commit.

Para cambiar a `main` y recrear los servicios legacy:

cd /opt/ltecobike || exit 1

git switch main
cp .env.main .env

if docker compose version >/dev/null 2>&1; then
  docker compose up -d --force-recreate panel
else
  docker-compose up -d --force-recreate panel
fi

Para el storefront, recrear su Compose por separado:

docker compose -f docker-compose.storefront.yml up -d --build --force-recreate
