<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pkdi:sync-if-empty', function () {
    if (Schema::hasTable('pengguna') && DB::table('users')->count() === 0) {
        $this->info('Tabel pengguna terdeteksi dan tabel users kosong. Menjalankan PuloKencanaDrumImportSeeder...');
        $this->call('db:seed', ['--class' => 'PuloKencanaDrumImportSeeder', '--force' => true]);

        return;
    }

    $this->info('Skip auto-import (tabel users sudah terisi atau tabel pengguna tidak tersedia).');
})->purpose('Impor otomatis data pulo_kencana_drum saat deploy jika tabel users masih kosong');
