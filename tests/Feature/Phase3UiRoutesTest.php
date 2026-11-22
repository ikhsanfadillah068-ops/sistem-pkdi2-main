<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\TestScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase3UiRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_public_and_authenticated_role_pages_render_without_demo_text(): void
    {
        $this->seed(TestScenarioSeeder::class);

        foreach (['/login', '/register'] as $guestUrl) {
            $response = $this->get($guestUrl);
            $response->assertStatus(200);
            $response->assertDontSee('—', false);
            $response->assertDontSee('Akses Cepat Akun Demo');
            $response->assertDontSee('/preview/role/');
        }

        $roleUrls = [
            User::ROLE_CLIENT => [
                '/settings',
                '/client/dashboard',
                '/client/orders',
                '/client/orders/create',
                '/client/orders/senin-16-11-26-1',
                '/client/orders/selasa-17-11-26-5',
                '/client/invoices',
                '/client/invoices/INV-PKDI-2026-11-0001',
                '/client/company',
            ],
            User::ROLE_KEPALA_GUDANG => [
                '/warehouse/dashboard',
                '/warehouse/inspections',
                '/warehouse/processes',
                '/warehouse/orders/selasa-17-11-26-4',
                '/warehouse/orders/selasa-17-11-26-5',
            ],
            User::ROLE_KEUANGAN => [
                '/finance/dashboard',
                '/finance/billable-orders',
                '/finance/billable-orders/selasa-17-11-26-3',
                '/finance/invoices',
                '/finance/invoices/INV-PKDI-2026-11-0001',
                '/finance/tariffs',
                '/finance/expenses',
                '/finance/cashbook',
                '/finance/reports',
            ],
            User::ROLE_OWNER => [
                '/owner/dashboard',
                '/owner/financial-summary',
                '/owner/transactions',
                '/owner/transactions/senin-16-11-26-1',
                '/owner/audit-logs',
            ],
        ];

        foreach ($roleUrls as $role => $urls) {
            $user = User::query()->where('role', $role)->firstOrFail();

            foreach ($urls as $url) {
                $response = $this->actingAs($user)->get($url);
                $response->assertStatus(200);
                $response->assertDontSee('—', false);
                $response->assertDontSee('[DATA DEMO]');
                $response->assertDontSee('[DEMO]');
                $response->assertDontSee('Pindah Akun Demo');
            }
        }
    }

    public function test_unauthenticated_users_accessing_dashboard_links_are_blocked_and_redirected_to_login(): void
    {
        $protectedUrls = [
            '/client/dashboard',
            '/client/orders',
            '/warehouse/dashboard',
            '/finance/dashboard',
            '/owner/dashboard',
            '/settings',
        ];

        foreach ($protectedUrls as $url) {
            $this->get($url)->assertRedirect('/login');
        }

        // Route Satpam dan preview demo tidak tersedia
        $this->get('/gate/dashboard')->assertNotFound();
        $this->get('/preview/role/owner')->assertNotFound();
    }

    public function test_revision_rules_light_auth_order_format_and_owner_trends(): void
    {
        $login = $this->get('/login');
        $login->assertStatus(200);
        $login->assertSee('data-theme="light"', false);
        $login->assertDontSee('data-theme-toggle', false);

        $register = $this->get('/register');
        $register->assertStatus(200);
        $register->assertSee('data-theme="light"', false);
        $register->assertDontSee('data-theme-toggle', false);

        $this->seed(TestScenarioSeeder::class);
        $client = User::query()->where('email', 'client@pkdi.co.id')->firstOrFail();

        $clientOrders = $this->actingAs($client)->get('/client/orders');
        $clientOrders->assertStatus(200);
        $clientOrders->assertSee('senin-16/11/26-1');
        $clientOrders->assertDontSee('PKDI-ORD-');

        $owner = User::query()->where('role', User::ROLE_OWNER)->firstOrFail();
        $ownerDash = $this->actingAs($owner)->get('/owner/dashboard');
        $ownerDash->assertStatus(200);
        $ownerDash->assertSee('Tren Naik-Turun Pendapatan Jasa &amp; Biaya Operasional (12 Bulan)', false);
        $ownerDash->assertSee('data-chart="area"', false);
        $ownerDash->assertSee('data-smooth', false);
        $ownerDash->assertSee('Aktivitas Terbaru Lintas Bagian');
    }

    public function test_client_order_detail_renders_compact_stepper_without_raw_enums_and_empty_orders_state(): void
    {
        $this->seed();
        $client = User::query()->where('email', 'client@pkdi.co.id')->firstOrFail();

        $emptyOrders = $this->actingAs($client)->get('/client/orders');
        $emptyOrders->assertOk()
            ->assertSee('Belum ada pesanan')
            ->assertSee('Buat Pesanan Baru');

        $this->seed(TestScenarioSeeder::class);

        $readyOrder = $this->actingAs($client)->get('/client/orders/selasa-17-11-26-3');
        $readyOrder->assertOk()
            ->assertSee('Tahap 5 dari 5')
            ->assertSee('Diajukan')
            ->assertSee('Diterima di Gudang')
            ->assertSee('Pemeriksaan & Sortir')
            ->assertSee('Proses Rekondisi')
            ->assertSee('Rekondisi Selesai &amp; Siap Serah', false)
            ->assertSee('Riwayat Progres')
            ->assertSee('step-done', false)
            ->assertSee('step-current', false)
            ->assertSee('<details>', false)
            ->assertDontSee('Masuk Gerbang')
            ->assertDontSee('Status Per Jalur')
            ->assertDontSee('CHECKED_IN_APPROVED')
            ->assertDontSee('UNBILLED');
    }
}
