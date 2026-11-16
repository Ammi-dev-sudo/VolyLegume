<?php

namespace Tests\Unit;

use App\Services\PhotoStorage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PhotoStorageTest extends TestCase
{
    public function test_une_ecriture_photo_refusee_est_signalee_comme_echec(): void
    {
        config(['filesystems.media_disk' => 'unavailable']);

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->once()->andReturnFalse();
        Storage::shouldReceive('disk')->once()->with('unavailable')->andReturn($disk);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('La photo n’a pas pu être enregistrée.');

        app(PhotoStorage::class)->store(UploadedFile::fake()->image('culture.jpg'), 'campaign-photos');
    }
}
