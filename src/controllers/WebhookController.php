<?php

namespace vaersaagod\muxmate\controllers;

use Craft;
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

    /**
     * @return bool
     * @throws BadRequestHttpException
     */
    public function actionIndex(): bool
    {

        $this->requirePostRequest();

        $webhookJson = $this->request->getRawBody();

        if (empty($webhookJson)) {
            throw new BadRequestHttpException();
        }

        $webhookData = Json::decode($webhookJson);
        $event = $webhookData['type'] ?? null;

        Craft::info("MuxMate webhook triggered. Event type is \"$event\"", __METHOD__);

        // Check if the event is something we support
        if (!in_array($event, [
            'video.asset.created',
            'video.asset.ready',
            'video.asset.updated',
            'video.asset.static_renditions.ready',
            'video.asset.deleted',
        ])) {
            return true;
        }

        $muxAssetId = $webhookData['object']['id'] ?? null;

        if (!$muxAssetId) {
            Craft::info("Webhook failed, no Mux asset ID found in payload for event \"$event\"", __METHOD__);
            return false;
        }

        // Get the asset
        // This is a bit awkward, because we have to go via the MuxMate fields (we can't know which field to query on, in cases where there are multiple volumes w/ multiple different MuxMate fields in their layouts
        $asset = null;
        $muxMateFields = Craft::$app->getFields()->getFieldsByType(MuxMateField::class, 'global');
        foreach ($muxMateFields as $muxMateField) {
            $muxMateFieldHandle = $muxMateField->handle;
            $asset = Asset::find()
                ->kind(Asset::KIND_VIDEO)
                ->$muxMateFieldHandle([
                    'id' => $muxAssetId,
                ])
                ->one();
            if ($asset) {
                break;
            }
        }

        if (!$asset) {
            Craft::error("Webhook \"$event\" failed – no asset found for the Mux asset ID \"$muxAssetId\"", __METHOD__);
            return false;
        }

        Craft::info("Found asset \"$asset->id\" for Mux asset ID \"$muxAssetId\"", __METHOD__);

        $success = false;

        switch ($event) {
            case 'video.asset.created':
            case 'video.asset.updated':
            case 'video.asset.ready':
            case 'video.asset.static_renditions.ready':
                $muxAssetData = $webhookData['data'] ?? null;
                $success = MuxMateHelper::saveMuxAttributesToAsset($asset, [
                    'muxAssetId' => $muxAssetId,
                    'muxMetaData' => $muxAssetData,
                ]);
                if (!$success) {
                    Craft::error("Failed to save Mux attributes for asset \"$asset->id\" via webhook.", __METHOD__);
                }
                break;
            case 'video.asset.deleted':
                $success = MuxMateHelper::deleteMuxAttributesForAsset($asset, false);
                if (!$success) {
                    Craft::error("Failed to delete Mux attributes for asset \"$asset->id\" via webhook.", __METHOD__);
                }
                break;
        }

        return $success;

    }
}
