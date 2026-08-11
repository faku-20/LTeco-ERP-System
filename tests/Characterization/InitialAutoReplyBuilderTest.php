<?php

declare(strict_types=1);

use Lteco\Application\Whatsapp\InitialAutoReplyBuilder;
use Lteco\Application\Whatsapp\InitialAutoReplyEligibility;

final class InitialAutoReplyBuilderTest
{
    public static function run(): void
    {
        $builder = new InitialAutoReplyBuilder();
        $base = dirname(__DIR__, 2);

        $catalog = $builder->build('hola, quiero mas info sobre los modelos');
        Assert::same('Auto respuesta inicial', 'texto catálogo igual a V3', 'Hola, buenas. Te mando fotos de los modelos y te paso información del que más te interesa.', $catalog['body']);
        Assert::same('Auto respuesta inicial', 'catálogo envía cuatro fichas', 4, count($catalog['media']));
        Assert::same('Auto respuesta inicial', 'primera ficha identifica Q8 350W', 'q8_350w', $catalog['media'][0]['model_key']);
        Assert::same('Auto respuesta inicial', 'segunda ficha identifica Q8 500W', 'q8_500w', $catalog['media'][1]['model_key']);
        Assert::same('Auto respuesta inicial', 'tercera ficha identifica LY 500W', 'ly_500w', $catalog['media'][2]['model_key']);
        Assert::same('Auto respuesta inicial', 'cuarta ficha identifica SL 500W', 'sl_500w', $catalog['media'][3]['model_key']);
        foreach ($catalog['media'] as $media) {
            Assert::isTrue('Auto respuesta inicial', 'imagen usa dominio productivo actual', str_starts_with($media['url'], 'https://panel.ltecobike.shop/lteco-panel/assets/img/whatsapp-models/'));
            $imagePath = $base.parse_url($media['url'], PHP_URL_PATH);
            Assert::isTrue('Auto respuesta inicial', 'imagen existe en el panel', is_file($imagePath));
            $imageSize = getimagesize($imagePath);
            Assert::same('Auto respuesta inicial', 'collage mantiene ancho esperado', 1400, $imageSize[0] ?? 0);
            Assert::same('Auto respuesta inicial', 'collage mantiene alto esperado', 900, $imageSize[1] ?? 0);
            Assert::same('Auto respuesta inicial', 'foto actual es JPEG compatible con WhatsApp', 'image/jpeg', $imageSize['mime'] ?? '');
            Assert::same(
                'Auto respuesta inicial',
                'versión de imagen evita caché obsoleta',
                'v='.substr((string)hash_file('sha256', $imagePath), 0, 8),
                (string)parse_url($media['url'], PHP_URL_QUERY)
            );
        }
        Assert::same('Auto respuesta inicial', 'cierre catálogo igual a V3', 'Comentame cuál te gusta más y te paso información del que más te interesa.', $catalog['final_body']);

        foreach (['Q8 350W', 'Q8-500W', 'LY 500W', 'SL 500W'] as $model) {
            $reply = $builder->build('Hola, me interesa la '.$model);
            Assert::isTrue('Auto respuesta inicial', 'detecta '.$model, str_contains($reply['body'], str_replace('-', ' ', $model)) || str_contains($reply['body'], $model));
            Assert::same('Auto respuesta inicial', $model.' envía una ficha', 1, count($reply['media']));

            $followUp = $builder->buildModelFollowUp('Me interesa la '.$model);
            Assert::isTrue('Auto respuesta modelo', 'genera seguimiento '.$model, $followUp !== null);
            Assert::isTrue('Auto respuesta modelo', 'seguimiento '.$model.' coordina showroom', str_contains((string)$followUp['final_body'], 'showroom'));
        }

        $slReply = $builder->build('Me interesa la SL 500W');
        Assert::isTrue('Auto respuesta inicial', 'SL informa solo datos confirmados', str_contains($slReply['body'], 'autonomía de 50km'));
        Assert::isFalse('Auto respuesta inicial', 'SL no inventa requisito de libreta', str_contains($slReply['body'], 'libreta'));
        Assert::isFalse('Auto respuesta inicial', 'SL no inventa garantía', str_contains($slReply['body'], 'garantía'));

        Assert::same('Auto respuesta modelo', 'no responde sin modelo conocido', null, $builder->buildModelFollowUp('Me interesa una automática'));

        $eligibility = new InitialAutoReplyEligibility();
        Assert::isFalse('Auto respuesta inicial', 'no saluda una continuación de agenda', $eligibility->shouldReply('Ok,si el otro sabado'));
        Assert::isFalse('Auto respuesta inicial', 'no saluda una confirmación con tilde', $eligibility->shouldReply('Sí, gracias'));
        Assert::isFalse('Auto respuesta inicial', 'no saluda una conformidad con tilde', $eligibility->shouldReply('Bárbaro'));
        Assert::isFalse('Auto respuesta inicial', 'no saluda un mensaje citado', $eligibility->shouldReply('Me interesa esta', 'wamid.citado'));
        Assert::isFalse('Auto respuesta inicial', 'no responde reacciones', $eligibility->shouldReply('[reaction]'));
        Assert::isTrue('Auto respuesta inicial', 'responde fallback de mensaje no disponible de Meta', $eligibility->shouldReply('[unsupported]'));
        Assert::isTrue('Auto respuesta inicial', 'mantiene consulta inicial real', $eligibility->shouldReply('Quiero mas informacion sobre modelos y costos'));
        Assert::same('Auto respuesta inicial', 'fallback unsupported envía catálogo', 4, count($builder->build('[unsupported]')['media']));
        foreach (['información', 'batería', 'autonomía'] as $accentedCatalogTerm) {
            Assert::same(
                'Auto respuesta inicial',
                'reconoce consulta acentuada: '.$accentedCatalogTerm,
                4,
                count($builder->build($accentedCatalogTerm)['media'])
            );
        }

        $previousPanelUrl = getenv('LTECO_PANEL_PUBLIC_URL');
        putenv('LTECO_PANEL_PUBLIC_URL=/public-web');
        $relativeConfigReply = $builder->build('modelos');
        putenv($previousPanelUrl === false ? 'LTECO_PANEL_PUBLIC_URL' : 'LTECO_PANEL_PUBLIC_URL='.$previousPanelUrl);
        Assert::isTrue(
            'Auto respuesta inicial',
            'ignora URL pública relativa no apta para Meta',
            str_starts_with($relativeConfigReply['media'][0]['url'], 'https://panel.ltecobike.shop/')
        );

        $generic = $builder->build('Hola');
        Assert::same('Auto respuesta inicial', 'saludo genérico sin imágenes', 0, count($generic['media']));
        Assert::isTrue('Auto respuesta inicial', 'saludo genérico deriva a humano', str_contains($generic['body'], 'miembro del equipo'));

        $aiSupport = (string)file_get_contents($base.'/src/Presentation/Panel/Support/ai.php');
        $whatsappSupport = (string)file_get_contents($base.'/src/Presentation/Panel/Support/whatsapp.php');
        Assert::isTrue('Auto respuesta inicial', 'webhook usa constructor contextual', str_contains($aiSupport, 'InitialAutoReplyBuilder'));
        Assert::isTrue('Auto respuesta inicial', 'WhatsApp soporta payload de imagen', str_contains($whatsappSupport, "'type' => 'image'"));
        Assert::isTrue('Auto respuesta inicial', 'WhatsApp sube imágenes a Meta y envía por media_id', str_contains($whatsappSupport, 'whatsappMediaIdParaImagen') && str_contains($whatsappSupport, "'id' => \$mediaId"));
        Assert::isFalse('Auto respuesta inicial', 'imágenes no dependen de V3', str_contains($builderSource = (string)file_get_contents($base.'/src/Application/Whatsapp/InitialAutoReplyBuilder.php'), 'v3.ltecobike.shop'));
        $webhookSource = (string)file_get_contents($base.'/lteco-panel/whatsapp_webhook.php');
        Assert::isFalse('Auto respuesta modelo', 'webhook no responde mensajes posteriores', str_contains($webhookSource, 'aiMaybeSendModelAutoReply'));
        Assert::isTrue('Auto respuesta inicial', 'marca última imagen para ordenar el cierre', str_contains($aiSupport, "'_ultimo'"));
        Assert::isTrue('Auto respuesta inicial', 'cierre espera confirmación de entrega', str_contains($webhookSource, 'aiMaybeSendInitialAutoReplyClosure'));
        Assert::isTrue('Auto respuesta inicial', 'solo intenta responder el primer inbound guardado', str_contains($aiSupport, "IdInbox < ?"));
    }
}
