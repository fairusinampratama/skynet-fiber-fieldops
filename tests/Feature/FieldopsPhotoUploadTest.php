<?php

namespace Tests\Feature;

use App\Support\FieldopsPhotoProcessor;
use App\Support\FieldopsPhotoUpload;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Tests\TestCase;

class FieldopsPhotoUploadTest extends TestCase
{
    public function test_field_photo_uploads_are_resized_before_storage(): void
    {
        $upload = FieldopsPhotoUpload::configure(FileUpload::make('photo_path'));

        $this->assertSame(['image/jpeg', 'image/png', 'image/webp'], $upload->getAcceptedFileTypes());
        $this->assertSame(FieldopsPhotoUpload::MAX_UPLOAD_KILOBYTES, $upload->getMaxSize());
        $this->assertSame('contain', $upload->getAutomaticallyResizeImagesMode());
        $this->assertSame(FieldopsPhotoUpload::RESIZE_TARGET_PIXELS, $upload->getAutomaticallyResizeImagesWidth());
        $this->assertSame(FieldopsPhotoUpload::RESIZE_TARGET_PIXELS, $upload->getAutomaticallyResizeImagesHeight());
        $this->assertFalse($upload->shouldAutomaticallyUpscaleImagesWhenResizing());
    }

    public function test_livewire_temporary_upload_limit_allows_large_phone_photos(): void
    {
        $this->assertContains('max:' . FieldopsPhotoUpload::MAX_UPLOAD_KILOBYTES, config('livewire.temporary_file_upload.rules'));
        $this->assertSame(10, config('livewire.temporary_file_upload.max_upload_time'));
    }

    public function test_field_photo_processor_stores_normalized_jpeg(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD is required for server-side photo normalization.');
        }

        config(['livewire.temporary_file_upload.disk' => 'tmp-for-tests']);

        Storage::fake('tmp-for-tests');
        Storage::fake('public');

        $source = imagecreatetruecolor(2400, 1200);
        imagefill($source, 0, 0, imagecolorallocate($source, 32, 96, 160));

        ob_start();
        imagejpeg($source, null, 95);
        $contents = ob_get_clean();
        imagedestroy($source);

        Storage::disk('tmp-for-tests')->put('livewire-tmp/source.jpg', $contents);

        $file = TemporaryUploadedFile::createFromLivewire('source.jpg');
        $component = FileUpload::make('photo_path')
            ->disk('public')
            ->directory('submissions/odp')
            ->visibility('public');

        $path = FieldopsPhotoProcessor::storeNormalized($file, $component);

        $this->assertIsString($path);
        $this->assertStringStartsWith('submissions/odp/', $path);
        $this->assertStringEndsWith('.jpg', $path);
        Storage::disk('public')->assertExists($path);

        $thumbnailPath = FieldopsPhotoProcessor::thumbnailPathFor($path);

        $this->assertSame($thumbnailPath, FieldopsPhotoProcessor::tableThumbnailPathFor($path));
        Storage::disk('public')->assertExists($thumbnailPath);

        [$width, $height] = getimagesize(Storage::disk('public')->path($path));
        [$thumbnailWidth, $thumbnailHeight] = getimagesize(Storage::disk('public')->path($thumbnailPath));

        $this->assertSame(1600, $width);
        $this->assertSame(800, $height);
        $this->assertSame(320, $thumbnailWidth);
        $this->assertSame(160, $thumbnailHeight);
        $this->assertSame('image/jpeg', Storage::disk('public')->mimeType($path));
        $this->assertSame('image/jpeg', Storage::disk('public')->mimeType($thumbnailPath));
    }

    public function test_table_thumbnail_path_falls_back_to_original_photo_for_existing_records(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('assets/odc/existing.jpg', 'legacy');

        $this->assertSame('assets/odc/existing.jpg', FieldopsPhotoProcessor::tableThumbnailPathFor('assets/odc/existing.jpg'));
    }
}
