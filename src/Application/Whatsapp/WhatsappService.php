<?php

declare(strict_types=1);

namespace Lteco\Application\Whatsapp;

use Lteco\Infrastructure\Repository\WhatsappRepository;

final class WhatsappService
{
    public function __construct(private WhatsappRepository $repository)
    {
    }

    /** @param array<string,mixed> $defaults @return array<string,mixed> */
    public function configuracion(array $defaults): array
    {
        $fila = $this->repository->configuracion();
        if (!$fila) {
            return $defaults;
        }

        $mapa = [
            'WaEnabled' => 'enabled',
            'WaPhoneId' => 'phone_id',
            'WaToken' => 'token',
            'WaTplVenta' => 'tpl_venta',
            'WaTplService' => 'tpl_service',
        ];
        foreach ($mapa as $columna => $clave) {
            if (!array_key_exists($columna, $fila) || $fila[$columna] === null || $fila[$columna] === '') {
                continue;
            }
            $defaults[$clave] = $columna === 'WaEnabled'
                ? (bool) (int) $fila[$columna]
                : (string) $fila[$columna];
        }
        return $defaults;
    }

    public function tablaDisponible(): bool
    {
        return $this->repository->tablaDisponible();
    }

    public function ultimoError(string $tipo, int $idReferencia, string $template): ?string
    {
        return $this->repository->ultimoError($tipo, $idReferencia, $template);
    }

    public function tieneVentanaTextoGratuita(string $telefono, int $horas = 24): bool
    {
        return $this->repository->tieneVentanaTextoGratuita(mb_substr($telefono, 0, 30), $horas);
    }

    public function registrar(
        string $tipo,
        int $idReferencia,
        string $telefono,
        string $template,
        string $estado,
        ?string $respuesta,
        ?string $waMessageId = null
    ): void {
        if (!$this->repository->tablaDisponible()) {
            return;
        }
        $this->repository->registrar(
            in_array($tipo, ['venta', 'service'], true) ? $tipo : 'venta',
            $idReferencia,
            mb_substr($telefono, 0, 30),
            mb_substr($template, 0, 100),
            in_array($estado, ['enviado', 'error', 'omitido'], true) ? $estado : 'omitido',
            $respuesta !== null ? mb_substr($respuesta, 0, 65535) : null,
            $waMessageId !== null ? mb_substr($waMessageId, 0, 255) : null
        );
    }

    public function asegurarEstructura(): void
    {
        $this->repository->asegurarTabla();
        $this->repository->asegurarColumnasConfiguracion();
    }

    public function asegurarMediaCache(): void
    {
        $this->repository->asegurarMediaCache();
    }

    public function mediaCacheId(string $sourceKey): ?string
    {
        return $this->repository->mediaCacheId($sourceKey);
    }

    public function guardarMediaCache(
        string $sourceKey,
        string $url,
        ?string $localPath,
        ?string $fileHash,
        string $mimeType,
        string $mediaId,
        ?string $respuestaMeta
    ): void {
        $this->repository->guardarMediaCache(
            mb_substr($sourceKey, 0, 64),
            mb_substr($url, 0, 2000),
            $localPath !== null ? mb_substr($localPath, 0, 500) : null,
            $fileHash !== null ? mb_substr($fileHash, 0, 64) : null,
            mb_substr($mimeType, 0, 80),
            mb_substr($mediaId, 0, 255),
            $respuestaMeta !== null ? mb_substr($respuestaMeta, 0, 65535) : null
        );
    }

    public function registrarEstadoWebhook(
        string $messageId,
        string $estado,
        string $respuestaWebhook,
        ?string $fechaEstadoMeta
    ): void {
        $messageId = trim($messageId);
        if ($messageId === '') {
            return;
        }

        $this->repository->registrarEstadoWebhook(
            mb_substr($messageId, 0, 255),
            mb_substr($estado, 0, 40),
            mb_substr($respuestaWebhook, 0, 65535),
            $fechaEstadoMeta
        );
    }

    public function programarResetPrueba(string $telefono): void
    {
        $this->repository->programarResetPrueba(mb_substr($telefono, 0, 30));
    }

    public function reclamarResetPrueba(string $telefono): bool
    {
        return $this->repository->reclamarResetPrueba(mb_substr($telefono, 0, 30));
    }

    public function liberarResetPrueba(string $telefono): void
    {
        $this->repository->liberarResetPrueba(mb_substr($telefono, 0, 30));
    }

    public function completarResetPrueba(string $telefono): void
    {
        $this->repository->completarResetPrueba(mb_substr($telefono, 0, 30));
    }

    /** @return list<array<string,mixed>> */
    public function servicesParaRecordatorio(string $desde, string $hasta): array
    {
        return $this->repository->servicesParaRecordatorio($desde, $hasta);
    }

    public function serviceYaNotificado(int $idService): bool
    {
        return $this->repository->tablaDisponible()
            && $this->repository->serviceYaNotificado($idService);
    }

    public function telefonoDistribuidor(int $idDistribuidor): ?string
    {
        return $this->repository->telefonoDistribuidor($idDistribuidor);
    }
}
