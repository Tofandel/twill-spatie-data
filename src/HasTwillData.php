<?php

namespace Tofandel\TwillSpatieData;

use Spatie\LaravelData\DataPipeline;

trait HasTwillData
{
    public static function pipeline(): DataPipeline
    {
        return parent::pipeline()->firstThrough(TwillDataPipe::class);
    }
}
