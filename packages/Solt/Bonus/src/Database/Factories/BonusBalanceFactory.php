<?php

namespace Solt\Bonus\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Solt\Bonus\Models\BonusBalance;
use Solt\Bonus\Models\Card;
use Solt\Bonus\Models\LegalEntity;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory
 */
class BonusBalanceFactory extends Factory{

    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = BonusBalance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'legal_entity_id'   => LegalEntity::factory(),
            'card_id'           => Card::factory(),
            'sum'               => fake()->randomFloat(2,0,20000)
        ];
    }
}
