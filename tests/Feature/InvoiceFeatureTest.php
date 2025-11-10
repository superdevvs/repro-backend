<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Shoot;
use App\Models\Service;
use App\Models\User;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_generation_creates_records_and_links_shoots(): void
    {
        $photographer = User::factory()->photographer()->create();
        $client = User::factory()->create();
        $service = Service::factory()->create();

        $start = Carbon::now()->subWeek()->startOfWeek();
        $end = $start->copy()->endOfWeek();

        collect(range(0, 1))->each(function (int $index) use ($photographer, $client, $service, $start) {
            $shoot = Shoot::factory()->create([
                'photographer_id' => $photographer->id,
                'client_id' => $client->id,
                'service_id' => $service->id,
                'scheduled_date' => $start->copy()->addDays($index + 1),
                'base_quote' => 150 + ($index * 25),
                'tax_amount' => 10,
                'total_quote' => 160 + ($index * 25),
            ]);

            Payment::create([
                'shoot_id' => $shoot->id,
                'amount' => 160 + ($index * 25),
                'currency' => 'USD',
                'square_payment_id' => (string) Str::uuid(),
                'square_order_id' => (string) Str::uuid(),
                'status' => Payment::STATUS_COMPLETED,
                'processed_at' => now(),
            ]);

        });

        $serviceInstance = app(InvoiceService::class);
        $invoices = $serviceInstance->generateForPeriod($start, $end);

        $this->assertCount(1, $invoices);
        $invoice = $invoices->first();

        $this->assertEquals($photographer->id, $invoice->photographer_id);
        $this->assertSame(2, $invoice->shoots()->count());
        $this->assertEquals(345.0, (float) $invoice->total_amount);
        $this->assertEquals(345.0, (float) $invoice->amount_paid);
    }

    public function test_admin_can_list_and_mark_invoice_paid(): void
    {
        $admin = User::factory()->admin()->create();
        $photographer = User::factory()->photographer()->create();
        $client = User::factory()->create();
        $service = Service::factory()->create();

        $start = Carbon::now()->subWeek()->startOfWeek();
        $end = $start->copy()->endOfWeek();

        $shoot = Shoot::factory()->create([
            'photographer_id' => $photographer->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'scheduled_date' => $start->copy()->addDay(),
            'total_quote' => 200,
            'base_quote' => 180,
            'tax_amount' => 20,
        ]);

        Payment::create([
            'shoot_id' => $shoot->id,
            'amount' => 200,
            'currency' => 'USD',
            'square_payment_id' => (string) Str::uuid(),
            'square_order_id' => (string) Str::uuid(),
            'status' => Payment::STATUS_COMPLETED,
            'processed_at' => now(),
        ]);

        app(InvoiceService::class)->generateForPeriod($start, $end);
        $invoice = Invoice::first();

        Sanctum::actingAs($admin);

        $listResponse = $this->getJson('/api/admin/invoices');
        $listResponse->assertOk();
        $listResponse->assertJsonPath('data.0.id', $invoice->id);

        $markResponse = $this->patchJson("/api/admin/invoices/{$invoice->id}/mark-paid", [
            'paid_at' => now()->toISOString(),
            'amount_paid' => 200,
        ]);
        $markResponse->assertOk();
        $markResponse->assertJsonPath('data.is_paid', true);
        $this->assertTrue($invoice->fresh()->is_paid);
        $this->assertEquals(200.0, (float) $invoice->fresh()->amount_paid);
    }

    public function test_admin_can_download_invoice_csv(): void
    {
        $admin = User::factory()->admin()->create();
        $photographer = User::factory()->photographer()->create();
        $client = User::factory()->create();
        $service = Service::factory()->create();

        $start = Carbon::now()->subWeek()->startOfWeek();
        $end = $start->copy()->endOfWeek();

        $shoot = Shoot::factory()->create([
            'photographer_id' => $photographer->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'scheduled_date' => $start->copy()->addDay(),
            'total_quote' => 120,
            'base_quote' => 100,
            'tax_amount' => 20,
        ]);

        Payment::create([
            'shoot_id' => $shoot->id,
            'amount' => 120,
            'currency' => 'USD',
            'square_payment_id' => (string) Str::uuid(),
            'square_order_id' => (string) Str::uuid(),
            'status' => Payment::STATUS_COMPLETED,
            'processed_at' => now(),
        ]);

        app(InvoiceService::class)->generateForPeriod($start, $end);
        $invoice = Invoice::first();

        Sanctum::actingAs($admin);

        $response = $this->get('/api/admin/invoices/' . $invoice->id . '/download');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertNotNull($response->headers->get('content-disposition'));
    }
}
