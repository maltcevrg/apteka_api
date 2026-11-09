<?php

namespace Solt\Bonus\Tests\Feature\Card;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Solt\Bonus\Models\Card;

class GetTest extends TestCase
{

    use RefreshDatabase;

    /**
     * Get card test.
     *
     * @return void
     */
    public function test_card_getting()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $card = Card::factory()->create();

        $this->getJson(route('api.bonus.card.get', $card->short_code))
            ->assertStatus(200)
            ->assertJson([
                'id' => $card->id,
                'short_code' => $card->short_code,
                'firstname' => $card->firstname,
                'name' => $card->name,
                'lastname' => $card->lastname,
                'birthdate' => Carbon::parse($card->birthdate)->format('d.m.Y'),
                'email' => $card->email,
                'phone' => $card->phone,
                'city' => $card->city,
                'gender' => Card::$genders[$card->gender],
                'newsletter_agree' => (Bool)$card->newsletter_agree,
                'guid' => $card->guid,
                'status' => Card::$statuses[$card->status]
            ]);
    }


    /**
     * Get card wrong card test.
     *
     * @return void
     */
    public function test_card_getting_wrong_card()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $card = Card::factory()->create();

        $this->getJson(route('api.bonus.card.get', fake()->bothify('????#####')))
            ->assertStatus(202);
    }
}
