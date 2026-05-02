<?php

namespace App\Support;

use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

class FieldopsPhotoProcessor
{
    public const OUTPUT_QUALITY = 80;

    public static function storeNormalized(TemporaryUploadedFile $file, BaseFileUpload $component): ?string
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('The GD extension is required to normalize field photos.');
        }

        if (! $file->exists()) {
            return null;
        }

        $sourcePath = $file->getRealPath();

        if (! is_string($sourcePath) || blank($sourcePath)) {
            return null;
        }

        [$image] = self::readImage($sourcePath, $file->getMimeType());
        $image = self::orientImage($image, $sourcePath);
        $width = imagesx($image);
        $height = imagesy($image);

        $path = trim($component->getDirectory() . '/' . Str::ulid() . '.jpg', '/');

        self::storeJpeg(
            disk: $component->getDisk(),
            path: $path,
            image: self::resizeImage($image, $width, $height, (int) FieldopsPhotoUpload::RESIZE_TARGET_PIXELS),
            visibility: $component->getVisibility(),
        );

        self::storeJpeg(
            disk: $component->getDisk(),
            path: self::thumbnailPathFor($path),
            image: self::resizeImage($image, $width, $height, (int) FieldopsPhotoUpload::THUMBNAIL_TARGET_PIXELS),
            visibility: $component->getVisibility(),
        );

        imagedestroy($image);

        return $path;
    }

    public static function thumbnailPathFor(?string $photoPath): ?string
    {
        if (blank($photoPath)) {
            return null;
        }

        $directory = trim((string) dirname($photoPath), '.');

        return trim($directory . '/thumbnails/' . basename($photoPath), '/');
    }

    public static function tableThumbnailPathFor(?string $photoPath, string $disk = 'public'): ?string
    {
        $thumbnailPath = self::thumbnailPathFor($photoPath);

        if ($thumbnailPath && Storage::disk($disk)->exists($thumbnailPath)) {
            return $thumbnailPath;
        }

        return $photoPath;
    }

    /**
     * @return array{0: resource|\GdImage, 1: int, 2: int}
     */
    private static function readImage(string $path, ?string $mimeType): array
    {
        $image = match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/webp' => imagecreatefromwebp($path),
            default => false,
        };

        if (! $image) {
            throw new RuntimeException('Unsupported or unreadable field photo.');
        }

        return [$image, imagesx($image), imagesy($image)];
    }

    /**
     * @param  resource|\GdImage  $image
     * @return resource|\GdImage
     */
    private static function orientImage($image, string $path)
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? ($exif['Orientation'] ?? null) : null;

        $oriented = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $oriented ?: $image;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function targetDimensions(int $width, int $height, ?int $maxDimension = null): array
    {
        $maxDimension ??= (int) FieldopsPhotoUpload::RESIZE_TARGET_PIXELS;

        if ($width <= $maxDimension && $height <= $maxDimension) {
            return [$width, $height];
        }

        $ratio = min($maxDimension / $width, $maxDimension / $height);

        return [
            max(1, (int) round($width * $ratio)),
            max(1, (int) round($height * $ratio)),
        ];
    }

    /**
     * @param  resource|\GdImage  $image
     * @return resource|\GdImage
     */
    private static function resizeImage($image, int $width, int $height, int $maxDimension)
    {
        [$targetWidth, $targetHeight] = self::targetDimensions($width, $height, $maxDimension);
        $resized = imagecreatetruecolor($targetWidth, $targetHeight);

        imagefill($resized, 0, 0, imagecolorallocate($resized, 255, 255, 255));
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $resized;
    }

    /**
     * @param  resource|\GdImage  $image
     */
    private static function storeJpeg($disk, string $path, $image, string $visibility): void
    {
        $temporary = fopen('php://temp', 'w+b');

        if ($temporary === false) {
            imagedestroy($image);

            throw new RuntimeException('Unable to allocate temporary memory for field photo normalization.');
        }

        imagejpeg($image, $temporary, self::OUTPUT_QUALITY);
        rewind($temporary);

        $disk->put($path, $temporary, ['visibility' => $visibility]);

        fclose($temporary);
        imagedestroy($image);
    }
}
