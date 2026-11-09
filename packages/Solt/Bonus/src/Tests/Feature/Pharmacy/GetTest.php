<?php

namespace Solt\Bonus\Tests\Feature\Pharmacy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Solt\Bonus\Models\Pharmacy;

class GetTest extends TestCase
{

    use RefreshDatabase;

    /**
     * Get pharmacy test.
     *
     * @return void
     */
    public function test_pharmacy_getting()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $pharmacy = Pharmacy::factory()->create();

        $this->getJson(route('api.bonus.pharmacy.get', $pharmacy->number) )
            ->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'legal_entity',
                'number',
                'name'
            ]
        );
    }

    /**
     * Get pharmacy list test.
     *
     * @return void
     */
    public function test_pharmacy_getting_list()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $pharmacy = Pharmacy::factory(10)->create();

        $this->getJson(route('api.bonus.pharmacy.list') )
            ->assertStatus(200)
            ->assertJson(fn (AssertableJson $json) =>
                $json->has(10)
                    ->first(fn ($json) =>
                        $json->has('id')
                            ->has('name')
                            ->has('number')
                            ->has('legal_entity')
                            ->etc()
                    )
            );
    }

    /**
     * Get pharmacy list empty test.
     *
     * @return void
     */
    public function test_pharmacy_getting_list_empty()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );


        $this->getJson(route('api.bonus.pharmacy.list') )
            ->assertStatus(200)
            ->assertJson(fn (AssertableJson $json) =>
                $json->has(0)
            );
    }
}
