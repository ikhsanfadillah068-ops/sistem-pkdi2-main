<?php

namespace Database\Seeders;

use App\Models\DrumProduct;
use App\Models\ReconditioningService;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        DrumProduct::ensureDefaults();
        ReconditioningService::ensureDefaults();
    }
}
