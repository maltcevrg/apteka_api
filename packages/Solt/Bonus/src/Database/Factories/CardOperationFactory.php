<?php

namespace Solt\Bonus\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Solt\Bonus\Models\Card;
use Solt\Bonus\Models\CardOperation;
use Solt\Bonus\Models\LegalEntity;
use Solt\Bonus\Models\Pharmacy;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory
 */
class CardOperationFactory extends Factory{

    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CardOperation::class;

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
            'pharmacy_id'       => Pharmacy::factory(),
            'action'            => fake()->numberBetween(0,1),
            'document_date'     => fake()->date(),
            'document_number'   => fake()->bothify(str_repeat('?', 12)),
            'document_type'     => fake()->bothify(str_repeat('?', 40)),
            'amount'            => fake()->randomFloat(2,-2,30)
        ];
    }
}
