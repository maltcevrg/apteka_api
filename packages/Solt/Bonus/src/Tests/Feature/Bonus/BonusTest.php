<?php

namespace Solt\Bonus\Tests\Feature\Bonus;

use Tests\TestCase;
use Laravel\Passport\Passport;
use Laravel\Passport\Client;

class Bonus extends TestCase
{

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
            ->getJson('/api/v1/bonus/check');

        $response
            ->assertStatus(200)
            ->assertJson([
                'message' => 'Check api success',
            ]);
    }

    /**
     * Test creating bonus card
     *
     * @return void
     */
    public function test_creating_bonus_card()
    {
        $response = $this
            ->postJson('/api/v1/bonus/create');

        $response
            ->assertStatus(201);
    }
}
