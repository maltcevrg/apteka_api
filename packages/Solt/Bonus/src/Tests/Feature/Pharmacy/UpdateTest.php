<?php

namespace Solt\Bonus\Tests\Feature\Pharmacy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Solt\Bonus\Models\LegalEntity;
use Solt\Bonus\Models\Pharmacy;

class UpdateTest extends TestCase
{

    use RefreshDatabase;

    /**
     * Update pharmacy test.
     *
     * @return void
     */
    public function test_pharmacy_updating()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $pharmacy = Pharmacy::factory()->create();

        $payload = [
            'number'=> fake()->numberBetween(1,99),
            'name'  => fake()->name()
        ];

        $this->putJson(route('api.bonus.pharmacy.update', $pharmacy->number), $payload )
            ->assertStatus(200)
            ->assertJson([
                'id' => $pharmacy->id,
                'number' => $payload['number'],
                'name' => $payload['name'],
                'legal_entity' => [
                    'id' => $pharmacy->legal_entity->id
                ]
            ]
        );
    }

    /**
     * Update pharmacy wrong legal entity test.
     *
     * @return void
     */
    public function test_pharmacy_updating_wrong_legal_entity()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $pharmacy = Pharmacy::factory()->create();

        $payload = [
            'legal_entity'  => fake()->numberBetween(1,99),
        ];

        $this->putJson(route('api.bonus.pharmacy.update.legal-entity', $pharmacy->number), $payload )
            ->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    'legal_entity'
                ]
            ]
        );
    }

    /**
     * Update pharmacy another legal entity test.
     *
     * @return void
     */
    public function test_pharmacy_updating_another_legal_entity()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $pharmacy = Pharmacy::factory()->create();

        $legal_entity = LegalEntity::factory()->create();

        $payload = [
            'legal_entity'      => $legal_entity->id,
        ];

        $this->putJson(route('api.bonus.pharmacy.update.legal-entity', $pharmacy->number), $payload )
            ->assertStatus(200)
            ->assertJson([
                'number' => $pharmacy->number,
                'name' => $pharmacy->name,
                'legal_entity' => [
                    'id' => $legal_entity->id
                ]
            ]
        );
    }

    /**
     * Update pharmacy same legal entity test.
     *
     * @return void
     */
    public function test_pharmacy_updating_same_legal_entity()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $pharmacy = Pharmacy::factory()->create();

        $legal_entity = LegalEntity::factory()->create();

        $payload = [
            'legal_entity'   => $pharmacy->legal_entity->id,
        ];

        $this->putJson(route('api.bonus.pharmacy.update.legal-entity', $pharmacy->number), $payload )
            ->assertStatus(202);
    }
}
