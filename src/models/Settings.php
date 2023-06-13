<?php

namespace vaersaagod\muxmate\models;

use craft\base\Model;
use craft\helpers\App;

/**
 * MuxMate settings
 */
class Settings extends Model
{

    /** @var string The default URL to the `<mux-player>` web component */
    public const MUX_PLAYER_URL = 'https://cdn.jsdelivr.net/npm/@mux/mux-player';

    /** @var string The default URL to the `<mux-video>` web component */
    public const MUX_VIDEO_URL = 'https://cdn.jsdelivr.net/npm/@mux/mux-video@0';

    public ?string $muxAccessTokenId = null;

    public ?string $muxSecretKey = null;

    public ?string $muxPlayerUrl = null;

    public ?string $muxVideoUrl = null;

    public ?array $volumes = null;

    public function setAttributes($values, $safeOnly = true): void
    {
        $values['muxPlayerUrl'] = App::parseEnv($values['muxPlayerUrl']) ?: static::MUX_PLAYER_URL;
        $values['muxVideoUrl'] = App::parseEnv($values['muxVideoUrl']) ?: static::MUX_VIDEO_URL;
        parent::setAttributes($values, $safeOnly);
    }

}
