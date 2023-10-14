<?php

namespace vaersaagod\muxmate;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\Asset;
use craft\events\AssetPreviewEvent;
use craft\events\DefineAssetThumbUrlEvent;
use craft\events\DefineBehaviorsEvent;
use craft\events\DefineElementInnerHtmlEvent;
use craft\events\DefineRulesEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\ReplaceAssetEvent;
use craft\helpers\Cp;
use craft\helpers\StringHelper;
use craft\log\MonologTarget;
use craft\models\FieldLayout;
use craft\services\Assets;
use craft\services\Fields;
use craft\web\Response;
use craft\web\UrlManager;

use Monolog\Formatter\LineFormatter;

use Psr\Log\LogLevel;

use vaersaagod\muxmate\assetpreviews\MuxVideoPreview;
use vaersaagod\muxmate\behaviors\MuxAssetBehavior;
use vaersaagod\muxmate\fields\MuxMateField;
use vaersaagod\muxmate\helpers\MuxMateHelper;
use vaersaagod\muxmate\helpers\SignedUrlsHelper;
use vaersaagod\muxmate\models\Settings;

use yii\base\Event;
use yii\base\ModelEvent;
use yii\web\Response as BaseResponse;

/**
 * MuxMate plugin
 *
 * @method static MuxMate getInstance()
 * @method Settings getSettings()
 */
class MuxMate extends Plugin
{

    /** @var string The default URL to the `<mux-player>` web component */
    public const MUX_PLAYER_URL = 'https://cdn.jsdelivr.net/npm/@mux/mux-player';

    /** @var string The default URL to the `<mux-video>` web component */
    public const MUX_VIDEO_URL = 'https://cdn.jsdelivr.net/npm/@mux/mux-video@0';

    public string $schemaVersion = '1.1.0';
    public bool $hasCpSettings = false;

    public function init(): void
    {
        parent::init();

        // Register a custom log target, keeping the format as simple as possible.
        Craft::getLogger()->dispatcher->targets[] = new MonologTarget([
            'name' => '_muxmate',
            'categories' => ['_muxmate', 'vaersaagod\\muxmate\\*'],
            'level' => LogLevel::INFO,
            'logContext' => false,
            'allowLineBreaks' => false,
            'formatter' => new LineFormatter(
                format: "%datetime% %message%\n",
                dateFormat: 'Y-m-d H:i:s',
            ),
        ]);

        // Defer most setup tasks until Craft is fully initialized
        Craft::$app->onInit(function() {
            $this->attachEventHandlers();
            // ...
        });
    }

