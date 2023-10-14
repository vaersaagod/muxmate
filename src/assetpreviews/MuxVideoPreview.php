<?php

namespace vaersaagod\muxmate\assetpreviews;

use craft\assetpreviews\Video;
use craft\web\View;

use vaersaagod\muxmate\helpers\MuxMateHelper;
use vaersaagod\muxmate\helpers\SignedUrlsHelper;
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
        $playbackId = MuxMateHelper::getMuxPlaybackId($this->asset);
        if ($playbackId?->policy === MuxMateHelper::PLAYBACK_POLICY_SIGNED) {
            $playbackToken = SignedUrlsHelper::getToken($playbackId, SignedUrlsHelper::SIGNED_URL_AUDIENCE_VIDEO, null, MuxMateHelper::getMuxVideoDuration($this->asset));
            $thumbnailToken = SignedUrlsHelper::getToken($playbackId, SignedUrlsHelper::SIGNED_URL_AUDIENCE_THUMBNAIL);
            $storyboardToken = SignedUrlsHelper::getToken($playbackId, SignedUrlsHelper::SIGNED_URL_AUDIENCE_STORYBOARD);
        }
        return \Craft::$app->getView()->renderTemplate('_muxmate/_mux-video-preview.twig', [
            'playbackId' => $playbackId,
            'playbackToken' => $playbackToken ?? null,
            'thumbnailToken' => $thumbnailToken ?? null,
            'storyboardToken' => $storyboardToken ?? null,
            'playerUrl' => MuxMate::MUX_PLAYER_URL,
        ], View::TEMPLATE_MODE_CP);
    }

}
