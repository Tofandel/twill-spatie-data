<?php

namespace Tofandel\TwillSpatieData;

use A17\Twill\Models\Behaviors\HasMedias;
use A17\Twill\Models\Block;
use A17\Twill\Models\Model;
use Spatie\LaravelData\DataPipes\DataPipe;
use Spatie\LaravelData\Lazy;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataClass;
use Tofandel\CTV\Traits\HasBlocks;

class TwillDataPipe implements DataPipe
{
    public function handle(mixed $payload, DataClass $class, array $properties, CreationContext $creationContext): array
    {
        /** @var HasMedias|HasBlocks|Model $payload */
        if ($payload instanceof Model) {
            foreach ($class->properties as $dataProperty) {
                if ($dataProperty->type->dataClass === ImageData::class) {
                    $getMedias = function () use ($payload, $dataProperty) {
                        $medias = $payload->medias->filter(fn ($media) => $media->pivot->role === $dataProperty->name);

                        return ! empty($dataProperty->type->dataCollectableClass) ? ImageData::collect($medias) : ImageData::optional($medias->first());
                    };
                    $properties[$dataProperty->name] = $dataProperty->type->lazyType ? Lazy::create($getMedias)->defaultIncluded() : $getMedias();
                }

                if ($dataProperty->type->dataClass === BlockData::class) {
                    $getBlocks = function () use ($payload, $dataProperty) {
                        /** @var \Illuminate\Database\Eloquent\Collection $blocks */
                        $blocks = $payload->blocks->where('editor_name', $dataProperty->name);
                        $blocks->loadMissing('medias', 'files', 'relatedItems');
                        $blocks = $blocks->whereNull('parent_id')->values()
                            ->map(fn (Block $block) => BlockData::getNestedBlockData($block, $blocks));

                        return BlockData::collect($blocks);
                    };
                    $properties[$dataProperty->name] = $dataProperty->type->lazyType ? Lazy::create($getBlocks)->defaultIncluded() : $getBlocks();
                }
            }
        }

        return $properties;
    }
}
