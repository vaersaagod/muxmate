<?php

namespace vaersaagod\muxmate\helpers;

use Craft;
use craft\base\Element;
use craft\base\FieldInterface;
use craft\elements\Asset;
use craft\helpers\App;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use craft\web\View;

use Illuminate\Support\Collection;

use Twig\Markup;
use vaersaagod\muxmate\fields\MuxMateField;
use vaersaagod\muxmate\models\MuxMateFieldAttributes;
use vaersaagod\muxmate\models\MuxPlaybackId;
use vaersaagod\muxmate\models\VolumeSettings;
use vaersaagod\muxmate\MuxMate;

use yii\base\InvalidConfigException;

final class MuxMateHelper
{

    /** @var string */
    public const MUX_STREAMING_DOMAIN = 'https://stream.mux.com';

    /** @var string */
    public const MUX_IMAGE_DOMAIN = 'https://image.mux.com';

    /** @var string */
    public const PLAYBACK_POLICY_PUBLIC = 'public';

    /** @var string */
    public const PLAYBACK_POLICY_SIGNED = 'signed';

    /** @var string */
    public const STATIC_RENDITION_QUALITY_HIGH = 'high';

    /** @var string */
    public const STATIC_RENDITION_QUALITY_MEDIUM = 'medium';

    /** @var string */
    public const STATIC_RENDITION_QUALITY_LOW = 'low';

    /** @var MuxMateField[] */
    private static array $_muxMateFieldsByVolume = [];

    /**
     * @param Asset|null $asset
     * @return string|null
     */
    public static function getMuxAssetId(?Asset $asset): ?string
    {
        return MuxMateHelper::getMuxMateFieldAttributes($asset)?->muxAssetId;
    }

    /**
     * @param Asset|null $asset
     * @param string|null $policy
     * @return MuxPlaybackId|null
     * @throws InvalidConfigException
     */
    public static function getMuxPlaybackId(?Asset $asset, ?string $policy = null): ?MuxPlaybackId
    {
        $policy = $policy ?? MuxMate::getInstance()->getSettings()->defaultPolicy;
        if (!in_array($policy, [MuxMateHelper::PLAYBACK_POLICY_SIGNED, MuxMateHelper::PLAYBACK_POLICY_PUBLIC], true)) {
            throw new InvalidConfigException("Invalid playback policy \"$policy\"");
        }
        $data = MuxMateHelper::getMuxMateFieldAttributes($asset)?->muxMetaData ?? [];
        $playbackId = Collection::make($data['playback_ids'] ?? [])
            ->where('policy', $policy)
            ->first();
        if (!$playbackId) {
            return null;
        }
        return new MuxPlaybackId($playbackId);
    }

    /**
     * See https://docs.mux.com/guides/video/get-images-from-a-video for params
     * @param Asset|null $asset
     * @param array|null $params
     * @param string|null $policy
     * @return string|null
     * @throws InvalidConfigException
     */
    public static function getMuxImageUrl(?Asset $asset, ?array $params = null, ?string $policy = null): ?string
    {

        if (
            !$asset instanceof Asset ||
            MuxMateHelper::getMuxStatus($asset) !== 'ready') {
            return null;
        }

        $playbackId = MuxMateHelper::getMuxPlaybackId($asset, $policy);
        if (!$playbackId instanceof MuxPlaybackId) {
            return null;
        }

        if (!$playbackId->validate()) {
            Craft::error("Invalid playback ID {$playbackId}: " . Json::encode($playbackId->getErrors()), __METHOD__);
            return null;
        }

        // Normalize params
        $params = $params ?? [];
        if (!isset($params['fit_mode'])) {
            if (isset($params['width']) && isset($params['height'])) {
                $params['fit_mode'] = 'smartcrop';
            } else {
                $params['fit_mode'] = 'preserve';
            }
        }

        // If the policy is signed; create a JWT signing token
        if ($playbackId->policy === MuxMateHelper::PLAYBACK_POLICY_SIGNED) {
            if (!$token = SignedUrlsHelper::getToken($playbackId, SignedUrlsHelper::SIGNED_URL_AUDIENCE_THUMBNAIL, $params)) {
                return null;
            }
            $params = [
                'token' => $token,
            ];
        }

        return UrlHelper::url(MuxMateHelper::MUX_IMAGE_DOMAIN . '/' . $playbackId . '/thumbnail.jpg', $params);

    }

