<?php

namespace Solt\Bonus\Tests\Feature\Bonus;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Passport\Passport;
use Laravel\Passport\Client;

class BonusTest extends TestCase
{
    use RefreshDatabase;

    public function setUp() : void
    {
        parent::setUp();
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );
    }

    /**
     * Test check api
     *
     * @return void
     */
    public function test_check()
    {
        $response = $this
            ->getJson('/api/v1/check');

        $response
            ->assertStatus(200)
            ->assertJson([
                'message' => 'API работает!',
            ]);
    }

    /**
     * Creating a card without data must fail validation
     *
     * @return void
     */
    public function test_creating_bonus_card_without_data_is_rejected()
    {
        $response = $this
            ->postJson('/api/v1/bonus/card', []);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['short_code']);
    }
}
