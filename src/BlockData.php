<?php

namespace Tofandel\TwillSpatieData;

use A17\Twill\Facades\TwillBlocks;
use A17\Twill\Facades\TwillUtil;
use A17\Twill\Models\Block;
use A17\Twill\Models\File;
use A17\Twill\Models\Media;
use A17\Twill\Models\RelatedItem;
use Illuminate\Support\Collection;
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

    public static function getNestedBlockData(
        Block $block,
        ?Collection $allBlocks = null,
    ): BlockData|array {
        if (! isset($allBlocks)) {
            if (! $block->relationLoaded('children')) {
                $allBlocks = Block::query()
                    ->where('blockable_id', $block->blockable_id)
                    ->where('blockable_type', $block->blockable_type)
                    ->where('editor_name', $block->editor_name);
            } else {
                $children = $block->children;
            }
        }
        if (! isset($children)) {
            $children = $allBlocks->where('parent_id', $block->id); //->sortBy('position') I think it's already sorted
        }
        $children = $children->mapToDictionary(fn (Block $block) => [$block->child_key => self::getNestedBlockData(
            $block,
            $allBlocks,
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

        if (str_starts_with($block->type, 'dynamic-repeater-')) {
            return ['id' => $block->id] + $content + $children->all();
        }

        $browsers = [];
        $files = [];
        $medias = [];
        if (! empty($block->content['browsers'])) {
            $twillBlock = TwillBlocks::findByName($block->type);
            $class = $twillBlock?->componentClass;
            $types = ! empty($class) && property_exists($class, 'dataTypes') ? $class::$dataTypes : [];
            $browsers = $block->relatedItems->mapToDictionary(function (RelatedItem $item) use ($types) {
                $related = $item->related;
                if ($related && isset($types[$item->browser_name][get_class($related)])) {
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
        if (! $block->medias->isEmpty()) {
            $medias = $block->medias->mapToDictionary(function (Media $file) {
                return [$file->pivot->role => ImageData::from($file)];
            })->all();
        }

        return BlockData::from($block, [
            'props' => $content + $browsers + $files + $medias + $children->all(),
        ]);
    }

    public static function fromModel(Block $block): BlockData|array
    {
        return self::getNestedBlockData($block);
    }
}
