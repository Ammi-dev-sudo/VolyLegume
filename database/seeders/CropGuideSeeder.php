<?php

namespace Database\Seeders;

use App\Models\Crop;
use App\Services\CropGuideService;
use Illuminate\Database\Seeder;

class CropGuideSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(CropGuideService::class);

        Crop::query()
            ->with('category')
            ->orderBy('id')
            ->each(fn (Crop $crop) => $service->sync($crop));
    }
}
