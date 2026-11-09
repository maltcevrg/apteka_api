<?php

namespace Solt\Bonus\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Solt\Bonus\Models\Card;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory
 */
class CardFactory extends Factory{

    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Card::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'short_code'        => fake()->bothify('????#####'),
            'firstname'         => fake()->firstName(),
            'name'              => fake()->name(),
            'birthdate'         => fake()->date(),
            'email'             => fake()->safeEmail(),
            'phone'             => fake()->numerify('+7##########'),
            'gender'            => '1',
            'newsletter_agree'  => '1',
            'status'            => Card::$avaliable_status
        ];
    }
}
