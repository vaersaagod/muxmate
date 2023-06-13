<?php

namespace vaersaagod\muxmate\models;

use craft\base\Model;

class VolumeSettings extends Model
{

    /** @var string|null Override the base URL for a volume (useful if assets are local, and proxied via Ngrok or similar to provide Mux access to them */
    public ?string $baseUrl = null;

}
