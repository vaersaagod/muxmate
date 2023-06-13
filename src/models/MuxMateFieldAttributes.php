<?php

namespace vaersaagod\muxmate\models;

use craft\base\Model;

class MuxMateFieldAttributes extends Model
{
    public ?string $muxAssetId = null;
    public ?string $muxPlaybackId = null;
    public ?array $muxMetaData = null;
}
