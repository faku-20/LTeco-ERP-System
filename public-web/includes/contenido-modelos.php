<?php

declare(strict_types=1);

function contenidoModeloPublico(string $modelo): array
{
    $modelo = strtoupper(trim($modelo));

    $fichas = [
        'Q8-500W' => [
            'resumen' => 'Scooter eléctrica de 500W con diseño compacto, conducción urbana ágil y respaldo para acompañante.',
            'especificaciones' => [
                'Motor' => '500W',
                'Batería' => '48V · 12Ah o 20Ah',
                'Velocidad máxima' => '45 km/h',
                'Autonomía aproximada' => 'Hasta 50 km',
                'Tiempo de carga' => '5 a 6 horas',
                'Freno delantero' => 'Disco',
                'Carga máxima' => 'Hasta 100 kg',
                'Seguridad' => 'Alarma y 3 velocidades',
                'Garantía' => '1 año en repuestos, batería y cargador',
            ],
        ],
        'SL-500W' => [
            'resumen' => 'Modelo urbano de 500W y batería de 20Ah, con doble faro delantero y baúl trasero.',
            'especificaciones' => [
                'Motor' => '500W',
                'Batería' => '48V · 20Ah',
                'Autonomía aproximada' => 'Hasta 40 km',
                'Velocidad máxima' => '42 km/h',
                'Tiempo de carga' => '5 a 6 horas',
                'Freno delantero' => 'Disco',
                'Equipamiento' => 'Doble faro y baúl trasero',
                'Accesorio opcional' => 'Canasto delantero, precio a confirmar',
                'Garantía' => '1 año en repuestos, batería y cargador',
            ],
        ],
    ];

    return $fichas[$modelo] ?? ['resumen' => '', 'especificaciones' => []];
}

function preguntasFrecuentesPublicas(): array
{
    return [
        '¿Para qué tipo de uso son estas motos eléctricas?' => 'Están pensadas principalmente para traslados urbanos diarios, trabajo y movilidad dentro de la ciudad.',
        '¿Qué autonomía tienen?' => 'Depende del modelo, la batería, el peso transportado y la forma de conducción. La Q8-500W alcanza aproximadamente 50 km y la SL-500W aproximadamente 40 km.',
        '¿Cuánto demora la carga?' => 'El tiempo de carga estimado es de 5 a 6 horas, según el modelo y el nivel previo de la batería.',
        '¿Qué tipo de batería utilizan?' => 'Utilizan baterías de plomo-ácido de 48V. La Q8-500W está disponible en 12Ah o 20Ah y la SL-500W en 20Ah.',
        '¿Tienen garantía y service?' => 'Sí. La garantía informada es de 1 año en repuestos, batería y cargador, con respaldo y service posventa.',
        '¿Cómo consulto disponibilidad o elijo un modelo?' => 'Podés ver el stock y las variantes publicadas, comprar con tu cuenta verificada o escribirnos por WhatsApp para recibir asesoramiento.',
    ];
}
