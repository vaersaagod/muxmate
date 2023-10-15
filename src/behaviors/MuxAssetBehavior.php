<?php

namespace vaersaagod\muxmate\behaviors;

use craft\elements\Asset;
use craft\helpers\Template;
use craft\web\View;

use Twig\Markup;

use vaersaagod\muxmate\MuxMate;
use vaersaagod\muxmate\helpers\SignedUrlsHelper;
use vaersaagod\muxmate\models\MuxPlaybackId;
use vaersaagod\muxmate\helpers\MuxMateHelper;

use yii\base\Behavior;

class MuxAssetBehavior extends Behavior
{


    /**
     * @return bool
     */
    public function isMuxVideo(): bool
    {
        return !empty($this->getMuxAssetId());
    }

    /**
     * @return string|null
     */
    public function getMuxStatus(): ?string
    {
        return MuxMateHelper::getMuxStatus($this->owner);
    }

    /**
     * @return bool
     */
    public function isMuxVideoReady(): bool
    {
        return $this->getMuxStatus() === 'ready';
    }

    /**
     * @param array|null $params Mux params – so far only `max_resolution` is supported
     * @param string|null $policy "signed" or "public"
     * @return string|null
     * @throws \yii\base\InvalidConfigException
     */
    public function getMuxStreamUrl(?array $params = null, ?string $policy = null): ?string
    {
        return MuxMateHelper::getMuxStreamUrl($this->owner, $params, $policy);
    }

    /**
     * @param array|null $options "inline", "lazyload"
     * @param array|null $params Mux params – so far only `max_resolution` is supported
     * @param string|null $policy "signed" or "public"
     * @return string|Markup
     */
    public function getMuxVideo(?array $options = null, ?array $params = null, ?string $policy = null): string|Markup
    {
        return MuxMateHelper::getMuxVideoTag($this->owner, $options, $params, $policy) ?? '';
    }

    /**
     * @param string|null $quality "high", "medium" or "low"
     * @param string|null $policy "public" or "signed"
     * @param bool $download
     * @param string|null $filename
     * @return string|null
     * @throws \yii\base\InvalidConfigException
     */
    public function getMuxMp4Url(?string $quality = null, ?string $policy = null, bool $download = false, ?string $filename = null): ?string
    {
        return MuxMateHelper::getMuxMp4Url($this->owner, $quality, $policy, $download, $filename);
    }

    /**
     * See https://docs.mux.com/guides/video/get-images-from-a-video for params
     * @param array|null $params
     * @param string|null $policy
     * @return string|null
     * @throws \yii\base\InvalidConfigException
     */
    public function getMuxImageUrl(?array $params = null, ?string $policy = null): ?string
    {
        return MuxMateHelper::getMuxImageUrl($this->owner, $params, $policy);
    }

    /**
     * See https://docs.mux.com/guides/video/get-images-from-a-video#get-an-animated-gif-from-a-video for params
     *
     * @param array|null $params
     * @param string|null $policy
     * @return string|null
     * @throws \yii\base\InvalidConfigException
     */
    public function getMuxGifUrl(?array $params = null, ?string $policy = null): ?string
    {
        return MuxMateHelper::getMuxGifUrl($this->owner, $params, $policy);
    }

    /**
     * @return float|null
     */
    public function getMuxVideoDuration(): ?float
    {
        return MuxMateHelper::getMuxVideoDuration($this->owner);
    }

    /**
     * @return array|null
     */
    public function getStaticRenditions(): ?array
    {
        return MuxMateHelper::getStaticRenditions($this->owner);
    }

    /**
     * @return string|null
     */
    public function getMuxAssetId(): ?string
    {
        if (!$this->owner instanceof Asset) {
            return null;
        }
        return MuxMateHelper::getMuxAssetId($this->owner);
    }

    /**
     * @param string|null $policy
     * @return string|null
     * @throws \yii\base\InvalidConfigException
     */
    public function getMuxPlaybackId(?string $policy = null): ?string
    {
        if (!$this->owner instanceof Asset) {
            return null;
        }
        return MuxMateHelper::getMuxPlaybackId($this->owner, $policy);
    }

    /**
     * @return array|null
     */
    public function getMuxData(): ?array
    {
        if (!$this->owner instanceof Asset) {
            return null;
        }
        return MuxMateHelper::getMuxData($this->owner);
    }

    /**
     * @return float|int|null
     */
    public function getMuxAspectRatio(): float|int|null
    {
        if (!$this->owner instanceof Asset) {
            return null;
        }

        $data = MuxMateHelper::getMuxData($this->owner);
        if (empty($data)) {
            return null;
        }

        $aspectRatio = $data['aspect_ratio'] ?? null;
        if (empty($aspectRatio) || !is_string($aspectRatio)) {
            return null;
        }

        $temp = array_map('intval', explode(':', $aspectRatio));
        $width = $temp[0] ?? null;
        $height = $temp[1] ?? null;

        if (!$width || !$height) {
            return null;
        }

        return $width / $height;

    }

}
