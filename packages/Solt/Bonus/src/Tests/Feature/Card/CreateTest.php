<?php

namespace Solt\Bonus\Tests\Feature\Card;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

class CreateTest extends TestCase
{

    use RefreshDatabase;

    /**
     * Store card test.
     *
     * @return void
     */
    public function test_card_creating()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $payload = [
            'short_code'        => '1234567890123',
            'firstname'         => 'Test',
            'name'              => 'Test',
            'birthdate'         => '04.07.1997',
            'phone'             => '+79998887766',
            'gender'            => '1',
            'newsletter_agree'  => '1',
            'status'            => '1'
        ];

        $this->postJson(route('api.bonus.card.store'), $payload)
            ->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'short_code',
                'firstname',
                'name',
                'lastname',
                'birthdate',
                'email',
                'phone',
                'city',
                'gender',
                'newsletter_agree',
                'guid',
                'status'
            ]);
    }

    /**
     * Store card test wrong phone format.
     *
     * @return void
     */
    public function test_card_creating_wrong_phone_format()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $payload = [
            'short_code'        => '1234567890123',
            'firstname'         => 'Test',
            'name'              => 'Test',
            'birthdate'         => '04.07.1997',
            'phone'             => '79998887766',
            'gender'            => '1',
            'newsletter_agree'  => '1',
            'status'            => '1'
        ];

        $this->postJson(route('api.bonus.card.store'), $payload)
            ->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    'phone'
                ]
            ]);
    }

    /**
     * Store card test wrong phone format.
     *
     * @return void
     */
    public function test_card_creating_wrong_birthdate_format()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $payload = [
            'short_code'        => '1234567890123',
            'firstname'         => 'Test',
            'name'              => 'Test',
            'birthdate'         => '04.07.199',
            'phone'             => '+79998887766',
            'gender'            => '1',
            'newsletter_agree'  => '1',
            'status'            => '1'
        ];

        $this->postJson(route('api.bonus.card.store'), $payload)
            ->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    'birthdate'
                ]
            ]);
    }
}
