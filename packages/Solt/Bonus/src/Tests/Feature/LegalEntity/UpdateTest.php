<?php

namespace Solt\Bonus\Tests\Feature\LegalEntity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Solt\Bonus\Models\LegalEntity;

class UpdateTest extends TestCase
{

    use RefreshDatabase;

    /**
     * Update legal entity test.
     *
     * @return void
     */
    public function test_legal_entity_updating()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $legal_entity = LegalEntity::factory()->create();

        $payload = [
            'inn'               => fake()->numerify(str_repeat('#', 13)),
            'name'              => 'Test'
        ];

        $this->putJson(route('api.bonus.legal-entity.update', $legal_entity->inn), $payload )
            ->assertStatus(200)
            ->assertJson([
                'id' => $legal_entity->id,
                'inn' => $payload['inn'],
                'name' => $payload['name']
            ]
        );
    }
}
