<?php

namespace Database\Factories;

use App\Models\Shoot;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShootFactory extends Factory
{
    protected $model = Shoot::class;

    public function definition(): array
    {
        $baseQuote = $this->faker->randomFloat(2, 100, 1000);
        $taxAmount = $this->faker->randomFloat(2, 0, 150);
        $scheduled = $this->faker->dateTimeBetween('-1 month', '+1 month');

        return [
            'client_id' => User::factory(),
            'photographer_id' => User::factory(),
            'service_id' => Service::factory(),
            'service_category' => 'General',
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->stateAbbr(),
            'zip' => $this->faker->postcode(),
            'scheduled_date' => $scheduled,
            'time' => $this->faker->time('H:i'),
            'base_quote' => $baseQuote,
            'tax_amount' => $taxAmount,
            'total_quote' => $baseQuote + $taxAmount,
            'payment_status' => 'unpaid',
            'payment_type' => 'card',
            'notes' => null,
            'shoot_notes' => null,
            'company_notes' => null,
            'photographer_notes' => null,
            'editor_notes' => null,
            'status' => 'booked',
            'workflow_status' => Shoot::WORKFLOW_BOOKED,
            'created_by' => $this->faker->uuid(),
        ];
    }
}
