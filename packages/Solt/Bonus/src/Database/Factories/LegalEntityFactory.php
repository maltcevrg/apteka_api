<?php

namespace Solt\Bonus\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Solt\Bonus\Models\BonusBalance;
use Solt\Bonus\Models\LegalEntity;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory
 */
class LegalEntityFactory extends Factory{

    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = LegalEntity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'inn'   => fake()->numerify('##########'),
            'name'  => fake()->name()
        ];
    }
}
