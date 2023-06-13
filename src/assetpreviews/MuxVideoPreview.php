<?php

namespace vaersaagod\muxmate\assetpreviews;

use craft\assetpreviews\Video;
use craft\web\View;

use vaersaagod\muxmate\helpers\MuxMateHelper;

class MuxVideoPreview extends Video
{

    /**
     * @param array $variables
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \yii\base\Exception
     * @throws \yii\base\NotSupportedException
     */
    public function getPreviewHtml(array $variables = []): string
    {
        if (!MuxMateHelper::getMuxPlaybackId($this->asset)) {
            return parent::getPreviewHtml();
        }
        return \Craft::$app->getView()->renderTemplate('_muxmate/_mux-video-preview.twig', [
            'asset' => $this->asset,
        ], View::TEMPLATE_MODE_CP);
    }

}
