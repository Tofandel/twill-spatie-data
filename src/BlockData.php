<?php

namespace Tofandel\TwillSpatieData;

use A17\Twill\Facades\TwillBlocks;
use A17\Twill\Facades\TwillUtil;
use A17\Twill\Models\Block;
use A17\Twill\Models\File;
use A17\Twill\Models\Media;
use A17\Twill\Models\RelatedItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Contracts\BaseData;
use Spatie\LaravelData\Resource;

/** @see Block */
class BlockData extends Resource
{
    public function __construct(
        public readonly int $id,
        #[MapInputName('type')]
        public readonly string $name,
        public readonly BaseData|array $props,
        public readonly int $position,
    ) {}

    public static function getNestedBlockData(
        Block $block,
        ?Collection $allBlocks = null,
        ?Block $parentBlock = null,
    ): array|BaseData {
        if (! isset($allBlocks)) {
            if (! $block->relationLoaded('children')) {
                $allBlocks = Block::query()
                    ->where('blockable_id', $block->blockable_id)
                    ->where('blockable_type', $block->blockable_type)
                    ->where('editor_name', $block->editor_name)->get();
            } else {
                $children = $block->children;
            }
        }
        if (! isset($children)) {
            $children = $allBlocks->where('parent_id', $block->id); // ->sortBy('position') I think it's already sorted
        }

        if (! empty($children) && $parentBlock && TwillBlocks::findRepeaterByName($block->type)) {
            $parentForChild = $parentBlock;
        } else {
            $parentForChild = $block;
        }
        $children = $children->mapToDictionary(fn (Block $childBlock) => [$childBlock->child_key => self::getNestedBlockData(
            $childBlock,
            $allBlocks,
            $parentForChild,
        )]);

        $locale = app()->currentLocale();

        $content = collect($block->content)->except('browsers')
            ->map(function ($val) use ($locale) {
                $ret = is_array($val) && array_key_exists($locale, $val) ? $val[$locale] : $val;
                if (is_string($ret)) {
                    $ret = TwillUtil::parseInternalLinks($ret);
                }

                return $ret;
            })->all();

        $browsers = [];
        $files = [];
        $medias = [];
        if (! empty($block->content['browsers'])) {
            if (! str_starts_with($block->type, 'dynamic-repeater-')) {
                $twillBlock = TwillBlocks::findByName($block->type);
                $class = $twillBlock?->componentClass;
                $types = [];
                if (! empty($class)) {
                    $class = app($class);
                    if (property_exists($class, 'dataTypes')) {
                        $types = $class::$dataTypes;
                    } elseif (method_exists($class, 'getDataTypes')) {
                        $types = $class::getDataTypes();
                    }
                }
            }
            $configTypes = config('data.class_map');
            $browsers = $block->relatedItems->filter(fn (RelatedItem $item) => isset($item->related))->mapToDictionary(function (RelatedItem $item) use ($types, $configTypes) {
                $related = $item->related;
                if (isset($types[$item->browser_name][get_class($related)])) {
                    $related = $types[$item->browser_name][get_class($related)]::from($related);
                } elseif (isset($configTypes[get_class($related)])) {
                    $related = $configTypes[get_class($related)]::from($related);
                }

                return [$item->browser_name => $related];
            })->all();
        }
        if (! $block->files->isEmpty()) {
            $files = $block->files->mapToDictionary(function (File $file) {
                return [$file->pivot->role => FileData::from($file)];
            })->all();
        }
        if (! $block->medias->isEmpty()) {
            $medias = $block->medias->mapToDictionary(function (Media $file) {
                return [$file->pivot->role => ImageData::from($file)];
            })->all();
        }

        $props = $content + $browsers + $files + $medias + $children->all();
        if ($parentBlock && ((str_starts_with($block->type, 'dynamic-repeater-') && $name = 'dynamic-'.Str::after($block->type, 'dynamic-repeater-'))
                || ($name = TwillBlocks::findRepeaterByName($block->type)?->name))) {
            $props = ['id' => $block->id] + $props;
            if ($dataClass = config('data.repeaters_map.'.$parentBlock->type.'.'.$name)) {
                return $dataClass::from($props);
            }

            return $props;
        } elseif ($dataClass = config('data.blocks_map.'.$block->type)) {
            $props = $dataClass::from(['block' => $block] + $props);
        }

        return BlockData::from($block, [
            'props' => $props,
        ]);
    }

    public static function fromModel(Block $block): BlockData|array
    {
        return self::getNestedBlockData($block);
    }
}
