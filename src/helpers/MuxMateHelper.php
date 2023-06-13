<?php

namespace vaersaagod\muxmate\helpers;

use craft\base\Element;
use craft\base\FieldInterface;
use craft\elements\Asset;
use craft\helpers\App;
use craft\helpers\Json;
use craft\helpers\StringHelper;

use Illuminate\Support\Collection;

use vaersaagod\muxmate\fields\MuxMateField;
use vaersaagod\muxmate\models\MuxMateFieldAttributes;
use vaersaagod\muxmate\models\VolumeSettings;
use vaersaagod\muxmate\MuxMate;

class MuxMateHelper
{

    /** @var MuxMateField[] */
    private static array $_muxMateFieldsByVolume = [];

    /**
     * @param Asset|null $asset
     * @return string|null
     */
    public static function getMuxAssetId(?Asset $asset): ?string
    {
        return static::getMuxMateFieldAttributes($asset)?->muxAssetId;
    }

    /**
     * @param Asset|null $asset
     * @return string|null
     */
    public static function getMuxPlaybackId(?Asset $asset): ?string
    {
        return static::getMuxMateFieldAttributes($asset)?->muxPlaybackId;
    }

    /**
     * @param Asset|null $asset
     * @return string|null
     */
    public static function getMuxStatus(?Asset $asset): ?string
    {
        $data = static::getMuxMateFieldAttributes($asset)?->muxMetaData;
        if (!$data) {
            return null;
        }
        return $data['status'] ?? null;
    }

    /**
     * @param Asset|null $asset
     * @return array|null
     */
    public static function getMuxData(?Asset $asset): ?array
    {
        return static::getMuxMateFieldAttributes($asset)?->muxMetaData;
    }

    /**
     * @param Asset|null $asset
     * @return bool
     */
    public static function updateOrCreateMuxAsset(?Asset $asset): bool
    {

        $muxAssetId = static::getMuxAssetId($asset);

        if ($muxAssetId) {
            // Get existing Mux asset
            try {
                $muxAsset = MuxApiHelper::getAsset($muxAssetId);
            } catch (\Throwable $e) {
                \Craft::error($e, __METHOD__);
                $muxAsset = null;
            }
        } else {
            $muxAsset = null;
        }

        if (!$muxAsset) {

            // Create a new Mux asset
            try {
                $assetUrl = static::_getAssetUrl($asset);
                if (!$assetUrl) {
                    throw new \Exception("Asset ID \"$asset->id\" has no URL");
                }
                $muxAsset = MuxApiHelper::createAsset($assetUrl);
            } catch (\Throwable $e) {
                \Craft::error($e, __METHOD__);
            }
        }

        if (!$muxAsset) {
            // Still no Mux asset; make sure the data on the Craft asset is wiped out and bail
            static::deleteMuxAttributesForAsset($asset);
            return false;
        }

        return static::saveMuxAttributesToAsset($asset, [
            'muxAssetId' => $muxAsset->getId(),
            'muxPlaybackId' => $muxAsset->getPlaybackIds()[0]['id'] ?? null,
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

        if (!static::_setMuxMateFieldAttributes($asset, $attributes)) {
            return false;
        }

        $asset->setScenario(Element::SCENARIO_ESSENTIALS);
        $asset->resaving = true;

        try {
            $success = \Craft::$app->getElements()->saveElement($asset, false);
        } catch (\Throwable $e) {
            \Craft::error($e, __METHOD__);
            return false;
        }

        if (!$success) {
            \Craft::error("Unable to save Mux attributes to asset: " . Json::encode($asset->getErrors()), __METHOD__);
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

        $muxAssetId = static::getMuxMateFieldAttributes($asset)?->muxAssetId;

        if (!$muxAssetId) {
            return false;
        }

        static::_setMuxMateFieldAttributes($asset, null);

        $asset->setScenario(Element::SCENARIO_ESSENTIALS);
        $asset->resaving = true;

        try {
            $success = \Craft::$app->getElements()->saveElement($asset, false);
        } catch (\Throwable $e) {
            \Craft::error($e, __METHOD__);
            return false;
        }

        if (!$success) {
            \Craft::error("Unable to delete Mux attributes for asset: " . Json::encode($asset->getErrors()));
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
        $muxMateFieldHandle = static::_getMuxMateFieldForAsset($asset)?->handle;
        if (!$muxMateFieldHandle) {
            return false;
        }

        /** @var MuxMateFieldAttributes|null $muxMateFieldAttributes */
        $muxMateFieldAttributes = $asset->$muxMateFieldHandle ?? null;

        return $muxMateFieldAttributes;
    }

    /**
     * @param Asset|null $asset
     * @param array|null $attributes
     * @return void
     */
    private static function _setMuxMateFieldAttributes(?Asset $asset, ?array $attributes = null): bool
    {

        $muxMateFieldHandle = static::_getMuxMateFieldForAsset($asset)?->handle;

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

            if (isset(static::$_muxMateFieldsByVolume[$volumeHandle])) {
                return static::$_muxMateFieldsByVolume[$volumeHandle];
            }

            /** @var FieldInterface|null $muxMateField */
            $muxMateField = Collection::make($asset->getFieldLayout()->getCustomFields())
                ->first(static fn(FieldInterface $field) => $field instanceof MuxMateField);

            static::$_muxMateFieldsByVolume[$volumeHandle] = $muxMateField;

        } catch (\Throwable $e) {
            \Craft::error($e, __METHOD__);
            return null;
        }

        return static::$_muxMateFieldsByVolume[$volumeHandle] ?? null;

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
            $assetVolumeSettings = \Craft::createObject([
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
