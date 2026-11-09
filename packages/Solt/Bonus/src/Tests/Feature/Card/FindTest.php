<?php

namespace Solt\Bonus\Tests\Feature\Card;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Solt\Bonus\Models\Card;

class FindTest extends TestCase
{

    use RefreshDatabase;

    /**
     * Find card test.
     *
     * @return void
     */
    public function test_card_finding()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $card = Card::factory()->create();

        $payload = [
            'phone' => $card->phone
        ];

        $this->postJson(route('api.bonus.find'), $payload)
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
     * Find card test wrong phone.
     *
     * @return void
     */
    public function test_card_finding_wrong_phone()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $card = Card::factory()->create();

        $payload = [
            'phone' => fake()->numerify('+7##########')
        ];

        $this->postJson(route('api.bonus.find'), $payload)
            ->assertStatus(422);
    }

    /**
     * Find card test deleted card.
     *
     * @return void
     */
    public function test_card_finding_deleted_card()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $card = Card::factory()->create();

        $payload = [
            'phone' => $card->phone
        ];

        $this->deleteJson(route('api.bonus.card.delete', $card->short_code));

        $this->postJson(route('api.bonus.find'), $payload)
            ->assertStatus(202);
    }
}
