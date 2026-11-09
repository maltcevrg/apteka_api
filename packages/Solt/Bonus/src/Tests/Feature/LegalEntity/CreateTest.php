<?php

namespace Solt\Bonus\Tests\Feature\LegalEntity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

class CreateTest extends TestCase
{

    use RefreshDatabase;

    /**
     * Store pharmacy test.
     *
     * @return void
     */
    public function test_pharmacy_creating()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $payload = [
            'inn'               => fake()->numerify(str_repeat('#', 13)),
            'name'              => 'Test'
        ];

        $this->postJson(route('api.bonus.legal-entity.store'), $payload)
            ->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'inn',
                'name'
            ]
        );
    }
}
