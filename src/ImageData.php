<?php

namespace Tofandel\TwillSpatieData;

use A17\Twill\Models\Media;
use A17\Twill\Services\MediaLibrary\ImageService;
use Illuminate\Support\Arr;
use Spatie\LaravelData\Resource;

/** @see Media */
class ImageData extends Resource
{
    public function __construct(
        public readonly string $url,
        public readonly ?string $alt_text,
        public readonly ?string $caption,
        public readonly int $width,
        public readonly int $height,
    ) {

    }

    public static function fromModel(Media $media): self
    {
        $crop_params = Arr::only($media->pivot->toArray(), [
            'crop_x',
            'crop_y',
            'crop_w',
            'crop_h',
        ]);
        $url = ImageService::getUrlWithCrop($media->uuid, $crop_params);

        return new self(
            $url,
            alt_text: $media->alt_text,
            caption: $media->caption,
            width: $crop_params['crop_w'] ?? $media->width,
            height: $crop_params['crop_h'] ?? $media->height,
        );
    }
}
