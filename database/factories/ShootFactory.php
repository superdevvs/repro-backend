<?php

namespace Database\Factories;

use App\Models\Shoot;

/**
 * @extends Factory<Shoot>
 */
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShootFactory extends Factory
{
    protected $model = Shoot::class;

    public function definition(): array
    {
        $baseQuote = fake()->randomFloat(2, 100, 500);
        $tax = round($baseQuote * 0.07, 2);

        return [
            'client_id' => UserFactory::new(),
            'photographer_id' => UserFactory::new()->photographer(),
            'service_id' => ServiceFactory::new(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'zip' => fake()->postcode(),
            'scheduled_date' => fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'time' => fake()->time('H:i'),
            'base_quote' => $baseQuote,
            'tax_amount' => $tax,
            'total_quote' => $baseQuote + $tax,
            'payment_status' => 'paid',
            'payment_type' => 'card',
            'notes' => null,
            'status' => 'completed',
            'workflow_status' => Shoot::WORKFLOW_COMPLETED,
            'created_by' => fake()->userName(),
        ];
    }
}
