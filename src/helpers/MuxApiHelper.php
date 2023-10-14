<?php

namespace vaersaagod\muxmate\helpers;

use MuxPhp\Api\AssetsApi;
use MuxPhp\ApiException;
use MuxPhp\Configuration;
use MuxPhp\Models\Asset as MuxAsset;
use MuxPhp\Models\CreateAssetRequest;
use MuxPhp\Models\CreatePlaybackIDRequest;
use MuxPhp\Models\InputSettings;
use MuxPhp\Models\PlaybackID;
use MuxPhp\Models\PlaybackPolicy;

use vaersaagod\muxmate\MuxMate;

final class MuxApiHelper
{

    /**
     * @param string $inputUrl The URL to the input asset
     * @return MuxAsset|null
     * @throws ApiException
     * @throws \Exception
     */
    public static function createAsset(string $inputUrl): ?MuxAsset
    {

        $apiClient = MuxApiHelper::getApiClient();

        $input = new InputSettings(['url' => $inputUrl]);

        $createAssetRequest = new CreateAssetRequest([
            'input' => $input,
            'mp4_support' => 'standard',
            'playback_policy' => [PlaybackPolicy::_PUBLIC, PlaybackPolicy::SIGNED],
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
        $apiClient = MuxApiHelper::getApiClient();
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
        $apiClient = MuxApiHelper::getApiClient();
        $apiClient->deleteAsset($muxAssetId);
    }

    /**
     * @param string $muxAssetId
     * @param string $playbackId
     * @return void
     * @throws ApiException
     * @throws \Exception
     */
    public static function deletePlaybackId(string $muxAssetId, string $playbackId): void
    {
        $apiClient = MuxApiHelper::getApiClient();
        $apiClient->deleteAssetPlaybackId($muxAssetId, $playbackId);
    }

    /**
     * @param string $muxAssetId
     * @param string $policy
     * @return PlaybackID|null
     * @throws ApiException
     * @throws \Exception
     */
    public static function createPlaybackId(string $muxAssetId, string $policy): ?PlaybackID
    {
        $apiClient = MuxApiHelper::getApiClient();
        $createPlaybackIdRequest = new CreatePlaybackIDRequest([
            'policy' => $policy,
        ]);
        return $apiClient
            ->createAssetPlaybackId($muxAssetId, $createPlaybackIdRequest)
            ->getData();
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
