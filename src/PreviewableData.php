<?php

namespace Tofandel\TwillSpatieData;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Lazy;
use Spatie\LaravelData\Resource;

class PreviewableData extends Resource
{
    use HasTwillData;

    public ?string $title;

    public ?string $slug;

    public Lazy|ImageData|null $thumbnail = null;

    //public ?string $type = null;

    public ?string $short_description = null;

    #[MapInputName('publish_start_date')]
    public ?CarbonImmutable $published_at = null;

    public function __construct(
    ) {

    }
}
