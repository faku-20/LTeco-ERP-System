<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class CatalogoMoto extends Model
{
    protected $connection = 'catalog';

    protected $table = 'storefront_catalogo_motos';

    protected $primaryKey = 'id_producto';

    public $incrementing = true;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id_producto' => 'integer',
            'precio' => 'decimal:2',
            'stock' => 'integer',
            'destacado' => 'boolean',
            'orden' => 'integer',
            'capacidad_bateria_ah' => 'integer',
            'disponible' => 'boolean',
        ];
    }
}