    /**
     * See https://docs.mux.com/guides/video/get-images-from-a-video#get-an-animated-gif-from-a-video for params
     *
     * @param Asset|null $asset
     * @param array|null $params
     * @param string|null $policy
     * @return string|null
     * @throws InvalidConfigException
     */
    public static function getMuxGifUrl(?Asset $asset, ?array $params = null, ?string $policy = null): ?string
    {

        if (
            !$asset instanceof Asset ||
            MuxMateHelper::getMuxStatus($asset) !== 'ready') {
            return null;
        }

        $playbackId = MuxMateHelper::getMuxPlaybackId($asset, $policy);
        if (!$playbackId instanceof MuxPlaybackId) {
            return null;
        }

        if (!$playbackId->validate()) {
            Craft::error("Invalid playback ID {$playbackId}: " . Json::encode($playbackId->getErrors()), __METHOD__);
            return null;
        }

        // Normalize params
        $params = $params ?? [];

        // If the policy is signed; create a JWT signing token
        if ($playbackId->policy === MuxMateHelper::PLAYBACK_POLICY_SIGNED) {
            if (!$token = SignedUrlsHelper::getToken($playbackId, SignedUrlsHelper::SIGNED_URL_AUDIENCE_GIF, $params)) {
                return null;
            }
            $params = [
                'token' => $token,
            ];
        }

        return UrlHelper::url(MuxMateHelper::MUX_IMAGE_DOMAIN . '/' . $playbackId . "/animated.gif", $params);

    }

    /**
     * @param Asset|null $asset
     * @param string|null $quality "high", "medium" or "low"
     * @param string|null $policy "public" or "signed"
     * @param bool $download
     * @param string|null $filename
     * @return string|null
     * @throws InvalidConfigException
     */
    public static function getMuxMp4Url(?Asset $asset, ?string $quality = null, ?string $policy = null, bool $download = false, ?string $filename = null): ?string
    {

        if (!$asset instanceof Asset) {
            return null;
        }

        $muxData = MuxMateHelper::getMuxData($asset) ?? [];
        $staticRenditions = $muxData['static_renditions'] ?? [];
        if (($staticRenditions['status'] ?? null) !== 'ready') {
            return null;
        }

        $playbackId = MuxMateHelper::getMuxPlaybackId($asset, $policy);
        if (!$playbackId instanceof MuxPlaybackId) {
            return null;
        }

        $quality = $quality ?: MuxMate::getInstance()->getSettings()->defaultMp4Quality;
        $qualities = [
            MuxMateHelper::STATIC_RENDITION_QUALITY_HIGH,
            MuxMateHelper::STATIC_RENDITION_QUALITY_MEDIUM,
            MuxMateHelper::STATIC_RENDITION_QUALITY_LOW,
        ];
        if (!in_array($quality, $qualities, true)) {
            Craft::error("Invalid quality \"$quality\" (needs to be one of " . implode(', ', $qualities) . ')', __METHOD__);
            return null;
        }

        // Get the highest available quality
        $availableQualities = array_intersect($qualities, MuxMateHelper::getStaticRenditions($asset, true));
        if (empty($availableQualities)) {
            return null;
        }

        if (!in_array($quality, $availableQualities, true)) {
            $quality = $availableQualities[0];
        }

        $params = [];

        if ($download) {
            $params['download'] = $filename ?: $playbackId->__toString();
        }

        if ($playbackId->policy === MuxMateHelper::PLAYBACK_POLICY_SIGNED) {
            if (!$token = SignedUrlsHelper::getToken($playbackId, SignedUrlsHelper::SIGNED_URL_AUDIENCE_VIDEO, null, MuxMateHelper::getMuxVideoDuration($asset))) {
                return null;
            }
            $params['token'] = $token;
        }

        if (empty($params)) {
            $params = null;
        }

        return UrlHelper::url(MuxMateHelper::MUX_STREAMING_DOMAIN . "/" . $playbackId . "/$quality.mp4", $params);

    }

