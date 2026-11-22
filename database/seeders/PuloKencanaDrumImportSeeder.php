<?php

namespace Database\Seeders;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\BillableTransaction;
use App\Models\CashbookEntry;
use App\Models\ClientCompany;
use App\Models\DeliveryOrder;
use App\Models\DrumProduct;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\OperationalExpense;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\ReconditioningService;
use App\Models\ServiceOrder;
use App\Models\ServiceProcess;
use App\Models\ServiceResult;
use App\Models\ServiceTariff;
use App\Models\User;
use App\Models\WarehouseInspection;
use App\Models\WarehouseReceipt;
use App\Models\WarehouseSorting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Memetakan seluruh data nyata dari 72 tabel asli database `pulo_kencana_drum`
 * ke skema tabel operasional Laravel `sistem_pkdi` secara non-destruktif
 * (tabel asli `pulo_kencana_drum` tidak pernah diubah atau dihapus).
 */
class PuloKencanaDrumImportSeeder extends Seeder
{
    public const DEFAULT_LOCAL_PASSWORD = 'password123';

    public function run(): void
    {
        $this->truncateLaravelOperationalTables();

        $defaultPasswordHash = Hash::make(self::DEFAULT_LOCAL_PASSWORD);

        $drumProductMap = $this->seedDrumProducts();
        $reconditioningServiceMap = $this->seedReconditioningServices();
        $clientCompanyMap = $this->seedClientCompanies();
        [$userMap, $defaultGudangId, $defaultKeuanganId, $defaultOwnerId] = $this->seedUsers(
            $clientCompanyMap,
            $defaultPasswordHash,
        );
        [$drumTariffMap, $limbahTariffMap] = $this->seedTariffs($defaultKeuanganId);

        $orderContext = $this->seedOrdersAndWarehouse(
            $clientCompanyMap,
            $userMap,
            $drumProductMap,
            $reconditioningServiceMap,
            $defaultGudangId,
        );

        [$invoiceMap, $paymentMap] = $this->seedInvoicesPaymentsAndDeliveryOrders(
            $orderContext,
            $clientCompanyMap,
            $userMap,
            $drumTariffMap,
            $limbahTariffMap,
            $defaultGudangId,
            $defaultKeuanganId,
        );

        $this->seedExpensesAndCashbook(
            $paymentMap,
            $invoiceMap,
            $userMap,
            $defaultKeuanganId,
        );

        $this->seedNotificationsAndAudit(
            $orderContext,
            $defaultGudangId,
            $defaultKeuanganId,
            $defaultOwnerId,
        );
    }

