<?php

namespace Solt\Bonus\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Solt\Bonus\Models\LegalEntity;
use Solt\Bonus\Models\Pharmacy;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory
 */
class PharmacyFactory extends Factory{

    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Pharmacy::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'legal_entity_id'   => LegalEntity::factory(),
            'number'            => fake()->numberBetween(1,99),
            'name'              => fake()->name()
        ];
    }
}
