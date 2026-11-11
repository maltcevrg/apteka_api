<?php

namespace Solt\Bonus\Tests\Feature\Card;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Solt\Bonus\Models\Card;

class UpdateTest extends TestCase
{

    use RefreshDatabase;

    /**
     * Update card test.
     *
     * @return void
     */
    public function test_card_updating()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $card = Card::factory()->create();


        $payload = [
            'short_code'        => $card->short_code,
            'firstname'         => $card->firstname,
            'name'              => $card->name,
            'birthdate'         => $card->birthdate,
            'phone'             => $card->phone,
            'gender'            => '0',
            'newsletter_agree'  => '0',
            'status'            => '0'
        ];

        $this->putJson(route('api.bonus.card.update', $card->short_code), $payload)
            ->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'short_code',
                'firstname',
                'name',
                'lastname'
            ]);
    }

    /**
     * Update card test empty data.
     *
     * @return void
     */
    public function test_card_updating_empty_data()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $payload = [

        ];

        $card = Card::factory()->create();

        $this->putJson(route('api.bonus.card.update', $card->short_code), $payload)
            ->assertStatus(422)
            ->assertJsonStructure([
                'errors'
            ]);
    }
}