    private function truncateLaravelOperationalTables(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ([
            'audit_logs',
            'notifications',
            'cashbook_entries',
            'operational_expenses',
            'vehicle_gate_logs',
            'delivery_orders',
            'payments',
            'invoice_items',
            'billable_transactions',
            'invoices',
            'service_results',
            'service_processes',
            'warehouse_inspections',
            'warehouse_sortings',
            'warehouse_receipts',
            'order_status_histories',
            'service_orders',
            'service_tariffs',
            'reconditioning_services',
            'drum_products',
            'sessions',
            'password_reset_tokens',
            'users',
            'client_companies',
        ] as $table) {
            DB::table($table)->truncate();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * @return array<int, DrumProduct>
     */
    private function seedDrumProducts(): array
    {
        $rows = DB::table('jenis_drum as jd')
            ->join('bahan_drum as bd', 'bd.id', '=', 'jd.bahan_drum_id')
            ->join('tipe_tutup as tt', 'tt.id', '=', 'jd.tipe_tutup_id')
            ->select('jd.id', 'bd.nama as bahan', 'tt.nama as tutup', 'jd.kapasitas_liter')
            ->orderBy('jd.id')
            ->get();

        $map = [];
        foreach ($rows as $index => $row) {
            $shortTutup = str_contains($row->tutup, 'Open Top') ? 'Tutup Besar' : 'Tutup Kecil';
            $product = DrumProduct::query()->create([
                'code' => sprintf('JD-%02d', $row->id),
                'name' => sprintf('Drum %s %dL (%s)', $row->bahan, $row->kapasitas_liter, $shortTutup),
                'capacity_label' => "{$row->kapasitas_liter} Liter",
                'default_unit' => 'Drum',
                'description' => "Drum {$row->bahan} {$row->kapasitas_liter}L - {$row->tutup}.",
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);
            $map[(int) $row->id] = $product;
        }

        // Pastikan produk standar PRD 2.0 tetap tersedia tanpa konflik unique name saat form order dibuka.
        DrumProduct::ensureDefaults();

        return $map;
    }

    /**
     * @return array<int, ReconditioningService>
     */
    private function seedReconditioningServices(): array
    {
        ReconditioningService::ensureDefaults();

        $byName = ReconditioningService::query()->get()->keyBy('name');
        $rows = DB::table('paket_drum')->orderBy('id')->get();
        $map = [];

        foreach ($rows as $index => $row) {
            $service = $byName->get($row->nama);
            if (! $service) {
                $service = ReconditioningService::query()->create([
                    'code' => 'REC-'.Str::upper(Str::slug($row->nama)),
                    'name' => $row->nama,
                    'description' => $row->deskripsi,
                    'is_active' => true,
                    'sort_order' => $index + 10,
                ]);
            }
            $map[(int) $row->id] = $service;
        }

        return $map;
    }

    /**
     * @return array<int, ClientCompany>
     */
    private function seedClientCompanies(): array
    {
        $clients = DB::table('client')->orderBy('id')->get();
        $pics = DB::table('client_pic')->orderByDesc('berwenang_order')->orderBy('id')->get()->groupBy('client_id');
        $addresses = DB::table('client_alamat as ca')
            ->join('kota as k', 'k.id', '=', 'ca.kota_id')
            ->join('provinsi as p', 'p.id', '=', 'k.provinsi_id')
            ->select('ca.*', 'k.nama as kota_nama', 'p.nama as provinsi_nama')
            ->orderBy('ca.jenis_alamat_id')
            ->orderBy('ca.id')
            ->get()
            ->groupBy('client_id');
        $docs = DB::table('client_dokumen')->orderByDesc('diunggah_pada')->get()->groupBy('client_id');
        $verifications = DB::table('client_verifikasi')->orderByDesc('id')->get()->groupBy('client_id');

        $map = [];
        foreach ($clients as $c) {
            $pic = $pics->get($c->id)?->first();
            $addr = $addresses->get($c->id)?->first();
            $fullAddress = $addr
                ? trim("{$addr->alamat_lengkap}, {$addr->kota_nama}, {$addr->provinsi_nama} ".($addr->kode_pos ?? ''))
                : 'Belum dilengkapi';

            $clientDocs = $docs->get($c->id) ?? collect();
            $nibDoc = $clientDocs->firstWhere('jenis_dokumen_client_id', 1);
            $distinctDocTypes = $clientDocs->pluck('jenis_dokumen_client_id')->unique()->count();

            $latestVerif = $verifications->get($c->id)?->first();
            $verificationStatus = match ((int) ($latestVerif->status_persetujuan_id ?? 1)) {
                2 => 'ACTIVE',
                3 => 'REJECTED',
                default => 'PENDING',
            };

            $company = ClientCompany::query()->create([
                'company_code' => sprintf('CLI-PKDI-%04d', $c->id),
                'company_name' => $c->nama_perusahaan,
                'pic_name' => $pic->nama ?? $c->nama_perusahaan,
                'email' => $c->email,
                'phone' => $c->no_telepon,
                'address' => $fullAddress,
                'npwp' => $c->npwp,
                'nib_number' => $c->nib,
                'nib_file_path' => $nibDoc->file_path ?? null,
                'documents_status' => $distinctDocTypes >= 4 ? 'COMPLETE' : 'INCOMPLETE',
                'verification_status' => $verificationStatus,
                'created_at' => $c->created_at,
                'updated_at' => $c->created_at,
            ]);

            $map[(int) $c->id] = $company;
        }

        return $map;
    }

    /**
     * @param  array<int, ClientCompany>  $clientCompanyMap
     * @return array{0: array<int, User>, 1: int, 2: int, 3: int}
     */
    private function seedUsers(array $clientCompanyMap, string $defaultPasswordHash): array
    {
        $rows = DB::table('pengguna')->orderBy('id')->get();
        $map = [];
        $defaultGudangId = null;
        $defaultKeuanganId = null;
        $defaultOwnerId = null;

        foreach ($rows as $row) {
            // Role `Admin` (peran_id = 5) dipetakan ke `kepala_gudang` agar sesuai 4 role aktif PRD 2.0.
            $role = match ((int) $row->peran_id) {
                1 => User::ROLE_CLIENT,
                2 => User::ROLE_KEPALA_GUDANG,
                3 => User::ROLE_KEUANGAN,
                4 => User::ROLE_OWNER,
                5 => User::ROLE_KEPALA_GUDANG,
                default => User::ROLE_CLIENT,
            };

            $companyId = $row->client_id ? ($clientCompanyMap[(int) $row->client_id]->id ?? null) : null;

            $userId = DB::table('users')->insertGetId([
                'client_company_id' => $companyId,
                'name' => $row->nama,
                'email' => Str::lower($row->email),
                'phone' => $row->no_telepon,
                'email_verified_at' => $row->created_at,
                'password' => $defaultPasswordHash,
                'role' => $role,
                'is_active' => (bool) $row->is_aktif,
                'created_at' => $row->created_at,
                'updated_at' => $row->created_at,
            ]);

            $user = User::query()->findOrFail($userId);
            $map[(int) $row->id] = $user;

            if ((int) $row->peran_id === 2 && $row->is_aktif && $defaultGudangId === null) {
                $defaultGudangId = $userId;
            }
            if ((int) $row->peran_id === 3 && $row->is_aktif && $defaultKeuanganId === null) {
                $defaultKeuanganId = $userId;
            }
            if ((int) $row->peran_id === 4 && $row->is_aktif && $defaultOwnerId === null) {
                $defaultOwnerId = $userId;
            }
        }

        return [
            $map,
            $defaultGudangId ?? array_key_first($map),
            $defaultKeuanganId ?? array_key_first($map),
            $defaultOwnerId ?? array_key_first($map),
        ];
    }

    /**
     * @return array{0: array<string, ServiceTariff>, 1: array<string, ServiceTariff>}
     */
    private function seedTariffs(int $defaultKeuanganId): array
    {
        $drumTariffs = DB::table('harga_paket_drum as h')
            ->join('paket_drum as pd', 'pd.id', '=', 'h.paket_drum_id')
            ->join('jenis_drum as jd', 'jd.id', '=', 'h.jenis_drum_id')
            ->join('bahan_drum as bd', 'bd.id', '=', 'jd.bahan_drum_id')
            ->join('tipe_tutup as tt', 'tt.id', '=', 'jd.tipe_tutup_id')
            ->select('h.*', 'pd.nama as paket_nama', 'bd.nama as bahan', 'tt.nama as tutup', 'jd.kapasitas_liter')
            ->orderBy('h.paket_drum_id')
            ->orderBy('h.jenis_drum_id')
            ->orderBy('h.berlaku_mulai')
            ->get();

        $latestDrumDates = [];
        foreach ($drumTariffs as $t) {
            $k = "{$t->paket_drum_id}-{$t->jenis_drum_id}";
            $latestDrumDates[$k] = max($latestDrumDates[$k] ?? '', $t->berlaku_mulai);
        }

        $drumTariffMap = [];
        foreach ($drumTariffs as $t) {
            $k = "{$t->paket_drum_id}-{$t->jenis_drum_id}";
            $isLatest = $t->berlaku_mulai === $latestDrumDates[$k];
            $shortTutup = str_contains($t->tutup, 'Open Top') ? 'Tutup Besar' : 'Tutup Kecil';
            $productLabel = sprintf('Drum %s %dL (%s)', $t->bahan, $t->kapasitas_liter, $shortTutup);

            $tariff = ServiceTariff::query()->create([
                'tariff_code' => sprintf('TRF-DRUM-%02d', $t->id),
                'name' => "Rekondisi {$productLabel} - {$t->paket_nama}",
                'service_category' => $productLabel,
                'cargo_type' => "Kemasan {$t->bahan} {$t->kapasitas_liter}L",
                'service_type' => $t->paket_nama,
                'unit' => 'Drum',
                'unit_price' => $t->harga,
                'effective_from' => $t->berlaku_mulai,
                'effective_until' => $isLatest ? null : '2026-06-30',
                'is_active' => $isLatest,
                'created_by_user_id' => $defaultKeuanganId,
            ]);

            $drumTariffMap["{$k}-{$t->harga}"] = $tariff;
            if ($isLatest) {
                $drumTariffMap[$k] = $tariff;
            }
        }

        $limbahTariffs = DB::table('harga_angkut_limbah as h')
            ->join('limbah as l', 'l.id', '=', 'h.limbah_id')
            ->join('kategori_limbah as kl', 'kl.id', '=', 'l.kategori_limbah_id')
            ->join('satuan as s', 's.id', '=', 'h.satuan_id')
            ->select('h.*', 'l.kode_limbah', 'l.nama as limbah_nama', 'kl.nama as kategori_nama', 's.nama as satuan_nama')
            ->orderBy('h.limbah_id')
            ->orderBy('h.satuan_id')
            ->orderBy('h.berlaku_mulai')
            ->get();

        $latestLimbahDates = [];
        foreach ($limbahTariffs as $t) {
            $k = "{$t->limbah_id}-{$t->satuan_id}";
            $latestLimbahDates[$k] = max($latestLimbahDates[$k] ?? '', $t->berlaku_mulai);
        }

        $limbahTariffMap = [];
        foreach ($limbahTariffs as $t) {
            $k = "{$t->limbah_id}-{$t->satuan_id}";
            $isLatest = $t->berlaku_mulai === $latestLimbahDates[$k];

            $tariff = ServiceTariff::query()->create([
                'tariff_code' => sprintf('TRF-LMB-%02d', $t->id),
                'name' => "Pengangkutan {$t->kode_limbah} - {$t->limbah_nama}",
                'service_category' => "Limbah {$t->kategori_nama}",
                'cargo_type' => "{$t->kode_limbah} ({$t->limbah_nama})",
                'service_type' => 'Pengangkutan Limbah',
                'unit' => $t->satuan_nama,
                'unit_price' => $t->harga,
                'effective_from' => $t->berlaku_mulai,
                'effective_until' => $isLatest ? null : '2026-06-30',
                'is_active' => $isLatest,
                'created_by_user_id' => $defaultKeuanganId,
            ]);

            $limbahTariffMap["{$k}-{$t->harga}"] = $tariff;
            if ($isLatest) {
                $limbahTariffMap[$k] = $tariff;
            }
        }

        return [$drumTariffMap, $limbahTariffMap];
    }

    /**
     * @param  array<int, ClientCompany>  $clientCompanyMap
     * @param  array<int, User>  $userMap
     * @param  array<int, DrumProduct>  $drumProductMap
     * @param  array<int, ReconditioningService>  $reconditioningServiceMap
     * @return array<int, array<string, mixed>>
     */
    private function seedOrdersAndWarehouse(
        array $clientCompanyMap,
        array $userMap,
        array $drumProductMap,
        array $reconditioningServiceMap,
        int $defaultGudangId,
    ): array {
        $pesananRows = DB::table('pesanan')->orderBy('id')->get();

        $drumItemsByPesanan = DB::table('pesanan_drum_item as pdi')
            ->join('jenis_drum as jd', 'jd.id', '=', 'pdi.jenis_drum_id')
            ->join('bahan_drum as bd', 'bd.id', '=', 'jd.bahan_drum_id')
            ->join('tipe_tutup as tt', 'tt.id', '=', 'jd.tipe_tutup_id')
            ->join('paket_drum as pd', 'pd.id', '=', 'pdi.paket_drum_id')
            ->join('isi_drum as idr', 'idr.id', '=', 'pdi.isi_drum_id')
            ->leftJoin('warna as w', 'w.id', '=', 'pdi.warna_id')
            ->leftJoin('pesanan_drum_item_sablon as sbl', 'sbl.pesanan_drum_item_id', '=', 'pdi.id')
            ->select(
                'pdi.*',
                'bd.nama as bahan',
                'tt.nama as tutup',
                'jd.kapasitas_liter',
                'pd.nama as paket_nama',
                'idr.nama as isi_nama',
                'w.nama as warna_nama',
                'sbl.spesifikasi as sablon_spesifikasi'
            )
            ->orderBy('pdi.id')
            ->get()
            ->groupBy('pesanan_id');

        $limbahHeaderByPesanan = DB::table('pesanan_limbah')->get()->keyBy('pesanan_id');

        $limbahItemsByPesanan = DB::table('pesanan_limbah_item as pli')
            ->join('limbah as l', 'l.id', '=', 'pli.limbah_id')
            ->join('kategori_limbah as kl', 'kl.id', '=', 'l.kategori_limbah_id')
            ->join('satuan as s', 's.id', '=', 'pli.satuan_id')
            ->select('pli.*', 'l.kode_limbah', 'l.nama as limbah_nama', 'kl.nama as kategori_nama', 's.nama as satuan_nama', 's.simbol as satuan_simbol')
            ->orderBy('pli.id')
            ->get()
            ->groupBy('pesanan_id');

        $statusLogsByPesanan = DB::table('pesanan_status_log as psl')
            ->join('status_pesanan as sp', 'sp.id', '=', 'psl.status_pesanan_id')
            ->select('psl.*', 'sp.nama as status_nama')
            ->orderBy('psl.id')
            ->get()
            ->groupBy('pesanan_id');

        $penerimaanByPesanan = DB::table('penerimaan_drum')->orderBy('id')->get()->groupBy('pesanan_id');
        $penerimaanItemsByPenerimaan = DB::table('penerimaan_drum_item')->orderBy('id')->get()->groupBy('penerimaan_drum_id');
        $penerimaanFotosByItem = DB::table('penerimaan_foto')->orderBy('id')->get()->groupBy('penerimaan_drum_item_id');
        $inspeksiByPenerimaanItem = DB::table('inspeksi_drum as ins')
            ->join('kondisi_drum as kd', 'kd.id', '=', 'ins.kondisi_drum_id')
            ->join('keputusan_inspeksi as ki', 'ki.id', '=', 'ins.keputusan_inspeksi_id')
            ->select('ins.*', 'kd.nama as kondisi_nama', 'ki.nama as keputusan_nama')
            ->orderBy('ins.id')
            ->get()
            ->groupBy('penerimaan_drum_item_id');

        $prosesByDrumItem = DB::table('pesanan_item_proses as pip')
            ->join('proses_layanan as pl', 'pl.id', '=', 'pip.proses_layanan_id')
            ->select('pip.*', 'pl.nama as proses_nama', 'pl.urutan')
            ->orderBy('pip.waktu_mulai')
            ->orderBy('pip.id')
            ->get()
            ->groupBy('pesanan_drum_item_id');

        $pengangkutanByPesanan = DB::table('pengangkutan as pg')
            ->join('armada as a', 'a.id', '=', 'pg.armada_id')
            ->join('jenis_armada as ja', 'ja.id', '=', 'a.jenis_armada_id')
            ->select('pg.*', 'a.plat_nomor', 'a.merek', 'ja.nama as jenis_armada')
            ->get()
            ->keyBy('pesanan_id');

        $pengangkutanDetailByPengangkutan = DB::table('pengangkutan_detail as pd')
            ->join('satuan as s', 's.id', '=', 'pd.satuan_id')
            ->select('pd.*', 's.nama as satuan_nama')
            ->get()
            ->groupBy('pengangkutan_id');

        $dokumenAngkutByPengangkutan = DB::table('dokumen_angkut as da')
            ->join('jenis_dokumen_angkut as jda', 'jda.id', '=', 'da.jenis_dokumen_angkut_id')
            ->select('da.*', 'jda.nama as jenis_dokumen')
            ->orderBy('da.id')
            ->get()
            ->groupBy('pengangkutan_id');

        $suratJalanByPesanan = DB::table('surat_jalan as sj')
            ->join('permintaan_surat_jalan as psj', 'psj.id', '=', 'sj.permintaan_surat_jalan_id')
            ->join('armada as a', 'a.id', '=', 'sj.armada_id')
            ->join('jenis_armada as ja', 'ja.id', '=', 'a.jenis_armada_id')
            ->select('sj.*', 'psj.pesanan_id', 'a.plat_nomor', 'a.merek', 'ja.nama as jenis_armada')
            ->get()
            ->keyBy('pesanan_id');

        $suratJalanItemsBySj = DB::table('surat_jalan_item')->get()->groupBy('surat_jalan_id');

        $context = [];

        foreach ($pesananRows as $p) {
            $company = $clientCompanyMap[(int) $p->client_id];
            $creator = $userMap[(int) $p->dibuat_oleh];
            $isRecond = (int) $p->layanan_id === 1;

            $logs = $statusLogsByPesanan->get($p->id) ?? collect();
            $latestLog = $logs->last();
            $latestStatusId = (int) ($latestLog->status_pesanan_id ?? ($isRecond ? 1 : 13));

            [$orderStatus, $warehouseStatus] = $this->mapOrderStatuses($latestStatusId);

            $completedAt = in_array($latestStatusId, [12, 20], true) ? $latestLog->created_at : null;
            $cancelledAt = in_array($latestStatusId, [3, 15], true) ? $latestLog->created_at : null;
            $cancellationReason = $cancelledAt ? ($latestLog->catatan ?? 'Pesanan ditolak.') : null;

            $deliveredLog = $logs->firstWhere('status_pesanan_id', 11);
            $sj = $suratJalanByPesanan->get($p->id);
            $pg = $pengangkutanByPesanan->get($p->id);

            $drumProductId = null;
            $recondServiceId = null;
            $serviceCategory = null;
            $cargoType = null;
            $serviceType = null;
            $requestedQty = 0;
            $requestedUnit = 'Drum';
            $specLines = [];
            $neededDate = null;
            $scheduledPickupDate = null;
            $inTransitAt = null;
            $vehicleType = null;
            $licensePlate = null;
            $transportNotes = null;
            $deliveryMethod = null;
            $deliveredQty = null;
            $deliveredAt = $deliveredLog->created_at ?? null;

            if ($isRecond) {
                $items = $drumItemsByPesanan->get($p->id) ?? collect();
                $primary = $items->sortByDesc('jumlah')->first();
                if ($primary) {
                    $drumProduct = $drumProductMap[(int) $primary->jenis_drum_id] ?? null;
                    $recondService = $reconditioningServiceMap[(int) $primary->paket_drum_id] ?? null;
                    $drumProductId = $drumProduct?->id;
                    $recondServiceId = $recondService?->id;
                    $serviceCategory = $drumProduct?->name ?? 'Drum Besi 200L';
                    $serviceType = $recondService?->name ?? $primary->paket_nama;
                }
                $cargoType = Str::limit($items->pluck('isi_nama')->unique()->implode(', '), 150, '');
                $requestedQty = (int) $items->sum('jumlah');
                $requestedUnit = 'Drum';

                foreach ($items as $it) {
                    $shortTutup = str_contains($it->tutup, 'Open Top') ? 'Tutup Besar' : 'Tutup Kecil';
                    $line = "- {$it->jumlah} Drum {$it->bahan} {$it->kapasitas_liter}L ({$shortTutup}) | Paket: {$it->paket_nama} | Isi awal: {$it->isi_nama}";
                    if ($it->warna_nama) {
                        $line .= " | Warna: {$it->warna_nama}";
                    }
                    if ($it->sablon_spesifikasi) {
                        $line .= " | Sablon: {$it->sablon_spesifikasi}";
                    }
                    $specLines[] = $line;
                }

                if ($sj) {
                    $vehicleType = trim("{$sj->jenis_armada} - {$sj->merek}");
                    $licensePlate = $sj->plat_nomor;
                    $deliveryMethod = 'DIANTAR_PKDI';
                    $sjItems = $suratJalanItemsBySj->get($sj->id) ?? collect();
                    if ($deliveredAt || $latestStatusId === 12) {
                        $deliveredQty = (int) $sjItems->sum('jumlah_dikirim');
                    }
                }
                $transitLog = $logs->firstWhere('status_pesanan_id', 4);
                $inTransitAt = $transitLog->created_at ?? null;
            } else {
                $limbahHeader = $limbahHeaderByPesanan->get($p->id);
                $items = $limbahItemsByPesanan->get($p->id) ?? collect();
                $primary = $items->first();
                $categories = $items->pluck('kategori_nama')->unique()->implode(' & ');
                $serviceCategory = $categories ? "Limbah {$categories}" : 'Limbah Industri';
                $cargoType = Str::limit($items->pluck('limbah_nama')->unique()->implode(', '), 150, '');
                $serviceType = 'Pengangkutan Limbah';
                $requestedQty = max(1, (int) round((float) $items->sum('jumlah_estimasi')));
                $requestedUnit = $primary->satuan_nama ?? 'Kilogram';
                $neededDate = $limbahHeader->tanggal_angkut_diminta ?? null;

                foreach ($items as $it) {
                    $est = number_format((float) $it->jumlah_estimasi, 0, ',', '.');
                    $specLines[] = "- [{$it->kode_limbah}] {$it->limbah_nama} ({$it->kategori_nama}): {$est} {$it->satuan_simbol}";
                }

                if ($pg) {
                    $scheduledPickupDate = $pg->tanggal_rencana;
                    $inTransitAt = $pg->waktu_mulai;
                    $vehicleType = trim("{$pg->jenis_armada} - {$pg->merek}");
                    $licensePlate = $pg->plat_nomor;
                    $deliveryMethod = 'DIANTAR_PKDI';

                    $docs = $dokumenAngkutByPengangkutan->get($pg->id) ?? collect();
                    if ($docs->isNotEmpty()) {
                        $docSummary = $docs->map(fn ($d) => "{$d->jenis_dokumen}: {$d->nomor_dokumen}")->implode(' | ');
                        $transportNotes = "Dokumen Angkut: {$docSummary}";
                        $specLines[] = $transportNotes;
                    }
                    if ($pg->waktu_selesai) {
                        $details = $pengangkutanDetailByPengangkutan->get($pg->id) ?? collect();
                        $deliveredQty = max(1, (int) round((float) $details->sum('jumlah_aktual')));
                        $deliveredAt = $pg->waktu_selesai;
                    }
                }
            }

            $order = ServiceOrder::query()->create([
                'order_number' => $p->nomor_pesanan,
                'order_slug' => Str::lower(str_replace('/', '-', $p->nomor_pesanan)),
                'client_company_id' => $company->id,
                'created_by_user_id' => $creator->id,
                'transaction_type' => $isRecond ? ServiceOrder::TYPE_RECONDITIONING : ServiceOrder::TYPE_WASTE_TRANSPORT,
                'drum_product_id' => $drumProductId,
                'reconditioning_service_id' => $recondServiceId,
                'service_category' => $serviceCategory,
                'cargo_type' => $cargoType,
                'service_type' => $serviceType,
                'requested_quantity' => $requestedQty,
                'requested_unit' => $requestedUnit,
                'po_number' => $p->nomor_pesanan,
                'needed_date' => $neededDate,
                'scheduled_pickup_date' => $scheduledPickupDate,
                'origin_company_name' => $company->company_name,
                'transporter_name' => $vehicleType ? 'Armada Internal PKDI' : null,
                'vehicle_type' => $vehicleType,
                'license_plate' => $licensePlate,
                'client_notes' => $p->catatan,
                'specification_notes' => implode("\n", $specLines),
                'transport_notes' => $transportNotes,
                'order_status' => $orderStatus,
                'gate_status' => 'NOT_REQUIRED',
                'warehouse_status' => $warehouseStatus,
                'billing_status' => 'NOT_READY',
                'payment_status' => 'UNBILLED',
                'delivery_method' => $deliveryMethod,
                'delivered_quantity' => $deliveredQty,
                'delivered_at' => $deliveredAt,
                'submitted_at' => $p->tanggal_pengajuan,
                'in_transit_at' => $inTransitAt,
                'completed_at' => $completedAt,
                'cancelled_at' => $cancelledAt,
                'cancellation_reason' => $cancellationReason,
                'created_at' => $p->tanggal_pengajuan,
                'updated_at' => $latestLog->created_at ?? $p->tanggal_pengajuan,
            ]);

            // 1. Riwayat status (pesanan_status_log -> order_status_histories)
            $prevStatus = null;
            foreach ($logs as $log) {
                [$mappedStatus] = $this->mapOrderStatuses((int) $log->status_pesanan_id);
                $actorId = $userMap[(int) $log->diubah_oleh]->id ?? $defaultGudangId;

                OrderStatusHistory::query()->create([
                    'service_order_id' => $order->id,
                    'track_type' => 'ORDER',
                    'from_status' => $prevStatus,
                    'to_status' => $mappedStatus,
                    'changed_by_user_id' => $actorId,
                    'notes' => "[{$log->status_nama}] ".($log->catatan ?: 'Pembaruan status pesanan.'),
                    'is_visible_to_client' => true,
                    'created_at' => $log->created_at,
                    'updated_at' => $log->created_at,
                ]);
                $prevStatus = $mappedStatus;
            }

            // 2. Penerimaan, penyortiran, proses & hasil kerja gudang
            $completedQty = $requestedQty;
            $rejectedQty = 0;
            $resultCompletedAt = $completedAt;

            if ($isRecond) {
                $penerimaanList = $penerimaanByPesanan->get($p->id) ?? collect();
                $firstPenerimaan = $penerimaanList->first();
                $receiptModel = null;
                $totalReceived = 0;
                $totalWeightKg = 0.0;
                $firstPhoto = null;

                $qtyPassed = 0;
                $qtyRepair = 0;
                $qtyReject = 0;
                $hasInspection = false;
                $conditionNotes = [];

                if ($firstPenerimaan) {
                    foreach ($penerimaanList as $pen) {
                        $penItems = $penerimaanItemsByPenerimaan->get($pen->id) ?? collect();
                        foreach ($penItems as $pi) {
                            $totalReceived += (int) $pi->jumlah_diterima;
                            $totalWeightKg += (float) ($pi->berat_total_kg ?? 0);
                            if (! $firstPhoto) {
                                $firstPhoto = ($penerimaanFotosByItem->get($pi->id) ?? collect())->first()?->file_path;
                            }
                            $insRows = $inspeksiByPenerimaanItem->get($pi->id) ?? collect();
                            if ($insRows->isNotEmpty()) {
                                $hasInspection = true;
                                foreach ($insRows as $ins) {
                                    $qty = (int) $ins->jumlah;
                                    match ((int) $ins->keputusan_inspeksi_id) {
                                        1 => $qtyPassed += $qty,
                                        2 => $qtyRepair += $qty,
                                        3 => $qtyReject += $qty,
                                        default => null,
                                    };
                                    $conditionNotes[$ins->kondisi_nama] = ($conditionNotes[$ins->kondisi_nama] ?? 0) + $qty;
                                }
                            }
                        }
                    }

                    $receiverId = $userMap[(int) $firstPenerimaan->diterima_oleh]->id ?? $defaultGudangId;
                    $receiptModel = WarehouseReceipt::query()->create([
                        'service_order_id' => $order->id,
                        'receipt_number' => sprintf('RCP-%04d', $firstPenerimaan->id),
                        'received_at' => $firstPenerimaan->tanggal_terima,
                        'actual_received_quantity' => $totalReceived,
                        'unit' => 'Drum',
                        'initial_condition' => ($firstPenerimaan->catatan ?: 'Drum diterima & ditimbang di gudang PKDI.')
                            .' Berat total: '.number_format($totalWeightKg, 2, ',', '.').' kg.',
                        'discrepancy_notes' => $totalReceived !== $requestedQty
                            ? "Selisih penerimaan: diminta {$requestedQty} drum, diterima {$totalReceived} drum."
                            : 'Jumlah fisik sesuai dengan pengajuan.',
                        'photo_path' => $firstPhoto,
                        'received_by_user_id' => $receiverId,
                        'created_at' => $firstPenerimaan->tanggal_terima,
                        'updated_at' => $firstPenerimaan->tanggal_terima,
                    ]);

                    if ($hasInspection) {
                        $insLog = $logs->firstWhere('status_pesanan_id', 6);
                        $sortedAt = $insLog->created_at ?? $firstPenerimaan->tanggal_terima;
                        $condSummary = collect($conditionNotes)
                            ->map(fn ($q, $k) => "{$k}: {$q} drum")
                            ->implode(', ');

                        WarehouseSorting::query()->create([
                            'service_order_id' => $order->id,
                            'warehouse_receipt_id' => $receiptModel->id,
                            'qty_passed' => $qtyPassed,
                            'qty_special_repair' => $qtyRepair,
                            'qty_rejected' => $qtyReject,
                            'unit' => 'Drum',
                            'sorting_notes' => "Hasil inspeksi kondisi: {$condSummary}.",
                            'rejected_reason' => $qtyReject > 0 ? "{$qtyReject} drum bocor/tidak layak dikeluarkan dari proses." : null,
                            'photo_path' => $firstPhoto,
                            'sorted_by_user_id' => $defaultGudangId,
                            'sorted_at' => $sortedAt,
                            'created_at' => $sortedAt,
                            'updated_at' => $sortedAt,
                        ]);

                        WarehouseInspection::query()->create([
                            'service_order_id' => $order->id,
                            'actual_cargo_type' => $cargoType ?: 'Drum Industri',
                            'actual_quantity' => $totalReceived,
                            'actual_unit' => 'Drum',
                            'cargo_condition' => "Layak: {$qtyPassed}, Perlu Perbaikan: {$qtyRepair}, Reject: {$qtyReject} ({$condSummary}).",
                            'discrepancy_notes' => $totalReceived !== $requestedQty
                                ? "Selisih {$totalReceived} dari {$requestedQty} drum."
                                : null,
                            'decision' => 'APPROVED',
                            'decision_reason' => 'Lolos inspeksi awal gudang.',
                            'photo_path' => $firstPhoto,
                            'inspected_by_user_id' => $defaultGudangId,
                            'inspected_at' => $sortedAt,
                            'created_at' => $sortedAt,
                            'updated_at' => $sortedAt,
                        ]);
                    }
                }

                // Proses rekondisi (pesanan_item_proses -> service_processes)
                $items = $drumItemsByPesanan->get($p->id) ?? collect();
                $lastProcessEnd = null;
                foreach ($items as $it) {
                    $procs = $prosesByDrumItem->get($it->id) ?? collect();
                    foreach ($procs as $pr) {
                        $recAt = $pr->waktu_selesai ?? $pr->waktu_mulai ?? $p->tanggal_pengajuan;
                        $lastProcessEnd = $lastProcessEnd ? max($lastProcessEnd, $recAt) : $recAt;
                        $actorId = $userMap[(int) $pr->dicatat_oleh]->id ?? $defaultGudangId;
                        $statusKet = $pr->waktu_selesai ? 'Selesai' : 'Sedang berjalan';

                        ServiceProcess::query()->create([
                            'service_order_id' => $order->id,
                            'process_stage' => "{$pr->proses_nama} ({$it->bahan} {$it->kapasitas_liter}L)",
                            'progress_notes' => "Tahap {$pr->proses_nama} untuk item {$it->paket_nama} - {$statusKet} ({$pr->jumlah_selesai} drum).",
                            'processed_qty' => (int) $pr->jumlah_selesai,
                            'is_visible_to_client' => true,
                            'recorded_by_user_id' => $actorId,
                            'recorded_at' => $recAt,
                            'created_at' => $recAt,
                            'updated_at' => $recAt,
                        ]);
                    }
                }

                if ($warehouseStatus === 'COMPLETED') {
                    $sjItems = $sj ? ($suratJalanItemsBySj->get($sj->id) ?? collect()) : collect();
                    $completedQty = $sjItems->isNotEmpty()
                        ? (int) $sjItems->sum('jumlah_dikirim')
                        : max(1, $qtyPassed + $qtyRepair);
                    $rejectedQty = $qtyReject;
                    $readyLog = $logs->firstWhere('status_pesanan_id', 8);
                    $resultCompletedAt = $readyLog->created_at ?? $lastProcessEnd ?? $order->updated_at;

                    ServiceResult::query()->create([
                        'service_order_id' => $order->id,
                        'reconditioning_service_id' => $recondServiceId,
                        'actual_service_type' => $serviceType ?? 'Rekondisi Drum',
                        'completed_quantity' => $completedQty,
                        'unprocessed_or_rejected_quantity' => $rejectedQty,
                        'unit' => 'Drum',
                        'result_notes' => "{$completedQty} drum selesai direkondisi dan lolos QC.".($rejectedQty > 0 ? " {$rejectedQty} drum reject tidak diproses." : ''),
                        'completed_by_user_id' => $defaultGudangId,
                        'completed_at' => $resultCompletedAt,
                        'created_at' => $resultCompletedAt,
                        'updated_at' => $resultCompletedAt,
                    ]);
                }
            } else {
                // Pengangkutan Limbah
                if ($pg && $pg->waktu_selesai) {
                    $details = $pengangkutanDetailByPengangkutan->get($pg->id) ?? collect();
                    $actualSum = max(1, (int) round((float) $details->sum('jumlah_aktual')));
                    $docs = $dokumenAngkutByPengangkutan->get($pg->id) ?? collect();
                    $docText = $docs->map(fn ($d) => "{$d->jenis_dokumen} {$d->nomor_dokumen}")->implode(', ');

                    WarehouseReceipt::query()->create([
                        'service_order_id' => $order->id,
                        'receipt_number' => sprintf('ANG-%04d', $pg->id),
                        'received_at' => $pg->waktu_selesai,
                        'actual_received_quantity' => $actualSum,
                        'unit' => $requestedUnit,
                        'initial_condition' => "Pengangkutan limbah selesai menggunakan armada {$pg->plat_nomor} ({$pg->merek}).",
                        'discrepancy_notes' => $docText ? "Dokumen: {$docText}" : null,
                        'received_by_user_id' => $defaultGudangId,
                        'created_at' => $pg->waktu_selesai,
                        'updated_at' => $pg->waktu_selesai,
                    ]);

                    $completedQty = $actualSum;
                    $resultCompletedAt = $pg->waktu_selesai;

                    ServiceResult::query()->create([
                        'service_order_id' => $order->id,
                        'reconditioning_service_id' => null,
                        'actual_service_type' => 'Pengangkutan Limbah',
                        'completed_quantity' => $actualSum,
                        'unprocessed_or_rejected_quantity' => 0,
                        'unit' => $requestedUnit,
                        'result_notes' => "Limbah terangkut aktual {$actualSum} {$requestedUnit} dengan armada {$pg->plat_nomor}.".($docText ? " ({$docText})" : ''),
                        'completed_by_user_id' => $defaultGudangId,
                        'completed_at' => $pg->waktu_selesai,
                        'created_at' => $pg->waktu_selesai,
                        'updated_at' => $pg->waktu_selesai,
                    ]);
                }
            }

            $context[(int) $p->id] = [
                'order' => $order,
                'pesanan' => $p,
                'is_recond' => $isRecond,
                'latest_status_id' => $latestStatusId,
                'completed_qty' => $completedQty,
                'rejected_qty' => $rejectedQty,
                'result_completed_at' => $resultCompletedAt ?? $p->tanggal_pengajuan,
                'drum_items' => $isRecond ? ($drumItemsByPesanan->get($p->id) ?? collect()) : collect(),
                'limbah_items' => ! $isRecond ? ($limbahItemsByPesanan->get($p->id) ?? collect()) : collect(),
                'surat_jalan' => $sj,
                'surat_jalan_items' => $sj ? ($suratJalanItemsBySj->get($sj->id) ?? collect()) : collect(),
            ];
        }

        return $context;
    }

    /**
     * @param  array<int, array<string, mixed>>  $orderContext
     * @param  array<int, ClientCompany>  $clientCompanyMap
     * @param  array<int, User>  $userMap
     * @param  array<string, ServiceTariff>  $drumTariffMap
     * @param  array<string, ServiceTariff>  $limbahTariffMap
     * @return array{0: array<int, Invoice>, 1: array<int, Payment>}
     */
    private function seedInvoicesPaymentsAndDeliveryOrders(
        array $orderContext,
        array $clientCompanyMap,
        array $userMap,
        array $drumTariffMap,
        array $limbahTariffMap,
        int $defaultGudangId,
        int $defaultKeuanganId,
    ): array {
        $invoices = DB::table('invoice')->orderBy('id')->get();
        $pembayaranByInvoice = DB::table('pembayaran as pb')
            ->join('metode_bayar as mb', 'mb.id', '=', 'pb.metode_bayar_id')
            ->select('pb.*', 'mb.nama as metode_nama')
            ->orderBy('pb.id')
            ->get()
            ->groupBy('invoice_id');

        $invoiceMap = [];
        $paymentMap = [];

        foreach ($invoices as $inv) {
            $ctx = $orderContext[(int) $inv->pesanan_id] ?? null;
            if (! $ctx) {
                continue;
            }

            /** @var ServiceOrder $order */
            $order = $ctx['order'];
            $isRecond = $ctx['is_recond'];
            $issuerId = $userMap[(int) $inv->diterbitkan_oleh]->id ?? $defaultKeuanganId;

            $lineItemsData = [];
            $subtotal = 0.0;

            if ($isRecond) {
                foreach ($ctx['drum_items'] as $it) {
                    $qty = (int) $it->jumlah;
                    $price = (float) $it->harga_satuan;
                    $lineTotal = round($qty * $price, 2);
                    $subtotal += $lineTotal;

                    $shortTutup = str_contains($it->tutup, 'Open Top') ? 'Tutup Besar' : 'Tutup Kecil';
                    $tariff = $drumTariffMap["{$it->paket_drum_id}-{$it->jenis_drum_id}-{$it->harga_satuan}"]
                        ?? $drumTariffMap["{$it->paket_drum_id}-{$it->jenis_drum_id}"]
                        ?? null;

                    $lineItemsData[] = [
                        'service_tariff_id' => $tariff?->id,
                        'transaction_type' => ServiceOrder::TYPE_RECONDITIONING,
                        'description' => "Rekondisi Drum {$it->bahan} {$it->kapasitas_liter}L ({$shortTutup}) - {$it->paket_nama}",
                        'quantity' => $qty,
                        'unit' => 'Drum',
                        'unit_price' => $price,
                        'line_total' => $lineTotal,
                    ];
                }
            } else {
                foreach ($ctx['limbah_items'] as $it) {
                    $qty = max(1, (int) round((float) $it->jumlah_estimasi));
                    $price = (float) $it->harga_satuan;
                    $lineTotal = round($qty * $price, 2);
                    $subtotal += $lineTotal;

                    $tariff = $limbahTariffMap["{$it->limbah_id}-{$it->satuan_id}-{$it->harga_satuan}"]
                        ?? $limbahTariffMap["{$it->limbah_id}-{$it->satuan_id}"]
                        ?? null;

                    $lineItemsData[] = [
                        'service_tariff_id' => $tariff?->id,
                        'transaction_type' => ServiceOrder::TYPE_WASTE_TRANSPORT,
                        'description' => "Pengangkutan Limbah [{$it->kode_limbah}] {$it->limbah_nama}",
                        'quantity' => $qty,
                        'unit' => $it->satuan_nama,
                        'unit_price' => $price,
                        'line_total' => $lineTotal,
                    ];
                }
            }

            $invPayments = $pembayaranByInvoice->get($inv->id) ?? collect();
            $verifiedPayments = $invPayments->where('status_persetujuan_id', 2);
            $paidAmount = round((float) $verifiedPayments->sum('nominal'), 2);
            $balanceDue = round(max(0.0, $subtotal - $paidAmount), 2);
            $hasPending = $invPayments->contains(fn ($x) => (int) $x->status_persetujuan_id === 1);
            $hasRejected = $invPayments->contains(fn ($x) => (int) $x->status_persetujuan_id === 3);

            if ($balanceDue <= 0.01) {
                $invoiceStatus = 'PAID';
                $orderPaymentStatus = 'PAID';
                $paidAt = $verifiedPayments->max('waktu_verifikasi') ?? $verifiedPayments->max('tanggal_bayar');
            } elseif ($hasPending) {
                $invoiceStatus = $paidAmount > 0 ? 'PARTIALLY_PAID' : 'ISSUED';
                $orderPaymentStatus = 'PROOF_SUBMITTED';
                $paidAt = null;
            } elseif ($paidAmount > 0) {
                $invoiceStatus = 'PARTIALLY_PAID';
                $orderPaymentStatus = 'PARTIALLY_PAID';
                $paidAt = null;
            } elseif ($hasRejected) {
                $invoiceStatus = 'ISSUED';
                $orderPaymentStatus = 'PROOF_REJECTED';
                $paidAt = null;
            } else {
                $invoiceStatus = 'ISSUED';
                $orderPaymentStatus = 'UNPAID';
                $paidAt = null;
            }

            $invoiceModel = Invoice::query()->create([
                'invoice_number' => $inv->nomor_invoice,
                'invoice_slug' => str_replace('/', '-', $inv->nomor_invoice),
                'service_order_id' => $order->id,
                'client_company_id' => $order->client_company_id,
                'revision_number' => 0,
                'subtotal_amount' => $subtotal,
                'adjustment_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => $subtotal,
                'paid_amount' => $paidAmount,
                'balance_due' => $balanceDue,
                'status' => $invoiceStatus,
                'issue_date' => $inv->tanggal_terbit,
                'due_date' => $inv->tanggal_jatuh_tempo,
                'payment_instructions' => 'Transfer ke Rekening Resmi PT Pulo Kencana Drum Industri (DP minimum 50% sebelum pengiriman/pelunasan maksimal jatuh tempo).',
                'notes' => "Invoice resmi untuk pesanan {$order->order_number}.",
                'created_by_user_id' => $issuerId,
                'issued_at' => "{$inv->tanggal_terbit} 10:00:00",
                'paid_at' => $paidAt,
                'created_at' => "{$inv->tanggal_terbit} 10:00:00",
                'updated_at' => $paidAt ?? "{$inv->tanggal_terbit} 10:00:00",
            ]);
            $invoiceMap[(int) $inv->id] = $invoiceModel;

            $billableTx = BillableTransaction::query()->create([
                'transaction_code' => sprintf('TX-PKDI-%04d', $inv->id),
                'service_order_id' => $order->id,
                'client_company_id' => $order->client_company_id,
                'transaction_type' => $order->transaction_type,
                'drum_product_id' => $order->drum_product_id,
                'reconditioning_service_id' => $order->reconditioning_service_id,
                'product_name_snapshot' => $order->service_category ?? 'Layanan PKDI',
                'service_name_snapshot' => $order->service_type,
                'billable_quantity' => max(1, (int) $order->requested_quantity),
                'rejected_quantity' => (int) $ctx['rejected_qty'],
                'unit' => $order->requested_unit,
                'billing_status' => BillableTransaction::STATUS_INVOICED,
                'invoice_id' => $invoiceModel->id,
                'ready_at' => $ctx['result_completed_at'],
                'invoiced_at' => "{$inv->tanggal_terbit} 10:00:00",
                'created_by_user_id' => $defaultGudangId,
                'notes' => "Ditagihkan pada {$inv->nomor_invoice}.",
                'created_at' => "{$inv->tanggal_terbit} 10:00:00",
                'updated_at' => "{$inv->tanggal_terbit} 10:00:00",
            ]);

            foreach ($lineItemsData as $itemData) {
                InvoiceItem::query()->create([
                    'invoice_id' => $invoiceModel->id,
                    'service_tariff_id' => $itemData['service_tariff_id'],
                    'billable_transaction_id' => $billableTx->id,
                    'transaction_type' => $itemData['transaction_type'],
                    'description' => $itemData['description'],
                    'quantity' => $itemData['quantity'],
                    'unit' => $itemData['unit'],
                    'unit_price' => $itemData['unit_price'],
                    'line_total' => $itemData['line_total'],
                ]);
            }

            $order->update([
                'billing_status' => 'INVOICED',
                'payment_status' => $orderPaymentStatus,
            ]);

            foreach ($invPayments as $pb) {
                $verifStatus = match ((int) $pb->status_persetujuan_id) {
                    2 => 'VERIFIED',
                    3 => 'REJECTED',
                    default => 'PENDING_VERIFICATION',
                };
                $submittedById = $userMap[(int) $pb->dicatat_oleh]->id ?? $order->created_by_user_id;
                $verifiedById = $pb->diverifikasi_oleh ? ($userMap[(int) $pb->diverifikasi_oleh]->id ?? $defaultKeuanganId) : null;

                $paymentModel = Payment::query()->create([
                    'payment_number' => sprintf('PAY-2026-%04d', $pb->id),
                    'invoice_id' => $invoiceModel->id,
                    'client_company_id' => $order->client_company_id,
                    'payment_date' => $pb->tanggal_bayar,
                    'amount' => $pb->nominal,
                    'payment_method' => $pb->metode_nama,
                    'reference_number' => $pb->nomor_referensi,
                    'proof_file_path' => $pb->file_bukti,
                    'verification_status' => $verifStatus,
                    'verification_notes' => $pb->catatan ?? ($verifStatus === 'VERIFIED' ? 'Pembayaran terverifikasi oleh Keuangan.' : null),
                    'submitted_by_user_id' => $submittedById,
                    'verified_by_user_id' => $verifiedById,
                    'verified_at' => $pb->waktu_verifikasi,
                    'created_at' => "{$pb->tanggal_bayar} 10:00:00",
                    'updated_at' => $pb->waktu_verifikasi ?? "{$pb->tanggal_bayar} 10:00:00",
                ]);
                $paymentMap[(int) $pb->id] = $paymentModel;
            }

            // Surat Jalan (surat_jalan -> delivery_orders)
            $sj = $ctx['surat_jalan'];
            if ($sj) {
                $sjQty = (int) $ctx['surat_jalan_items']->sum('jumlah_dikirim');
                $doIssuerId = $userMap[(int) $sj->diterbitkan_oleh]->id ?? $defaultKeuanganId;
                $isDelivered = in_array((int) $ctx['latest_status_id'], [11, 12], true);

                DeliveryOrder::query()->create([
                    'delivery_order_number' => $sj->nomor_surat_jalan,
                    'service_order_id' => $order->id,
                    'invoice_id' => $invoiceModel->id,
                    'client_company_id' => $order->client_company_id,
                    'cargo_summary' => "{$order->service_category} - {$order->service_type} ({$sjQty} Drum)",
                    'quantity_released' => max(1, $sjQty),
                    'unit' => 'Drum',
                    'vehicle_type' => trim("{$sj->jenis_armada} - {$sj->merek}"),
                    'license_plate' => $sj->plat_nomor,
                    'driver_name' => 'Sopir Armada PKDI',
                    'status' => $isDelivered ? 'USED_AT_GATE_OUT' : 'ISSUED',
                    'notes' => "Diterbitkan dari {$sj->nomor_surat_jalan}.",
                    'issued_by_user_id' => $doIssuerId,
                    'issued_at' => $sj->tanggal_terbit,
                    'used_at' => $isDelivered ? $order->delivered_at : null,
                    'created_at' => $sj->tanggal_terbit,
                    'updated_at' => $sj->tanggal_terbit,
                ]);
            }
        }

        return [$invoiceMap, $paymentMap];
    }

    /**
     * @param  array<int, Payment>  $paymentMap
     * @param  array<int, Invoice>  $invoiceMap
     * @param  array<int, User>  $userMap
     */
    private function seedExpensesAndCashbook(
        array $paymentMap,
        array $invoiceMap,
        array $userMap,
        int $defaultKeuanganId,
    ): void {
        // 1. Kas Masuk (IN) dari seluruh pembayaran terverifikasi
        $cbSeq = 1;
        foreach ($paymentMap as $payment) {
            if ($payment->verification_status !== 'VERIFIED') {
                continue;
            }
            $invNumber = $payment->invoice?->invoice_number ?? '-';
            CashbookEntry::query()->create([
                'entry_number' => sprintf('CB-IN-%04d', $cbSeq++),
                'entry_type' => 'IN',
                'transaction_date' => $payment->verified_at?->toDateString() ?? $payment->payment_date->toDateString(),
                'source_type' => 'INVOICE_PAYMENT',
                'payment_id' => $payment->id,
                'description' => "Penerimaan pembayaran {$invNumber} ({$payment->payment_method})",
                'amount' => $payment->amount,
                'status' => 'ACTIVE',
                'recorded_by_user_id' => $payment->verified_by_user_id ?? $defaultKeuanganId,
                'created_at' => $payment->verified_at ?? $payment->created_at,
                'updated_at' => $payment->verified_at ?? $payment->updated_at,
            ]);
        }

        // 2. Biaya Operasional & Kas Keluar (OUT) dari jurnal pengeluaran kas/bank (1-1002 / 1-1001 kredit)
        $suppliersByGrn = DB::table('stok_masuk as sm')
            ->join('supplier as s', 's.id', '=', 'sm.supplier_id')
            ->select('sm.nomor_dokumen', 's.nama as supplier_nama')
            ->get()
            ->keyBy('nomor_dokumen');

        $outflowRows = DB::table('jurnal as j')
            ->join('jurnal_detail as jd_credit', function ($join): void {
                $join->on('jd_credit.jurnal_id', '=', 'j.id')
                    ->whereIn('jd_credit.akun_id', [1, 2])
                    ->where('jd_credit.kredit', '>', 0);
            })
            ->join('jurnal_detail as jd_debit', function ($join): void {
                $join->on('jd_debit.jurnal_id', '=', 'j.id')
                    ->where('jd_debit.debit', '>', 0);
            })
            ->join('akun as a', 'a.id', '=', 'jd_debit.akun_id')
            ->select(
                'j.id as jurnal_id',
                'j.nomor_jurnal',
                'j.tanggal',
                'j.keterangan',
                'j.dibuat_oleh',
                'a.kode as akun_kode',
                'a.nama as akun_nama',
                'jd_debit.debit as amount',
                'jd_credit.akun_id as source_akun_id'
            )
            ->orderBy('j.tanggal')
            ->orderBy('j.id')
            ->get();

        $expSeq = 1;
        $cbOutSeq = 1;

        foreach ($outflowRows as $row) {
            $category = match ($row->akun_kode) {
                '1-1200' => 'Pembelian Bahan',
                '5-1002' => 'Gaji & Upah',
                '5-1003' => 'Perawatan Armada',
                '5-1004' => 'Pajak & KIR Armada',
                '5-1005' => 'Utilitas',
                default => $row->akun_nama,
            };

            $vendor = match ($row->akun_kode) {
                '5-1002' => 'Karyawan PT PKDI',
                '5-1003' => 'Bengkel Rekanan Armada',
                '5-1004' => 'Samsat / Dishub',
                '5-1005' => 'PLN & PDAM',
                default => 'Supplier PKDI',
            };

            if ($row->akun_kode === '1-1200' && preg_match('/(GRN\/\d{4}\/\d+)/', $row->keterangan, $m)) {
                $vendor = $suppliersByGrn->get($m[1])->supplier_nama ?? $vendor;
            }

            $recorderId = $userMap[(int) $row->dibuat_oleh]->id ?? $defaultKeuanganId;
            $method = (int) $row->source_akun_id === 1 ? 'Tunai' : 'Transfer Bank';

            $expense = OperationalExpense::query()->create([
                'expense_number' => sprintf('EXP-2026-%04d', $expSeq++),
                'expense_category' => $category,
                'expense_date' => $row->tanggal,
                'description' => "[{$row->nomor_jurnal}] {$row->keterangan}",
                'amount' => $row->amount,
                'recipient_or_vendor' => $vendor,
                'payment_method' => $method,
                'status' => 'RECORDED',
                'recorded_by_user_id' => $recorderId,
                'created_at' => "{$row->tanggal} 14:00:00",
                'updated_at' => "{$row->tanggal} 14:00:00",
            ]);

            CashbookEntry::query()->create([
                'entry_number' => sprintf('CB-OUT-%04d', $cbOutSeq++),
                'entry_type' => 'OUT',
                'transaction_date' => $row->tanggal,
                'source_type' => 'OPERATIONAL_EXPENSE',
                'operational_expense_id' => $expense->id,
                'description' => "{$category}: {$row->keterangan}",
                'amount' => $row->amount,
                'status' => 'ACTIVE',
                'recorded_by_user_id' => $recorderId,
                'created_at' => "{$row->tanggal} 14:00:00",
                'updated_at' => "{$row->tanggal} 14:00:00",
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $orderContext
     */
    private function seedNotificationsAndAudit(
        array $orderContext,
        int $defaultGudangId,
        int $defaultKeuanganId,
        int $defaultOwnerId,
    ): void {
        // Notifikasi pembayaran menunggu verifikasi (INV/2026/0054)
        $pendingPay = Payment::query()->with('invoice.serviceOrder')->where('verification_status', 'PENDING_VERIFICATION')->first();
        if ($pendingPay && $pendingPay->invoice) {
            AppNotification::query()->create([
                'recipient_user_id' => $defaultKeuanganId,
                'service_order_id' => $pendingPay->invoice->service_order_id,
                'title' => "Verifikasi pembayaran {$pendingPay->invoice->invoice_number}",
                'message' => 'Pembayaran Rp '.number_format((float) $pendingPay->amount, 0, ',', '.')." menunggu verifikasi Keuangan (ref {$pendingPay->reference_number}).",
                'tone' => 'warning',
                'icon' => 'wallet',
                'action_url' => "/finance/invoices/{$pendingPay->invoice->invoice_slug}",
            ]);
        }

        // Notifikasi pesanan aktif terbaru untuk Kepala Gudang
        $activeOrders = ServiceOrder::query()
            ->whereIn('order_status', ['WAITING_RECEIPT', 'IN_TRANSIT', 'RECEIVED', 'SORTING', 'IN_PROGRESS'])
            ->latest('submitted_at')
            ->limit(3)
            ->get();

        foreach ($activeOrders as $ord) {
            AppNotification::query()->create([
                'recipient_user_id' => $defaultGudangId,
                'service_order_id' => $ord->id,
                'title' => "Pesanan aktif {$ord->order_number}",
                'message' => "{$ord->origin_company_name} - {$ord->service_type} ({$ord->requested_quantity} {$ord->requested_unit}).",
                'tone' => 'info',
                'icon' => 'warehouse',
                'action_url' => "/warehouse/orders/{$ord->order_slug}",
            ]);
        }

        // Jejak audit impor awal
        AuditLog::query()->create([
            'user_id' => $defaultOwnerId,
            'user_role' => User::ROLE_OWNER,
            'action' => 'DATABASE_IMPORTED',
            'auditable_type' => User::class,
            'auditable_id' => $defaultOwnerId,
            'old_values' => null,
            'new_values' => [
                'order_number' => 'PULO-KENCANA-DRUM',
                'summary' => 'Sinkronisasi data dari database pulo_kencana_drum (12 client, 77 pesanan, 76 invoice, 140 pembayaran, 137 biaya operasional).',
            ],
            'reason' => 'Impor data operasional nyata dari pulo_kencana_drum.sql',
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        // Tambah log audit beberapa transaksi terbaru agar halaman Jejak Audit Owner langsung informatif
        $recentOrders = ServiceOrder::query()->latest('updated_at')->limit(10)->get();
        foreach ($recentOrders as $ord) {
            AuditLog::query()->create([
                'user_id' => $defaultGudangId,
                'user_role' => User::ROLE_KEPALA_GUDANG,
                'action' => 'ORDER_STATUS_SYNCED',
                'auditable_type' => ServiceOrder::class,
                'auditable_id' => $ord->id,
                'new_values' => [
                    'order_number' => $ord->order_number,
                    'order_status' => $ord->order_status,
                    'summary' => "Pesanan {$ord->order_number} ({$ord->origin_company_name}) berstatus {$ord->order_status}.",
                ],
                'reason' => 'Riwayat transaksi dari database pulo_kencana_drum',
                'ip_address' => '127.0.0.1',
                'created_at' => $ord->updated_at ?? now(),
            ]);
        }
    }

    /**
     * Memetakan `status_pesanan.id` (1..20) dari `pulo_kencana_drum` ke [order_status, warehouse_status].
     *
     * @return array{0: string, 1: string}
     */
    private function mapOrderStatuses(int $statusPesananId): array
    {
        return match ($statusPesananId) {
            // Layanan 1: Rekondisi Drum
            1 => ['UNDER_REVIEW', 'PENDING_REVIEW'],
            2 => ['WAITING_RECEIPT', 'WAITING_RECEIPT'],
            3 => ['REJECTED', 'REJECTED'],
            4 => ['IN_TRANSIT', 'IN_TRANSIT'],
            5 => ['RECEIVED', 'RECEIVED'],
            6 => ['SORTING', 'SORTING'],
            7 => ['IN_PROGRESS', 'IN_PROGRESS'],
            8, 9 => ['READY', 'COMPLETED'],
            10 => ['IN_DELIVERY', 'COMPLETED'],
            11 => ['DELIVERED', 'COMPLETED'],
            12 => ['COMPLETED', 'COMPLETED'],
            // Layanan 2: Pengangkutan Limbah
            13 => ['UNDER_REVIEW', 'PENDING_REVIEW'],
            14 => ['APPROVED', 'APPROVED'],
            15 => ['REJECTED', 'REJECTED'],
            16 => ['TRANSPORT_SCHEDULED', 'TRANSPORT_SCHEDULED'],
            17 => ['IN_TRANSIT', 'IN_TRANSIT'],
            18 => ['RECEIVED', 'RECEIVED'],
            19 => ['READY', 'COMPLETED'],
            20 => ['COMPLETED', 'COMPLETED'],
            default => ['UNDER_REVIEW', 'PENDING_REVIEW'],
        };
    }
}