    /**
     * @param Asset|null $asset
     * @param array|null $params
     * @param string|null $policy
     * @return string|null
     * @throws InvalidConfigException
     */
    public static function getMuxStreamUrl(?Asset $asset, ?array $params = null, ?string $policy = null): ?string
    {

        if (
            !$asset instanceof Asset ||
            MuxMateHelper::getMuxStatus($asset) !== 'ready') {
            return null;
        }

        $playbackId = MuxMateHelper::getMuxPlaybackId($asset, $policy);
        if (!$playbackId instanceof MuxPlaybackId) {
            return null;
        }

        if (!$playbackId->validate()) {
            Craft::error("Invalid playback ID {$playbackId}: " . Json::encode($playbackId->getErrors()), __METHOD__);
            return null;
        }

        $settings = MuxMate::getInstance()->getSettings();

        // Get max resolution
        $maxResolutions = ['720p', '1080p', '1440p', '2160p'];
        $maxResolution = $params['max_resolution'] ?? $settings->maxResolution;
        if ($maxResolution && !in_array($maxResolution, $maxResolutions)) {
            throw new \Exception("Invalid max_resolution \"$maxResolution\". Needs to be one of " . implode(', ', $maxResolutions));
        }

        // Normalize params
        $params = [];
        if ($maxResolution) {
            $params['max_resolution'] = $maxResolution;
        }

        // If the policy is signed; create a JWT signing token
        if ($playbackId->policy === MuxMateHelper::PLAYBACK_POLICY_SIGNED) {
            if (!$token = SignedUrlsHelper::getToken($playbackId, SignedUrlsHelper::SIGNED_URL_AUDIENCE_VIDEO, $params, MuxMateHelper::getMuxVideoDuration($asset))) {
                return null;
            }
            $params = ['token' => $token];
        }

        if (empty(array_values($params))) {
            $params = null;
        }

        return UrlHelper::url(MuxMateHelper::MUX_STREAMING_DOMAIN . "/$playbackId.m3u8", $params);

    }

    public static function getMuxVideoTag(?Asset $asset, ?array $options = null, ?array $params = null, ?string $policy = null): ?Markup
    {

        if (
            !$asset instanceof Asset ||
            MuxMateHelper::getMuxStatus($asset) !== 'ready') {
            return null;
        }

        $playbackId = MuxMateHelper::getMuxPlaybackId($asset, $policy);
        if (!$playbackId instanceof MuxPlaybackId) {
            return null;
        }

        $settings = MuxMate::getInstance()->getSettings();

        // Get options
        $inline = $options['inline'] ?? null;
        $lazyload = $options['lazyload'] ?? $settings->lazyloadMuxVideo;

        // Get max resolution
        $maxResolutions = ['720p', '1080p', '1440p', '2160p'];
        $maxResolution = $params['max_resolution'] ?? $settings->maxResolution;
        if ($maxResolution && !in_array($maxResolution, $maxResolutions)) {
            throw new \Exception("Invalid max_resolution \"$maxResolution\". Needs to be one of " . implode(', ', $maxResolutions));
        }

        if ($playbackId->policy === MuxMateHelper::PLAYBACK_POLICY_SIGNED) {
            if (!empty($maxResolution)) {
                $claims = [
                    'max_resolution' => $params['max_resolution'],
                ];
            } else {
                $claims = null;
            }
            if (!$token = SignedUrlsHelper::getToken($playbackId, SignedUrlsHelper::SIGNED_URL_AUDIENCE_VIDEO, $claims, MuxMateHelper::getMuxVideoDuration($asset))) {
                return null;
            }
            $maxResolution = null;
        } else {
            $token = null;
        }

        $nonce = $settings->scriptSrcNonce;
        $muxVideoUrl = $settings->muxVideoUrl;

        try {
            $html = Template::raw(\Craft::$app->getView()->renderTemplate('_muxmate/_mux-video.twig', [
                'video' => $asset,
                'playbackId' => $playbackId,
                'token' => $token,
                'muxVideoUrl' => $muxVideoUrl,
                'nonce' => $nonce,
                'inline' => $inline,
                'lazyload' => $lazyload,
                'maxResolution' => $maxResolution,
            ], View::TEMPLATE_MODE_CP));
        } catch (\Throwable $e) {
            \Craft::error($e, __METHOD__);
            return null;
        }
        return $html;
    }

    /**
     * @param Asset|null $asset
     * @return string|null
     */
    public static function getMuxStatus(?Asset $asset): ?string
    {
        if (!$asset instanceof Asset) {
            return null;
        }
        $data = MuxMateHelper::getMuxMateFieldAttributes($asset)?->muxMetaData;
        if (!$data) {
            return null;
        }
        return $data['status'] ?? null;
    }

