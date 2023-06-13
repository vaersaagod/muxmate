<?php

namespace vaersaagod\muxmate\controllers;

use craft\elements\Asset;
use craft\web\Controller;

use vaersaagod\muxmate\helpers\MuxMateHelper;

use yii\web\NotFoundHttpException;
use yii\web\Response;

class DefaultController extends Controller
{

    /**
     * @return Response|null
     * @throws NotFoundHttpException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionCreate(): ?Response
    {

        $assetId = $this->request->getRequiredBodyParam('assetId');

        if (!$asset = \Craft::$app->getAssets()->getAssetById($assetId)) {
            throw new NotFoundHttpException();
        }

        if ($asset->kind !== Asset::KIND_VIDEO) {
            return $this->asFailure(message: \Craft::t('_muxmate', 'This asset is not a video.'));
        }

        if (MuxMateHelper::getMuxAssetId($asset)) {
            MuxMateHelper::deleteMuxAttributesForAsset($asset);
        }

        if (!MuxMateHelper::updateOrCreateMuxAsset($asset)) {
            return $this->asFailure(message: \Craft::t('_muxmate', 'Unable to create Mux asset.'));
        }

        return $this->asSuccess(message: \Craft::t('_muxmate', 'Mux asset created.'));

    }

    /**
     * @return Response|null
     * @throws NotFoundHttpException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionDelete(): ?Response
    {

        $assetId = $this->request->getRequiredBodyParam('assetId');

        if (!$asset = \Craft::$app->getAssets()->getAssetById($assetId)) {
            throw new NotFoundHttpException();
        }

        if ($asset->kind !== Asset::KIND_VIDEO) {
            return $this->asFailure(message: \Craft::t('_muxmate', 'This asset is not a video.'));
        }

        if (!MuxMateHelper::deleteMuxAttributesForAsset($asset)) {
            return $this->asFailure(message: \Craft::t('_muxmate', 'Failed to delete Mux asset for video.'));
        }

        return $this->asSuccess(message: \Craft::t('_muxmate', 'Mux asset deleted.'));

    }

    /**
     * @return Response|null
     * @throws NotFoundHttpException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionUpdateData(): ?Response
    {
        $assetId = $this->request->getRequiredBodyParam('assetId');

        if (!$asset = \Craft::$app->getAssets()->getAssetById($assetId)) {
            throw new NotFoundHttpException();
        }

        if ($asset->kind !== Asset::KIND_VIDEO) {
            return $this->asFailure(message: \Craft::t('_muxmate', 'This asset is not a video.'));
        }

        if (!MuxMateHelper::updateOrCreateMuxAsset($asset)) {
            return $this->asFailure(message: \Craft::t('_muxmate', 'Unable to update Mux data.'));
        }

        return $this->asSuccess(message: \Craft::t('_muxmate', 'Mux data updated.'));

    }

}
