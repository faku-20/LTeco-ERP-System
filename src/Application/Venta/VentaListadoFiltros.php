<?php

declare(strict_types=1);

namespace Lteco\Application\Venta;

final class VentaListadoFiltros
{
    public function __construct(
        public string $estado,
        public string $metodo,
        public string $cliente,
        public string $desde,
        public string $hasta
    ) {
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function desdeInput(array $input): self
    {
        return new self(
            trim((string) ($input['estado'] ?? '')),
            trim((string) ($input['metodo'] ?? '')),
            trim((string) ($input['cliente'] ?? '')),
            trim((string) ($input['desde'] ?? '')),
            trim((string) ($input['hasta'] ?? ''))
        );
    }

    /**
     * @return array{sql:string,params:array<string,int|string>}
     */
    public function condicion(?int $idUsuarioVendedor = null): array
    {
        $where = [];
        $params = [];

        if ($idUsuarioVendedor !== null) {
            $where[] = 'v.UsuarioVendedorId = :usuario_vendedor_id';
            $params[':usuario_vendedor_id'] = $idUsuarioVendedor;
        }

        if ($this->estado !== '') {
            $where[] = 'v.EstadoVenta = :estado';
            $params[':estado'] = $this->estado;
        }

        if ($this->metodo !== '') {
            $where[] = 'v.MetodoPago = :metodo';
            $params[':metodo'] = $this->metodo;
        }

        if ($this->cliente !== '') {
            // Un placeholder único por campo: con prepares nativos
            // (PDO::ATTR_EMULATE_PREPARES = false) no se puede reusar el mismo
            // placeholder nombrado en varias posiciones (HY093).
            $campos = [
                'c.NombreApellido' => ':cliente_nombre',
                'c.Telefono'       => ':cliente_telefono',
                'c.Correo'         => ':cliente_correo',
                'c.Cedula'         => ':cliente_cedula',
                'c.Direccion'      => ':cliente_direccion',
                'c.RUT'            => ':cliente_rut',
            ];
            $like = '%' . $this->cliente . '%';
            $condiciones = [];
            foreach ($campos as $columna => $placeholder) {
                $condiciones[] = $columna . ' LIKE ' . $placeholder;
                $params[$placeholder] = $like;
            }
            $where[] = '(' . implode(' OR ', $condiciones) . ')';
        }

        if ($this->desde !== '') {
            $where[] = 'DATE(v.FechaVenta) >= :desde';
            $params[':desde'] = $this->desde;
        }

        if ($this->hasta !== '') {
            $where[] = 'DATE(v.FechaVenta) <= :hasta';
            $params[':hasta'] = $this->hasta;
        }

        return [
            'sql' => $where === [] ? '' : 'WHERE ' . implode(' AND ', $where),
            'params' => $params,
        ];
    }

    /**
     * @return array<string,string>
     */
    public function paraQuery(): array
    {
        return [
            'estado' => $this->estado,
            'metodo' => $this->metodo,
            'cliente' => $this->cliente,
            'desde' => $this->desde,
            'hasta' => $this->hasta,
        ];
    }
}
