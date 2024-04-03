<?php

namespace Tofandel\TwillSpatieData;

use A17\Twill\Models\File;
use FileService;
use Spatie\LaravelData\Resource;
use Storage;

class FileData extends Resource
{
    public function __construct(
        public readonly string $name,
        public readonly string $url,
        public readonly string $type,
        public readonly int $size,
    ) {
    }

    public static function fromModel(File $file): FileData
    {
        return new self(
            $file->filename,
            FileService::getUrl($file->uuid),
            Storage::disk(config('twill.file_library.disk'))->mimeType($file->uuid),
            (int) $file->size,
        );
    }
}
