<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ClientCompany;
use App\Models\Invoice;
use App\Models\ServiceOrder;
use App\Models\ServiceTariff;
use App\Models\User;
use Database\Seeders\TestScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase4FoundationAndAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_register_and_login_with_hashed_password_and_audit_log(): void
    {
        $response = $this->post('/register', [
            'company_name' => 'PT Kimia Nusantara Mandiri',
            'pic_name' => 'Rina Kartika',
            'phone' => '081299887766',
            'address' => 'Kawasan Industri Jababeka Blok K-10, Cikarang, Bekasi',
            'email' => 'RINA@KIMIANUSANTARA.CO.ID',
            'password' => 'SecretPass123!',
            'password_confirmation' => 'SecretPass123!',
            'role' => 'owner',
        ]);

        $response->assertRedirect('/client/dashboard');
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'rina@kimianusantara.co.id')->firstOrFail();
        $this->assertSame(User::ROLE_CLIENT, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->client_company_id);
        $this->assertNotSame('SecretPass123!', $user->password);
        $this->assertTrue(Hash::check('SecretPass123!', $user->password));

        $this->assertDatabaseHas('client_companies', [
            'id' => $user->client_company_id,
            'company_name' => 'PT Kimia Nusantara Mandiri',
            'pic_name' => 'Rina Kartika',
            'verification_status' => 'ACTIVE',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'user_role' => User::ROLE_CLIENT,
            'action' => 'CLIENT_REGISTERED',
        ]);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();

        $loginResponse = $this->post('/login', [
            'email' => 'rina@kimianusantara.co.id',
            'password' => 'SecretPass123!',
        ]);

        $loginResponse->assertRedirect('/client/dashboard');
        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'USER_LOGIN',
        ]);
    }

    public function test_all_four_active_users_can_login_with_their_role_matched_credentials(): void
    {
        $this->seed();

        $accounts = [
            ['email' => 'client@pkdi.co.id', 'password' => 'client123', 'role' => User::ROLE_CLIENT, 'redirect' => '/client/dashboard'],
            ['email' => 'kepalagudang@pkdi.co.id', 'password' => 'gudang123', 'role' => User::ROLE_KEPALA_GUDANG, 'redirect' => '/warehouse/dashboard'],
            ['email' => 'keuangan@pkdi.co.id', 'password' => 'keuangan123', 'role' => User::ROLE_KEUANGAN, 'redirect' => '/finance/dashboard'],
            ['email' => 'owner@pkdi.co.id', 'password' => 'owner123', 'role' => User::ROLE_OWNER, 'redirect' => '/owner/dashboard'],
        ];

        foreach ($accounts as $acc) {
            $response = $this->post('/login', [
                'email' => $acc['email'],
                'password' => $acc['password'],
            ]);

            $response->assertRedirect($acc['redirect']);
            $this->assertSame($acc['role'], auth()->user()?->role);

            $this->post('/logout')->assertRedirect('/login');
        }
    }

    public function test_login_validation_enforces_exact_empty_format_and_credential_messages(): void
    {
        $this->seed();

        // 1. Semua kosong
        $this->from('/login')
            ->post('/login', ['email' => '', 'password' => ''])
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'login' => 'Email dan password wajib diisi.',
                'email' => 'Email wajib diisi.',
                'password' => 'Password wajib diisi.',
            ]);

        $this->from('/login')
            ->followingRedirects()
            ->post('/login', ['email' => '', 'password' => ''])
            ->assertOk()
            ->assertDontSee('Gagal Masuk')
            ->assertSee('Email wajib diisi.')
            ->assertSee('Password wajib diisi.');

        // 2. Email kosong
        $this->from('/login')
            ->post('/login', ['email' => '', 'password' => 'client123'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Email wajib diisi.']);

        // 3. Password kosong
        $this->from('/login')
            ->post('/login', ['email' => 'client@pkdi.co.id', 'password' => ''])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['password' => 'Password wajib diisi.']);

        // 4. Email tidak valid
        $this->from('/login')
            ->post('/login', ['email' => 'bukan-email-valid', 'password' => 'client123'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Masukkan alamat email yang valid.']);

        // 5. Password terlalu pendek (< 8 karakter)
        $this->from('/login')
            ->post('/login', ['email' => 'client@pkdi.co.id', 'password' => '1234567'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['password' => 'Password minimal 8 karakter.']);

        // 6. Email belum terdaftar
        $this->from('/login')
            ->post('/login', ['email' => 'belumterdaftar@pkdi.co.id', 'password' => 'password123'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Email atau password tidak sesuai.']);

        // 7. Password salah
        $this->from('/login')
            ->post('/login', ['email' => 'client@pkdi.co.id', 'password' => 'passwordsalah'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Email atau password tidak sesuai.']);

        // Halaman login mengaktifkan validasi kustom tanpa teks instruksi berlebih
        $loginPage = $this->get('/login');
        $loginPage->assertOk()
            ->assertSee('text-danger', false)
            ->assertDontSee('Kolom bertanda bintang wajib diisi.')
            ->assertSee('data-validate-form="login"', false);
    }

    public function test_roles_cannot_open_routes_belonging_to_other_roles_and_menus_are_separated_on_server(): void
    {
        $this->seed();

        $client = User::query()->where('role', User::ROLE_CLIENT)->firstOrFail();
        $gudang = User::query()->where('role', User::ROLE_KEPALA_GUDANG)->firstOrFail();
        $keuangan = User::query()->where('role', User::ROLE_KEUANGAN)->firstOrFail();
        $owner = User::query()->where('role', User::ROLE_OWNER)->firstOrFail();

        $this->actingAs($client)->get('/warehouse/dashboard')->assertForbidden();
        $this->actingAs($client)->get('/finance/dashboard')->assertForbidden();
        $this->actingAs($client)->get('/owner/dashboard')->assertForbidden();

        $this->actingAs($gudang)->get('/client/dashboard')->assertForbidden();
        $this->actingAs($gudang)->get('/finance/dashboard')->assertForbidden();
        $this->actingAs($gudang)->get('/owner/dashboard')->assertForbidden();

        $this->actingAs($keuangan)->get('/client/dashboard')->assertForbidden();
        $this->actingAs($keuangan)->get('/warehouse/dashboard')->assertForbidden();
        $this->actingAs($keuangan)->get('/owner/dashboard')->assertForbidden();

        $this->actingAs($owner)->get('/client/dashboard')->assertForbidden();
        $this->actingAs($owner)->get('/warehouse/dashboard')->assertForbidden();
        $this->actingAs($owner)->get('/finance/dashboard')->assertForbidden();

        $warehousePage = $this->actingAs($gudang)->get('/warehouse/dashboard');
        $warehousePage->assertStatus(200);
        $warehousePage->assertSee('Antrean Gudang');
        $warehousePage->assertDontSee('Jejak Audit Sistem');
        $warehousePage->assertDontSee('Referensi Tarif Jasa');
    }

    public function test_client_a_cannot_view_orders_or_invoices_of_client_b_by_url_slug_or_id(): void
    {
        $this->seed(TestScenarioSeeder::class);

        $clientA = User::query()->where('email', 'client@pkdi.co.id')->firstOrFail();
        $companyB = ClientCompany::factory()->create(['company_name' => 'PT Mitra Logistik Kedua']);
        $clientB = User::factory()->client($companyB)->create(['email' => 'client2@mitralogistik.co.id']);

        $orderOfClientA = ServiceOrder::query()->where('order_number', 'senin-16/11/26-1')->firstOrFail();
        $orderOfClientB = ServiceOrder::factory()->create([
            'order_number' => 'rabu-18/11/26-99',
            'order_slug' => 'rabu-18-11-26-99',
            'client_company_id' => $companyB->id,
            'created_by_user_id' => $clientB->id,
        ]);

        $invoiceOfClientA = Invoice::query()->where('invoice_number', 'INV/PKDI/2026/11/0001')->firstOrFail();
        $invoiceOfClientB = Invoice::factory()->create([
            'invoice_number' => 'INV/PKDI/2026/11/0099',
            'invoice_slug' => 'INV-PKDI-2026-11-0099',
            'service_order_id' => $orderOfClientB->id,
            'client_company_id' => $companyB->id,
        ]);

        $this->actingAs($clientA)->get('/client/orders/'.$orderOfClientA->order_slug)->assertOk();
        $this->actingAs($clientA)->get('/client/invoices/'.$invoiceOfClientA->invoice_slug)->assertOk();

        $this->actingAs($clientA)->get('/client/orders/'.$orderOfClientB->order_slug)->assertForbidden();
        $this->actingAs($clientA)->get('/client/orders/'.$orderOfClientB->id)->assertForbidden();
        $this->actingAs($clientA)->get('/client/invoices/'.$invoiceOfClientB->invoice_slug)->assertForbidden();
        $this->actingAs($clientA)->get('/client/invoices/'.$invoiceOfClientB->id)->assertForbidden();

        $this->actingAs($clientB)->get('/client/orders/'.$orderOfClientA->order_slug)->assertForbidden();
        $this->actingAs($clientB)->get('/client/orders/'.$orderOfClientA->id)->assertForbidden();
        $this->actingAs($clientB)->get('/client/invoices/'.$invoiceOfClientA->invoice_slug)->assertForbidden();
        $this->actingAs($clientB)->get('/client/invoices/'.$invoiceOfClientA->id)->assertForbidden();
    }

    public function test_seeder_creates_four_active_users_master_data_and_one_client_company_with_zero_dummy_transactions(): void
    {
        $this->seed();

        $this->assertSame(4, User::query()->count());
        $this->assertSame(1, ClientCompany::query()->count());

        $rolesInDb = User::query()->pluck('role')->unique()->all();
        foreach ($rolesInDb as $role) {
            $this->assertContains($role, User::ROLES);
        }
        $this->assertNotContains('admin', $rolesInDb);
        $this->assertNotContains(User::ROLE_SATPAM, $rolesInDb);

        $this->assertSame(0, ServiceOrder::query()->count());
        $this->assertSame(0, Invoice::query()->count());
        $this->assertSame(0, ServiceTariff::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_profile_and_password_update_prevents_role_escalation_and_records_audit_log(): void
    {
        $this->seed();
        $client = User::query()->where('email', 'client@pkdi.co.id')->firstOrFail();

        $this->actingAs($client)->put('/settings/profile', [
            'name' => 'Budi Santoso Terbarukan',
            'email' => 'client.baru@pkdi.co.id',
            'role' => User::ROLE_OWNER,
            'is_active' => 0,
        ])->assertRedirect();

        $client->refresh();
        $this->assertSame('Budi Santoso Terbarukan', $client->name);
        $this->assertSame('client.baru@pkdi.co.id', $client->email);
        $this->assertSame(User::ROLE_CLIENT, $client->role);
        $this->assertTrue($client->is_active);

        $this->assertTrue(
            AuditLog::query()
                ->where('user_id', $client->id)
                ->where('action', 'USER_PROFILE_UPDATED')
                ->exists()
        );
    }

    public function test_migrations_create_all_approved_data_dictionary_tables_and_columns(): void
    {
        $expectedTables = [
            'client_companies' => ['id', 'company_code', 'company_name', 'pic_name', 'email', 'phone', 'address', 'verification_status'],
            'users' => ['id', 'client_company_id', 'name', 'email', 'phone', 'password', 'role', 'is_active', 'last_login_at'],
            'service_tariffs' => ['id', 'tariff_code', 'name', 'service_category', 'cargo_type', 'service_type', 'unit', 'unit_price', 'effective_from'],
            'service_orders' => ['id', 'order_number', 'order_slug', 'client_company_id', 'created_by_user_id', 'service_category', 'cargo_type', 'service_type', 'requested_quantity', 'vehicle_type', 'license_plate', 'driver_name', 'order_status', 'gate_status', 'warehouse_status', 'payment_status'],
            'order_status_histories' => ['id', 'service_order_id', 'track_type', 'from_status', 'to_status', 'changed_by_user_id', 'notes', 'is_visible_to_client'],
            'vehicle_gate_logs' => ['id', 'service_order_id', 'delivery_order_id', 'check_type', 'actual_license_plate', 'actual_vehicle_type', 'actual_driver_name', 'decision', 'hold_or_note_reason', 'checked_by_user_id'],
            'warehouse_inspections' => ['id', 'service_order_id', 'actual_cargo_type', 'actual_quantity', 'cargo_condition', 'decision', 'inspected_by_user_id'],
            'service_processes' => ['id', 'service_order_id', 'process_stage', 'progress_notes', 'processed_qty', 'recorded_by_user_id'],
            'service_results' => ['id', 'service_order_id', 'actual_service_type', 'completed_quantity', 'unprocessed_or_rejected_quantity', 'completed_by_user_id'],
            'invoices' => ['id', 'invoice_number', 'invoice_slug', 'service_order_id', 'client_company_id', 'total_amount', 'paid_amount', 'balance_due', 'status', 'created_by_user_id'],
            'invoice_items' => ['id', 'invoice_id', 'service_tariff_id', 'description', 'quantity', 'unit_price', 'line_total'],
            'payments' => ['id', 'payment_number', 'invoice_id', 'client_company_id', 'amount', 'payment_method', 'verification_status', 'submitted_by_user_id'],
            'delivery_orders' => ['id', 'delivery_order_number', 'service_order_id', 'invoice_id', 'client_company_id', 'quantity_released', 'license_plate', 'status', 'issued_by_user_id'],
            'operational_expenses' => ['id', 'expense_number', 'expense_category', 'expense_date', 'amount', 'status', 'recorded_by_user_id'],
            'cashbook_entries' => ['id', 'entry_number', 'entry_type', 'transaction_date', 'source_type', 'payment_id', 'operational_expense_id', 'amount', 'status'],
            'notifications' => ['id', 'recipient_user_id', 'service_order_id', 'title', 'message', 'action_url', 'read_at'],
            'audit_logs' => ['id', 'user_id', 'user_role', 'action', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'reason', 'created_at'],
        ];

        foreach ($expectedTables as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Tabel {$table} harus tersedia.");
            $this->assertTrue(Schema::hasColumns($table, $columns), "Kolom wajib pada tabel {$table} harus lengkap.");
        }
    }
}
