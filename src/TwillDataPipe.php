<?php

namespace Tofandel\TwillSpatieData;

use A17\Twill\Facades\TwillUtil;
use A17\Twill\Models\Behaviors\HasBlocks;
use A17\Twill\Models\Behaviors\HasMedias;
use A17\Twill\Models\Block;
use A17\Twill\Models\File;
use A17\Twill\Models\Media;
use A17\Twill\Models\Model;
use Spatie\LaravelData\Attributes\LoadRelation;
use Spatie\LaravelData\DataPipes\DataPipe;
use Spatie\LaravelData\Lazy;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataClass;
use Spatie\LaravelData\Support\DataProperty;

class TwillDataPipe implements DataPipe
{
    public function handle(mixed $payload, DataClass $class, array $properties, CreationContext $creationContext): array
    {
        /** @var HasMedias|HasBlocks|Model $payload */
        if ($payload instanceof Model) {
            foreach ($class->properties as $dataProperty) {
                /** @var DataProperty $dataProperty */
                if ($dataProperty->attributes->has(Wysiwyg::class)) {
                    $properties[$dataProperty->outputMappedName ?? $dataProperty->name] = TwillUtil::parseInternalLinks($payload->{$dataProperty->inputMappedName ?? $dataProperty->name});
                }
                if ($dataProperty->type->dataClass === ImageData::class) {
                    $getMedias = function () use ($payload, $dataProperty) {
                        $translate = $dataProperty->attributes->has(TranslatableMedia::class);
                        $locale = app()->getLocale();
                        $medias = $payload->medias->filter(fn (Media $media) => $media->pivot->role === ($dataProperty->inputMappedName ?? $dataProperty->name) && (! $translate || $media->pivot->locale === $locale));
                        if ($translate && $medias->isEmpty() && config('translatable.use_property_fallback', false)) {
                            $medias = $payload->medias->filter(fn (Media $media) => $media->pivot->role === ($dataProperty->inputMappedName ?? $dataProperty->name) && $media->pivot->locale === config('translatable.fallback_locale'));
                        }

                        return ! empty($dataProperty->type->dataCollectableClass) ? ImageData::collect($medias) : ImageData::optional($medias->first());
                    };
                    $properties[$dataProperty->outputMappedName ?? $dataProperty->name] = $dataProperty->type->lazyType ? Lazy::create($getMedias)->defaultIncluded($dataProperty->attributes->has(LoadRelation::class)) : $getMedias();
                }

                if ($dataProperty->type->dataClass === FileData::class) {
                    $getFiles = function () use ($payload, $dataProperty) {
                        $translate = $dataProperty->attributes->has(TranslatableMedia::class);
                        $locale = app()->getLocale();
                        $medias = $payload->files->filter(fn (File $file) => $file->pivot->role === ($dataProperty->inputMappedName ?? $dataProperty->name) && (! $translate || $file->pivot->locale === $locale));
                        if ($translate && $medias->isEmpty() && config('translatable.use_property_fallback', false)) {
                            $medias = $payload->files->filter(fn (File $file) => $file->pivot->role === ($dataProperty->inputMappedName ?? $dataProperty->name) && $file->pivot->locale === config('translatable.fallback_locale'));
                        }

                        return ! empty($dataProperty->type->dataCollectableClass) ? FileData::collect($medias) : FileData::optional($medias->first());
                    };
                    $properties[$dataProperty->outputMappedName ?? $dataProperty->name] = $dataProperty->type->lazyType ? Lazy::create($getFiles)->defaultIncluded($dataProperty->attributes->has(LoadRelation::class)) : $getFiles();
                }

                if ($dataProperty->type->dataClass === BlockData::class) {
                    $getBlocks = function () use ($payload, $dataProperty) {
                        /** @var \Illuminate\Database\Eloquent\Collection $blocks */
                        $blocks = $payload->blocks->where('editor_name', $dataProperty->inputMappedName ?? $dataProperty->name);
                        $blocks->loadMissing('medias', 'files', 'relatedItems');
                        $blocks = $blocks->whereNull('parent_id')->values()
                            ->map(fn (Block $block) => BlockData::getNestedBlockData($block, $blocks));

                        return BlockData::collect($blocks);
                    };
                    $properties[$dataProperty->outputMappedName ?? $dataProperty->name] = $dataProperty->type->lazyType ? Lazy::create($getBlocks)->defaultIncluded($dataProperty->attributes->has(LoadRelation::class)) : $getBlocks();
                }
            }
        }

        return $properties;
    }
}
