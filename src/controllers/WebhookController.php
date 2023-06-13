<?php

namespace vaersaagod\muxmate\controllers;

use craft\elements\Asset;
use craft\helpers\Json;
use craft\web\Controller;
use vaersaagod\muxmate\fields\MuxMateField;
use vaersaagod\muxmate\helpers\MuxMateHelper;
use yii\web\BadRequestHttpException;

class WebhookController extends Controller
{

    public array|bool|int $allowAnonymous = true;

    public $enableCsrfValidation = false;

    public function actionIndex(): bool
    {

        $this->requirePostRequest();

        $webhookJson = $this->request->getRawBody();

        if (empty($webhookJson)) {
            throw new BadRequestHttpException();
        }

        $webhookData = Json::decode($webhookJson);
        $event = $webhookData['type'] ?? null;

        \Craft::info("MuxMate webhook triggered. Event type is \"$event\"", __METHOD__);

        if (!in_array($event, [
            'video.asset.ready',
            'video.asset.updated',
            'video.asset.static_renditions.ready',
            'video.asset.deleted',
        ])) {
            return true;
        }

        $muxAssetId = $webhookData['object']['id'];

        // Get the asset
        // This is a bit awkward, because we have to go via the MuxMate fields (we can't know which field to query on, in cases where there are multiple volumes w/ multiple different MuxMate fields in their layouts
        $asset = null;
        $muxMateFields = \Craft::$app->getFields()->getFieldsByType(MuxMateField::class, 'global');
        foreach ($muxMateFields as $muxMateField) {
            $muxMateFieldHandle = $muxMateField->handle;
            $asset = Asset::find()
                ->kind(Asset::KIND_VIDEO)
                ->$muxMateFieldHandle([
                    'muxAssetId' => $muxAssetId,
                ])
                ->one();
            if ($asset) {
                break;
            }
        }

        if (!$asset) {
            return true;
        }

        switch ($event) {
            case 'video.asset.ready':
            case 'video.asset.updated':
            case 'video.asset.static_renditions.ready':
                $muxAssetData = $webhookData['data'];
                $muxPlaybackId = $muxAssetData['playback_ids'][0]['id'] ?? '';
                MuxMateHelper::saveMuxAttributesToAsset($asset, [
                    'muxAssetId' => $muxAssetId,
                    'muxPlaybackId' => $muxPlaybackId,
                    'muxMetaData' => $muxAssetData,
                ]);
                break;
            case 'video.asset.deleted':
                MuxMateHelper::deleteMuxAttributesForAsset($asset, false);
                break;
        }

        return true;

    }
}
