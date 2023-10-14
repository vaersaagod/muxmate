<?php

namespace vaersaagod\muxmate\models;

use craft\base\Model;

class MuxPlaybackId extends Model
{

    public ?string $id = null;

    public ?string $policy = null;

    public function __toString(): string
    {
        return $this->id ?: '';
    }

    // TODO validate

}
