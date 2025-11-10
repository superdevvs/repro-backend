<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Shoot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'shoot_id' => Shoot::factory(),
            'invoice_id' => Invoice::factory(),
            'amount' => $this->faker->randomFloat(2, 50, 1000),
            'currency' => 'USD',
            'square_payment_id' => 'pay_' . Str::uuid()->toString(),
            'square_order_id' => null,
            'status' => Payment::STATUS_COMPLETED,
            'processed_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
