<?php

namespace Solt\Bonus\Tests\Feature\LegalEntity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Solt\Bonus\Models\LegalEntity;

class GetTest extends TestCase
{

    use RefreshDatabase;

    /**
     * Get legal entity test.
     *
     * @return void
     */
    public function test_legal_entity_getting()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $legal_entity = LegalEntity::factory()->create();

        $this->getJson(route('api.bonus.legal-entity.get', $legal_entity->inn) )
            ->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'inn',
                'name'
            ]
        );
    }

    /**
     * Get legal entity list test.
     *
     * @return void
     */
    public function test_legal_entity_getting_list()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $legal_entity = LegalEntity::factory(10)->create();

        $this->getJson(route('api.bonus.legal-entity.list') )
            ->assertStatus(200)
            ->assertJson(fn (AssertableJson $json) =>
                $json->has(10)
                    ->first(fn ($json) =>
                        $json->has('id')
                            ->has('name')
                            ->has('inn')
                            ->etc()
                    )
            );
    }

    /**
     * Get legal entity list empty test.
     *
     * @return void
     */
    public function test_legal_entity_getting_list_empty()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );


        $this->getJson(route('api.bonus.legal-entity.list') )
            ->assertStatus(200)
            ->assertJson(fn (AssertableJson $json) =>
                $json->has(0)
            );
    }
}
