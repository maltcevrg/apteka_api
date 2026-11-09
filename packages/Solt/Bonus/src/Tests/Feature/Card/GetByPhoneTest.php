<?php

namespace Solt\Bonus\Tests\Feature\Card;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Solt\Bonus\Models\Card;

class GetByPhoneTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Getting card by phone test.
     *
     * @return void
     */
    public function test_get_card()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $card = Card::factory()->create();

        $payload = [
            'phone' => $card->phone
        ];

        $this->postJson( route('api.bonus.find', $payload) )
            ->assertStatus(200);
    }

    /**
     * Getting card by phone test wrong number.
     *
     * @return void
     */
    public function test_get_card_wrong_number()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $card = Card::factory()->create();

        $payload = [
            'phone' => '+79998887766'
        ];

        $this->postJson( route('api.bonus.find', $payload) )
            ->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    'phone'
                ]
            ]);
    }
}
