<?php

namespace App\Support;

use Filament\Forms\Components\FileUpload;

class FieldopsPhotoUpload
{
    public const MAX_UPLOAD_KILOBYTES = 51200;
    public const RESIZE_TARGET_PIXELS = '1600';
    public const THUMBNAIL_TARGET_PIXELS = '320';

    public static function configure(FileUpload $upload): FileUpload
    {
        return $upload
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize(self::MAX_UPLOAD_KILOBYTES)
            ->orientImagesFromExif()
            ->automaticallyResizeImagesMode('contain')
            ->automaticallyResizeImagesToWidth(self::RESIZE_TARGET_PIXELS)
            ->automaticallyResizeImagesToHeight(self::RESIZE_TARGET_PIXELS)
            ->automaticallyUpscaleImagesWhenResizing(false)
            ->imagePreviewHeight('160')
            ->saveUploadedFileUsing(fn ($component, $file): ?string => FieldopsPhotoProcessor::storeNormalized($file, $component))
            ->helperText('Foto besar dari kamera HP akan diperkecil otomatis sebelum disimpan.');
    }
}
