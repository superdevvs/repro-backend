<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Shoot;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_generate_client_invoice_with_shoots_and_payments(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create(['role' => 'client']);
        $photographer = User::factory()->create(['role' => 'photographer']);

        $service = $this->createService();

        $shoot = $this->createShoot($client, $photographer, $service, Carbon::parse('2025-10-10'), 275.00);

        Payment::create([
            'shoot_id' => $shoot->id,
            'amount' => 100.00,
            'currency' => 'USD',
            'square_payment_id' => 'pay-client-100',
            'square_order_id' => 'order-1',
            'status' => Payment::STATUS_COMPLETED,
            'processed_at' => Carbon::parse('2025-10-11 09:00:00'),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/invoices/generate', [
            'user_id' => $client->id,
            'role' => Invoice::ROLE_CLIENT,
            'period_start' => '2025-10-01',
            'period_end' => '2025-10-31',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.role', Invoice::ROLE_CLIENT);
        $response->assertJsonPath('data.user_id', $client->id);
        $response->assertJsonPath('data.charges_total', '275.00');
        $response->assertJsonPath('data.payments_total', '100.00');
        $response->assertJsonPath('data.balance_due', '175.00');

        $invoiceId = $response->json('data.id');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'user_id' => $client->id,
            'role' => Invoice::ROLE_CLIENT,
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoiceId,
            'type' => InvoiceItem::TYPE_CHARGE,
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoiceId,
            'type' => InvoiceItem::TYPE_PAYMENT,
        ]);
    }

    public function test_admin_can_list_and_view_invoices(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create(['role' => 'client']);
        $photographer = User::factory()->create(['role' => 'photographer']);
        $service = $this->createService();

        $shoot = $this->createShoot($client, $photographer, $service, Carbon::parse('2025-09-15'), 300.00);

        Payment::create([
            'shoot_id' => $shoot->id,
            'amount' => 150.00,
            'currency' => 'USD',
            'square_payment_id' => 'pay-client-150',
            'square_order_id' => 'order-2',
            'status' => Payment::STATUS_COMPLETED,
            'processed_at' => Carbon::parse('2025-09-16 14:00:00'),
        ]);

        /** @var InvoiceService $invoiceService */
        $invoiceService = app(InvoiceService::class);
        $invoice = $invoiceService->generateInvoice(
            $client,
            Invoice::ROLE_CLIENT,
            Carbon::parse('2025-09-01'),
            Carbon::parse('2025-09-30')
        );

        Sanctum::actingAs($admin);

        $index = $this->getJson('/api/admin/invoices?with_items=1');
        $index->assertOk();
        $index->assertJsonFragment(['id' => $invoice->id]);
        $index->assertJsonPath('data.0.items.0.type', InvoiceItem::TYPE_CHARGE);

        $show = $this->getJson('/api/admin/invoices/' . $invoice->id);
        $show->assertOk();
        $show->assertJsonPath('id', $invoice->id);
        $show->assertJsonPath('items.0.type', InvoiceItem::TYPE_CHARGE);
    }

    public function test_admin_can_send_and_mark_invoice_paid(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create(['role' => 'client']);

        $invoice = Invoice::create([
            'user_id' => $client->id,
            'role' => Invoice::ROLE_CLIENT,
            'period_start' => '2025-08-01',
            'period_end' => '2025-08-31',
            'charges_total' => 200,
            'payments_total' => 0,
            'balance_due' => 200,
            'status' => Invoice::STATUS_DRAFT,
        ]);

        $invoice->items()->create([
            'type' => InvoiceItem::TYPE_CHARGE,
            'description' => 'Manual charge',
            'quantity' => 1,
            'unit_amount' => 200,
            'total_amount' => 200,
        ]);

        $invoice->refreshTotals();

        Sanctum::actingAs($admin);

        $sendResponse = $this->postJson('/api/admin/invoices/' . $invoice->id . '/send');
        $sendResponse->assertOk();
        $sendResponse->assertJsonPath('data.status', Invoice::STATUS_SENT);
        $this->assertNotNull($sendResponse->json('data.sent_at'));

        $paidResponse = $this->postJson('/api/admin/invoices/' . $invoice->id . '/mark-paid');
        $paidResponse->assertOk();
        $paidResponse->assertJsonPath('data.status', Invoice::STATUS_PAID);
        $paidResponse->assertJsonPath('data.balance_due', '0.00');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => Invoice::STATUS_PAID,
        ]);
    }

    private function createService(): Service
    {
        $category = Category::create(['name' => 'Residential']);

        return Service::create([
            'name' => 'Standard Shoot',
            'description' => 'A standard residential package',
            'price' => 250.00,
            'delivery_time' => 48,
            'category_id' => $category->id,
        ]);
    }

    private function createShoot(User $client, User $photographer, Service $service, Carbon $date, float $totalQuote): Shoot
    {
        return Shoot::create([
            'client_id' => $client->id,
            'photographer_id' => $photographer->id,
            'service_id' => $service->id,
            'service_category' => 'Residential',
            'address' => '123 Main St',
            'city' => 'Sample City',
            'state' => 'CA',
            'zip' => '90001',
            'scheduled_date' => $date,
            'time' => '10:00',
            'base_quote' => $totalQuote - 25,
            'tax_amount' => 25,
            'total_quote' => $totalQuote,
            'payment_status' => 'partial',
            'payment_type' => 'card',
            'status' => 'completed',
            'workflow_status' => Shoot::WORKFLOW_COMPLETED,
            'created_by' => 'system',
        ]);
    }
}
