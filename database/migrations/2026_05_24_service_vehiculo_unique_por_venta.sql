-- LTECO V3.3.7
-- Ajusta la unicidad de services para permitir reventa de una moto luego de anular una venta.
-- Antes: un vehículo solo podía tener un Service 1, 2, 3 y 4 en toda su historia.
-- Ahora: un vehículo puede tener Service 1, 2, 3 y 4 por cada venta.

ALTER TABLE service_vehiculo
    DROP INDEX uq_service_vehiculo_numero;

ALTER TABLE service_vehiculo
    ADD UNIQUE KEY uq_service_vehiculo_venta_numero (IdVehiculo, IdVenta, NumeroService);
