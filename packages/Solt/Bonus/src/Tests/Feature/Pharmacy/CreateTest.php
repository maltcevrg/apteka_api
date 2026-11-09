<?php

namespace Solt\Bonus\Tests\Feature\Pharmacy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Solt\Bonus\Models\LegalEntity;

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
            'legal_entity'      => LegalEntity::factory()->create()->id,
            'number'            => fake()->numberBetween(1,99),
            'name'              => fake()->name()
        ];

        $this->postJson(route('api.bonus.pharmacy.store'), $payload)
            ->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'legal_entity',
                'number',
                'name'
            ]
        );
    }
}
