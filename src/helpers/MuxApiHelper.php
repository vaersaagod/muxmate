<?php

namespace vaersaagod\muxmate\helpers;

use craft\helpers\UrlHelper;

use MuxPhp\Api\AssetsApi;
use MuxPhp\ApiException;
use MuxPhp\Configuration;
use MuxPhp\Models\Asset as MuxAsset;
use MuxPhp\Models\CreateAssetRequest;
use MuxPhp\Models\InputSettings;
use MuxPhp\Models\PlaybackPolicy;

use vaersaagod\muxmate\MuxMate;

class MuxApiHelper
{

    /** @var string */
    const MUX_STREAMING_DOMAIN = 'https://stream.mux.com';

    /** @var string */
    const MUX_IMAGE_DOMAIN = 'https://image.mux.com';

    /**
     * @param string $inputUrl The URL to the input asset
     * @return MuxAsset|null
     * @throws ApiException
     * @throws \Exception
     */
    public static function createAsset(string $inputUrl): ?MuxAsset
    {

        $apiClient = static::getApiClient();

        $input = new InputSettings(['url' => $inputUrl]);

        $createAssetRequest = new CreateAssetRequest([
            'input' => $input,
            'playback_policy' => [PlaybackPolicy::_PUBLIC],
            'mp4_support' => 'standard',
        ]);

        $result = $apiClient->createAsset($createAssetRequest);

        return $result->getData();

    }

    /**
     * @param string $muxAssetId
     * @return MuxAsset|null
     * @throws ApiException
     * @throws \Exception
     */
    public static function getAsset(string $muxAssetId): ?MuxAsset
    {
        $apiClient = static::getApiClient();
        return $apiClient->getAsset($muxAssetId)->getData();
    }

    /**
     * @param string $muxAssetId
     * @return void
     * @throws \MuxPhp\ApiException
     * @throws \Exception
     */
    public static function deleteAsset(string $muxAssetId): void
    {
        $apiClient = static::getApiClient();
        $apiClient->deleteAsset($muxAssetId);
    }

    /**
     * @param string $muxPlaybackId
     * @return string
     */
    public static function getStreamUrl(string $muxPlaybackId): string
    {
        return static::MUX_STREAMING_DOMAIN . "/$muxPlaybackId.m3u8";
    }

    /**
     * @param string $muxPlaybackId
     * @param string|null $quality
     * @param bool $download
     * @param string|null $filename
     * @return string
     */
    public static function getMp4Url(string $muxPlaybackId, ?string $quality = 'medium', bool $download = false, ?string $filename = null): string
    {
        $quality = $quality ?? 'medium';
        $url = static::MUX_STREAMING_DOMAIN . "/$muxPlaybackId" . "/$quality.mp4";
        if ($download) {
            $filename = $filename ?: $muxPlaybackId;
            $url .= "?download=$filename";
        }
        return $url;
    }

    /**
     * @param string $muxPlaybackId
     * @param array $params
     * @return string
     */
    public static function getImageUrl(string $muxPlaybackId, array $params = []): string
    {

        if (!isset($params['fit_mode'])) {
            if (isset($params['width']) && isset($params['height'])) {
                $params['fit_mode'] = 'smartcrop';
            } else {
                $params['fit_mode'] = 'preserve';
            }
        }

        return UrlHelper::url(static::MUX_IMAGE_DOMAIN . '/' . $muxPlaybackId . '/thumbnail.jpg', $params);

    }

    /**
     * @param string $muxPlaybackId
     * @param array $params
     * @return string|null
     */
    public static function getGifUrl(string $muxPlaybackId, array $params = []): ?string
    {
        return UrlHelper::url(static::MUX_IMAGE_DOMAIN . '/' . $muxPlaybackId . "/animated.gif", $params);
    }

    /**
     * @return AssetsApi
     * @throws \Exception
     */
    public static function getApiClient(): AssetsApi
    {

        $settings = MuxMate::getInstance()->getSettings();
        $muxAccessTokenId = $settings->muxAccessTokenId;
        $muxSecretKey = $settings->muxSecretKey;

        if (!$muxAccessTokenId) {
            throw new \Exception("No Mux access token ID");
        }

        if (!$muxSecretKey) {
            throw new \Exception("No Mux secret key");
        }

        // Authentication Setup
        $config = Configuration::getDefaultConfiguration()
            ->setUsername($muxAccessTokenId)
            ->setPassword($muxSecretKey);

        // API Client Initialization
        return new AssetsApi(
            \Craft::createGuzzleClient(),
            $config
        );
    }

}