    /**
     * @param Asset|null $asset
     * @return float|null
     */
    public static function getMuxVideoDuration(?Asset $asset): ?float
    {
        if (!$asset instanceof Asset) {
            return null;
        }
        return MuxMateHelper::getMuxData($asset)['duration'] ?? null;
    }

    /**
     * @param Asset|null $asset
     * @return array|null
     */
    public static function getMuxData(?Asset $asset): ?array
    {
        return MuxMateHelper::getMuxMateFieldAttributes($asset)?->muxMetaData;
    }

    /**
     * Returns an array of all available static renditions, indexed by quality
     *
     * @param Asset|null $asset
     * @param bool $keysOnly Whether to only return a simple array of the available static rendition qualities' names (i.e. "high", "medium" and "low")
     * @return array|null
     */
    public static function getStaticRenditions(?Asset $asset, bool $keysOnly = false): ?array
    {
        if (!$asset instanceof Asset) {
            return null;
        }
        $muxData = MuxMateHelper::getMuxData($asset) ?? [];
        $staticRenditions = $muxData['static_renditions'] ?? [];
        if (($staticRenditions['status'] ?? null) !== 'ready') {
            return null;
        }
        $staticRenditionsByQuality = [];
        foreach ($staticRenditions['files'] ?? [] as $staticRendition) {
            $quality = explode('.', $staticRendition['name'])[0];
            $staticRenditionsByQuality[$quality] = $staticRendition;
        }
        if (empty($staticRenditionsByQuality)) {
            return null;
        }
        if ($keysOnly) {
            return array_keys($staticRenditionsByQuality);
        }
        return $staticRenditionsByQuality;
    }

    /**
     * @param Asset|null $asset
     * @return bool
     */
    public static function updateOrCreateMuxAsset(?Asset $asset): bool
    {

        $muxAssetId = MuxMateHelper::getMuxAssetId($asset);
        $muxAsset = null;

        // Try to get existing Mux asset. If it doesn't exist, we'll create a new one.
        if ($muxAssetId) {
            try {
                $muxAsset = MuxApiHelper::getAsset($muxAssetId);
            } catch (\Throwable $e) {
                Craft::error($e, __METHOD__);
                MuxMateHelper::deleteMuxAttributesForAsset($asset);
            }
        }

        // Create a new Mux asset?
        if (!$muxAsset) {
            try {
                $assetUrl = MuxMateHelper::_getAssetUrl($asset);
                if (!$assetUrl) {
                    throw new \Exception("Asset ID \"$asset->id\" has no URL");
                }
                $muxAsset = MuxApiHelper::createAsset($assetUrl);
                $muxAssetId = $muxAsset->getId();
            } catch (\Throwable $e) {
                Craft::error($e, __METHOD__);
                $muxAsset = null;
            }
        }

        if (!$muxAsset) {
            // Still no Mux asset; make sure any Mux data set on the Craft asset is wiped out and then bail
            MuxMateHelper::deleteMuxAttributesForAsset($asset);
            return false;
        }

        return MuxMateHelper::saveMuxAttributesToAsset($asset, [
            'muxAssetId' => $muxAssetId,
            'muxMetaData' => (array)$muxAsset->jsonSerialize(),
        ]);

    }

    /**
     * @param Asset $asset
     * @param array $attributes
     * @return bool
     */
    public static function saveMuxAttributesToAsset(Asset $asset, array $attributes): bool
    {

        if (!MuxMateHelper::_setMuxMateFieldAttributes($asset, $attributes)) {
            return false;
        }

        $asset->setScenario(Element::SCENARIO_ESSENTIALS);
        $asset->resaving = true;

        try {
            $success = Craft::$app->getElements()->saveElement($asset, false);
        } catch (\Throwable $e) {
            Craft::error($e, __METHOD__);
            return false;
        }

        if (!$success) {
            Craft::error("Unable to save Mux attributes to asset: " . Json::encode($asset->getErrors()), __METHOD__);
            return false;
        }

        return true;
    }

