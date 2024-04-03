<?php

namespace Tofandel\TwillSpatieData;

use A17\Twill\Facades\TwillBlocks;
use A17\Twill\Models\Behaviors\HasMedias;
use A17\Twill\Models\Block;
use A17\Twill\Models\Contracts\TwillModelContract;
use A17\Twill\Models\File;
use A17\Twill\Models\Model;
use A17\Twill\Models\RelatedItem;
use Illuminate\Support\Collection;
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
                        $blocks = $blocks->whereNull('parent_id')->values()
                            ->map(fn (Block $block) => $this->getNestedBlockData($block, $payload, $dataProperty->name, $blocks));

                        return BlockData::collect($blocks);
                    };
                    $properties[$dataProperty->name] = $dataProperty->type->lazyType ? Lazy::create($getBlocks)->defaultIncluded() : $getBlocks();
                }
            }
        }

        return $properties;
    }

    protected function getNestedBlockData(
        Block $block,
        TwillModelContract $rootModel,
        string $editorName,
        Collection $allBlocks,
    ): BlockData|array {
        $children = $allBlocks->where('parent_id', $block->id) //->sortBy('position') I think it's already sorted
            ->mapToDictionary(fn (Block $block) => [$block->child_key => $this->getNestedBlockData(
                $block,
                $rootModel,
                $editorName,
                $allBlocks,
            )]);

        $locale = app()->currentLocale();

        $content = collect($block->content)->except('browsers')
            ->map(fn ($val) => is_array($val) && array_key_exists($locale, $val) ? $val[$locale] : $val)->all();

        if (str_starts_with($block->type, 'dynamic-repeater-')) {
            return ['id' => $block->id] + $content + $children->all();
        }

        $browsers = [];
        $files = [];
        if (! empty($block->content['browsers'])) {
            $twillBlock = TwillBlocks::findByName($block->type);
            $class = $twillBlock?->componentClass;
            $types = ! empty($class) && property_exists($class, 'dataTypes') ? $class::$dataTypes : [];
            $browsers = $block->relatedItems->mapToDictionary(function (RelatedItem $item) use ($types) {
                $related = $item->related;
                if (isset($types[$item->browser_name][get_class($related)])) {
                    $related = $types[$item->browser_name][get_class($related)]::from($related);
                }

                return [$item->browser_name => $related];
            })->all();
        }
        if (! $block->files->isEmpty()) {
            $files = $block->files->mapToDictionary(function (File $file) {
                return [$file->pivot->role => FileData::from($file)];
            })->all();
        }

        return BlockData::from($block, [
            'props' => $content + $browsers + $files + $children->all(),
        ]);
    }
}
