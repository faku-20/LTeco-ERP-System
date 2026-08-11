<?php

declare(strict_types=1);

namespace Lteco\Application\Whatsapp;

final class InitialAutoReplyBuilder
{
    private const DEFAULT_PANEL_PUBLIC_URL = 'https://panel.ltecobike.shop';

    private const MODELS = [
        [
            'key' => 'q8_350w',
            'name' => 'Q8 350W',
            'aliases' => ['q8 350', 'q8-350', 'q8 350w', 'q8-350w'],
            'summary' => 'motor de 350W, autonomía de 45km, velocidad máxima de 45km/h, carga de 5 a 6 hs y frenos a tambor.',
            'detail' => 'Requiere libreta G1 o G2. Soporta hasta 100 kg, tiene alarma, 3 velocidades y garantía de 1 año en repuestos, batería y cargador.',
            'media_file' => 'q8-350w.jpg',
        ],
        [
            'key' => 'q8_500w',
            'name' => 'Q8 500W',
            'aliases' => ['q8 500', 'q8-500', 'q8 500w', 'q8-500w'],
            'summary' => 'motor de 500W, autonomía de 50km, velocidad máxima de 45km/h, carga de 5 a 6 hs y frenos a disco.',
            'detail' => 'Requiere libreta G1 o G2. Soporta hasta 100 kg, tiene alarma, 3 velocidades y garantía de 1 año en repuestos, batería y cargador.',
            'media_file' => 'q8-500w.jpg',
        ],
        [
            'key' => 'ly_500w',
            'name' => 'LY 500W',
            'aliases' => ['ly 500', 'ly-500', 'ly 500w', 'ly-500w'],
            'summary' => 'motor de 500W, autonomía de 50km, velocidad máxima de 45km/h, carga de 5 a 6 hs y frenos a disco.',
            'detail' => 'Es similar a la Q8, con variantes de diseño. Requiere libreta G1 o G2 y tiene garantía de 1 año en repuestos, batería y cargador.',
            'media_file' => 'ly-500w.jpg',
        ],
        [
            'key' => 'sl_500w',
            'name' => 'SL 500W',
            'aliases' => ['sl 500', 'sl-500', 'sl 500w', 'sl-500w'],
            'summary' => 'motor de 500W, autonomía de 50km, velocidad máxima de 45km/h, carga de 5 a 6 hs y frenos a disco.',
            'detail' => '',
            'media_file' => 'sl-500w.jpg',
        ],
    ];

    private const CATALOG_TERMS = [
        'modelo', 'modelos', 'precio', 'precios', 'costo', 'costos', 'bateria', 'batería',
        'autonomia', 'autonomía', 'stock', 'opciones', 'informacion', 'información', 'catalogo',
    ];

    /** @return array{body:string,media:list<array{model_key:string,url:string,caption:string}>,final_body:?string} */
    public function build(string $message): array
    {
        $model = $this->detectModel($message);
        if ($model !== null) {
            $description = trim($model['summary'].' '.$model['detail']);

            return [
                'body' => 'Hola, te cuento sobre la '.$model['name'].': '.$description.' Un asesor te confirma disponibilidad, colores y precio en breve.',
                'media' => [$this->modelMedia($model)],
                'final_body' => 'Comentame si ese modelo te gusta y te paso más información.',
            ];
        }

        if ($this->looksLikeUnsupportedAdMessage($message) || $this->looksLikeCatalogQuestion($message)) {
            return [
                'body' => 'Hola, buenas. Te mando fotos de los modelos y te paso información del que más te interesa.',
                'media' => array_map(fn (array $item): array => $this->modelMedia($item), self::MODELS),
                'final_body' => 'Comentame cuál te gusta más y te paso información del que más te interesa.',
            ];
        }

        return [
            'body' => 'Hola, gracias por escribir a Ltecobike. Recibimos tu mensaje y te derivamos con un miembro del equipo para que pueda ayudarte.',
            'media' => [],
            'final_body' => null,
        ];
    }

    /** @return array{model_key:string,body:string,media:list<array{model_key:string,url:string,caption:string}>,final_body:string}|null */
    public function buildModelFollowUp(string $message): ?array
    {
        $model = $this->detectModel($message);
        if ($model === null) {
            return null;
        }
        $description = trim($model['summary'].' '.$model['detail']);

        return [
            'model_key' => (string)$model['key'],
            'body' => 'Hola, sobre la '.$model['name'].': '.$description.' Si te interesa ese modelo, te confirmamos disponibilidad, colores y precio vigente.',
            'media' => [$this->modelMedia($model)],
            'final_body' => 'Si querés verla en persona, un asesor te coordina la visita al showroom.',
        ];
    }

    /** @return array<string,mixed>|null */
    private function detectModel(string $message): ?array
    {
        $normalized = mb_strtolower($message);
        foreach (self::MODELS as $model) {
            foreach ($model['aliases'] as $alias) {
                if (str_contains($normalized, $alias)) {
                    return $model;
                }
            }
        }

        return null;
    }

    private function looksLikeCatalogQuestion(string $message): bool
    {
        $normalized = mb_strtolower($message);
        foreach (self::CATALOG_TERMS as $term) {
            if (str_contains($normalized, $term)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeUnsupportedAdMessage(string $message): bool
    {
        return mb_strtolower(trim($message)) === '[unsupported]';
    }

    /** @param array<string,mixed> $model @return array{model_key:string,url:string,caption:string} */
    private function modelMedia(array $model): array
    {
        return [
            'model_key' => (string)$model['key'],
            'url' => $this->mediaUrl((string)$model['media_file']),
            'caption' => $model['name'].': '.$model['summary'],
        ];
    }

    private function mediaUrl(string $filename): string
    {
        $configuredUrl = trim((string)(getenv('LTECO_PANEL_PUBLIC_URL') ?: ''));
        $baseUrl = filter_var($configuredUrl, FILTER_VALIDATE_URL)
            ? rtrim($configuredUrl, '/')
            : self::DEFAULT_PANEL_PUBLIC_URL;
        $relativePath = '/lteco-panel/assets/img/whatsapp-models/'.$filename;
        $filePath = dirname(__DIR__, 3).$relativePath;
        $version = is_file($filePath)
            ? substr((string)hash_file('sha256', $filePath), 0, 8)
            : 'missing';

        return $baseUrl.$relativePath.'?v='.$version;
    }
}
