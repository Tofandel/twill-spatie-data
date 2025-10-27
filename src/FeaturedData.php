<?php

namespace Tofandel\TwillSpatieData;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Resource;

class FeaturedData extends Resource
{
    public function __construct(
        #[MapInputName('featured_type')]
        public readonly ?string $type = null,
        #[MapInputName('featured')]
        public readonly ?PreviewableData $data = null,
    ) {}
}