    /**
     * @return Model|null
     * @throws \yii\base\InvalidConfigException
     */
    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    /**
     * @return void
     */
    private function attachEventHandlers(): void
    {

        // Register custom MuxMate field type
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = MuxMateField::class;
        });

        // Register Mux asset behavior
        Event::on(
            Asset::class,
            Model::EVENT_DEFINE_BEHAVIORS,
            static function(DefineBehaviorsEvent $event) {
                $event->behaviors['muxAssetBehavior'] = [
                    'class' => MuxAssetBehavior::class,
                ];
            }
        );

        // Create Mux assets when videos are saved
        Event::on(
            Asset::class,
            Element::EVENT_AFTER_PROPAGATE,
            static function(ModelEvent $event) {
                /** @var Asset $asset */
                $asset = $event->sender;
                if (
                    $asset->resaving ||
                    $asset->kind !== Asset::KIND_VIDEO ||
                    MuxMateHelper::getMuxAssetId($asset)
                ) {
                    return;
                }
                MuxMateHelper::updateOrCreateMuxAsset($asset);
            }
        );

        // Make sure the Mux attributes are wiped when assets are replaced
        Event::on(
            Assets::class,
            Assets::EVENT_BEFORE_REPLACE_ASSET,
            static function(ReplaceAssetEvent $event) {
                $asset = $event->asset;
                if ($asset->kind !== Asset::KIND_VIDEO) {
                    return;
                }
                MuxMateHelper::deleteMuxAttributesForAsset($asset);
            }
        );

        // Delete Mux assets when videos are deleted
        Event::on(
            Asset::class,
            Element::EVENT_AFTER_DELETE,
            static function(Event $event) {
                /** Asset $asset */
                $asset = $event->sender;
                if ($asset->kind !== Asset::KIND_VIDEO) {
                    return;
                }
                MuxMateHelper::deleteMuxAttributesForAsset($asset);
            }
        );

        // Replace asset thumbs for Mux videos
        Event::on(
            Assets::class,
            Assets::EVENT_DEFINE_THUMB_URL,
            function(DefineAssetThumbUrlEvent $event) {
                $asset = $event->asset;
                if (
                    $asset->kind !== Asset::KIND_VIDEO ||
                    !MuxMateHelper::getMuxAssetId($asset) ||
                    MuxMateHelper::getMuxStatus($asset) !== 'ready'
                ) {
                    return;
                }
                $thumbSize = max($event->width, $event->height);
                $url = MuxMateHelper::getMuxImageUrl($asset, ['width' => $thumbSize, 'height' => $thumbSize, 'fit_mode' => 'preserve']);
                if (empty($url)) {
                    return;
                }
                $event->url = $url;
            }
        );

        Event::on(
            Cp::class,
            Cp::EVENT_DEFINE_ELEMENT_INNER_HTML,
            static function(DefineElementInnerHtmlEvent $event) {
                $element = $event->element;
                if (
                    !$element instanceof Asset ||
                    $element->kind !== Asset::KIND_VIDEO ||
                    $event->size !== 'large' ||
                    !MuxMateHelper::getMuxAssetId($element) ||
                    MuxMateHelper::getMuxStatus($element) !== 'ready'
                ) {
                    return;
                }
                $event->innerHtml = str_replace('class="elementthumb', 'class="elementthumb muxvideo', $event->innerHtml);
                $css = <<< CSS
                    .elementthumb.muxvideo::before {
                        content: "VIDEO";
                        display: block;
                        position: absolute;
                        background-color: black;
                        color: white;
                        left: 50%;
                        top: 50%;
                        transform: translate(-50%, -50%);
                        pointer-events: none;
                        font-size: 11px;
                        border-radius: 3px;
                        padding: 0 4px;
                    }
                CSS;
                Craft::$app->getView()->registerCss($css);
            }
        );

        // Add video preview handler
        Event::on(
            Assets::class,
            Assets::EVENT_REGISTER_PREVIEW_HANDLER,
            static function(AssetPreviewEvent $event) {
                $asset = $event->asset;
                if ($asset->kind !== Asset::KIND_VIDEO) {
                    return;
                }
                $event->previewHandler = new MuxVideoPreview($asset);
            }
        );

        // Prevent more than one MuxMate field from being added to a field layout
        // Also prevent MuxMate fields from being added to non-asset field layouts
        Event::on(
            FieldLayout::class,
            Model::EVENT_DEFINE_RULES,
            static function(DefineRulesEvent $event) {
                /** @var FieldLayout $fieldLayout */
                $fieldLayout = $event->sender;
                $event->rules[] = [
                    'customFields', static function() use ($fieldLayout) {
                        $customFields = $fieldLayout->getCustomFields();
                        $hasMuxMateField = false;
                        foreach ($customFields as $customField) {
                            if ($customField instanceof MuxMateField) {
                                if ($hasMuxMateField) {
                                    $fieldLayout->addError('fields', Craft::t('_muxmate', 'Only one MuxMate field can be added to a single field layout.'));
                                    break;
                                }
                                $hasMuxMateField = true;
                            }
                        }
                        if ($hasMuxMateField && $fieldLayout->type !== Asset::class) {
                            $fieldLayout->addError('fields', Craft::t('_muxmate', 'MuxMate fields are only supported for assets.'));
                        }
                    }
                ];
            }
        );

        // Add a route to the webhooks controller
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            static function(RegisterUrlRulesEvent $event) {
                $event->rules['muxmate/webhook'] = '_muxmate/webhook';
            }
        );

        // Replace nonce placeholders
        if (
            Craft::$app->getRequest()->getIsSiteRequest() &&
            $scriptSrcNonce = $this->getSettings()->scriptSrcNonce
        ) {
            Event::on(
                Response::class,
                BaseResponse::EVENT_AFTER_PREPARE,
                static function(Event $event) use ($scriptSrcNonce) {
                    /** @var Response|null $response */
                    $response = $event->sender;
                    $content = $response?->content;
                    if (empty($content)) {
                        return;
                    }
                    $response->content = StringHelper::replace($content, 'nonce="%%%MUXMATE_NONCE_PLACEHOLDER%%%"', 'nonce="' . $scriptSrcNonce . '"');
                }
            );
        }

        // Replace signed URL token placeholders
        if (
            Craft::$app->getRequest()->getIsSiteRequest() &&
            Craft::$app->getConfig()->getGeneral()->enableTemplateCaching
        ) {
            Event::on(
                Response::class,
                BaseResponse::EVENT_AFTER_PREPARE,
                static function(Event $event) {
                    /** @var Response|null $response */
                    $response = $event->sender;
                    $content = $response?->content;
                    if (empty($content)) {
                        return;
                    }
                    preg_match_all('/(MUX_TOKEN_PLACEHOLDER)(.+)(MUX_TOKEN_PLACEHOLDER)/', $content, $matches);
                    if (empty($matches[0] ?? null)) {
                        return;
                    }
                    for ($i = 0; $i < count($matches[0]); ++$i) {
                        $match = $matches[0][$i];
                        $token = '';
                        try {
                            $placeholderToken = $matches[2][$i] ?? '';
                            $decodedPlaceholderToken = SignedUrlsHelper::decodePlaceholderToken($placeholderToken);
                            if (!empty($decodedPlaceholderToken) && is_array($decodedPlaceholderToken)) {
                                ['playbackId' => $playbackId, 'aud' => $aud, 'claims' => $claims, 'expirationInSeconds' => $expirationInSeconds] = $decodedPlaceholderToken;
                                $token = SignedUrlsHelper::getToken($playbackId, $aud, $claims, $expirationInSeconds, false);
                            }
                        } catch (\Throwable $e) {
                            Craft::error($e, __METHOD__);
                        }
                        $content = str_replace($match, $token, $content);
                    }
                    $response->content = $content;
                }
            );
        }

    }
}
