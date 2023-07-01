<?php

namespace vaersaagod\muxmate\models;

use craft\base\Model;
use craft\helpers\App;

use vaersaagod\muxmate\MuxMate;

/**
 * MuxMate settings
 */
class Settings extends Model
{

    public ?string $muxAccessTokenId = null;

    public ?string $muxSecretKey = null;

    public string|bool|null $muxPlayerUrl = null;

    public string|bool|null $muxVideoUrl = null;

    public bool $lazyloadMuxVideo = false;

    public ?string $scriptSrcNonce = null;

    public ?array $volumes = null;

    public function setAttributes($values, $safeOnly = true): void
    {
        $values['muxPlayerUrl'] = $values['muxPlayerUrl'] ?? null;
        if ($values['muxPlayerUrl'] !== false) {
            $values['muxPlayerUrl'] = App::parseEnv($values['muxPlayerUrl']) ?: MuxMate::MUX_PLAYER_URL;
        }
        $values['muxVideoUrl'] = $values['muxVideoUrl'] ?? null;
        if ($values['muxVideoUrl'] !== false) {
            $values['muxVideoUrl'] = App::parseEnv($values['muxVideoUrl']) ?: MuxMate::MUX_VIDEO_URL;
        }
        parent::setAttributes($values, $safeOnly);
    }

}
