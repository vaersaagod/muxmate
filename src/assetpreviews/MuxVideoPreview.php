<?php

namespace vaersaagod\muxmate\assetpreviews;

use craft\assetpreviews\Video;
use craft\web\View;

use vaersaagod\muxmate\helpers\MuxMateHelper;
use vaersaagod\muxmate\MuxMate;

class MuxVideoPreview extends Video
{

    /**
     * @param array $variables
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    public function getPreviewHtml(array $variables = []): string
    {
        return \Craft::$app->getView()->renderTemplate('_muxmate/_mux-video-preview.twig', [
            'playbackId' => MuxMateHelper::getMuxPlaybackId($this->asset, MuxMateHelper::PLAYBACK_POLICY_PUBLIC),
            'playerUrl' => MuxMate::MUX_PLAYER_URL,
        ], View::TEMPLATE_MODE_CP);
    }

}
