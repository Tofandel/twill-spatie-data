<?php

namespace Tofandel\CTV\Data;

use A17\Twill\Models\Block;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Resource;

/** @see Block */
class BlockData extends Resource
{
    public function __construct(
        public readonly int $id,
        #[MapInputName('type')]
        public readonly string $name,
        public readonly array $props,
        public readonly int $position,
    ) {

    }
}