    /**
     * @param Asset|null $asset
     * @param bool $alsoDeleteMuxAsset
     * @return bool
     */
    public static function deleteMuxAttributesForAsset(?Asset $asset, bool $alsoDeleteMuxAsset = true): bool
    {

        $muxAssetId = MuxMateHelper::getMuxMateFieldAttributes($asset)?->muxAssetId;

        if (!$muxAssetId) {
            return false;
        }

        MuxMateHelper::_setMuxMateFieldAttributes($asset, null);

        $asset->setScenario(Element::SCENARIO_ESSENTIALS);
        $asset->resaving = true;

        try {
            $success = Craft::$app->getElements()->saveElement($asset, false);
        } catch (\Throwable $e) {
            Craft::error($e, __METHOD__);
            return false;
        }

        if (!$success) {
            Craft::error("Unable to delete Mux attributes for asset: " . Json::encode($asset->getErrors()));
            return false;
        }

        if ($alsoDeleteMuxAsset) {
            try {
                MuxApiHelper::deleteAsset($muxAssetId);
            } catch (\Throwable) {
                // Don't really care.
            }
        }

        return true;

    }

    /**
     * @param Asset|null $asset
     * @return MuxMateFieldAttributes|null
     */
    public static function getMuxMateFieldAttributes(?Asset $asset): ?MuxMateFieldAttributes
    {
        $muxMateFieldHandle = MuxMateHelper::_getMuxMateFieldForAsset($asset)?->handle;
        if (!$muxMateFieldHandle) {
            return null;
        }

        /** @var MuxMateFieldAttributes|null $muxMateFieldAttributes */
        $muxMateFieldAttributes = $asset->$muxMateFieldHandle ?? null;

        return $muxMateFieldAttributes;
    }

    /**
     * @param Asset|null $asset
     * @param array|null $attributes
     * @return bool
     */
    private static function _setMuxMateFieldAttributes(?Asset $asset, ?array $attributes = null): bool
    {

        $muxMateFieldHandle = MuxMateHelper::_getMuxMateFieldForAsset($asset)?->handle;

        if (!$muxMateFieldHandle) {
            return false;
        }

        $asset->setFieldValue($muxMateFieldHandle, $attributes);

        return true;
    }

    /**
     * @param Asset|null $asset
     * @return MuxMateField|null
     */
    private static function _getMuxMateFieldForAsset(?Asset $asset): ?MuxMateField
    {

        if ($asset?->kind !== Asset::KIND_VIDEO) {
            return null;
        }

        // Get the first MuxMate field for this asset
        try {

            $volumeHandle = $asset->getVolume()->handle;

            if (isset(MuxMateHelper::$_muxMateFieldsByVolume[$volumeHandle])) {
                return MuxMateHelper::$_muxMateFieldsByVolume[$volumeHandle];
            }

            /** @var MuxMateField|null $muxMateField */
            $muxMateField = Collection::make($asset->getFieldLayout()->getCustomFields())
                ->first(static fn(FieldInterface $field) => $field instanceof MuxMateField);

            MuxMateHelper::$_muxMateFieldsByVolume[$volumeHandle] = $muxMateField;

        } catch (\Throwable $e) {
            Craft::error($e, __METHOD__);
            return null;
        }

        return MuxMateHelper::$_muxMateFieldsByVolume[$volumeHandle] ?? null;

    }

    /**
     * @param Asset $asset
     * @return string|null
     * @throws \yii\base\InvalidConfigException
     */
    private static function _getAssetUrl(Asset $asset): ?string
    {
        $assetUrl = $asset->getUrl();

        // In case there is a proxy set up for enabling Mux to access assets locally
        $assetVolume = $asset->getVolume();
        $volumesConfig = MuxMate::getInstance()->getSettings()->volumes ?? [];
        $assetVolumeConfig = $volumesConfig[$assetVolume->handle] ?? $volumesConfig['*'] ?? null;

        if ($assetVolumeConfig) {
            /** @var VolumeSettings $assetVolumeSettings */
            $assetVolumeSettings = Craft::createObject([
                'class' => VolumeSettings::class,
                ...$assetVolumeConfig,
            ]);
            $assetBaseUrl = rtrim($assetVolumeSettings->baseUrl, '/');
            if ($assetBaseUrl) {
                $volumeBaseUrl = rtrim(App::parseEnv($assetVolume->getFs()->url), '/');
                $assetUrl = StringHelper::replaceBeginning($assetUrl, $volumeBaseUrl, $assetBaseUrl);
            }
        }

        return $assetUrl;
    }

}
