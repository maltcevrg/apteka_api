<?php

namespace Solt\Bonus\Tests\Feature\LegalEntity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Solt\Bonus\Database\Factories\LegalEntityFactory;
use Solt\Bonus\Models\LegalEntity;

class DeleteTest extends TestCase
{

    use RefreshDatabase;

    /**
     * Delete legal entity test.
     *
     * @return void
     */
    public function test_legal_entity_delete()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $legal_entity = LegalEntity::factory()->create();

        $this->deleteJson(route('api.bonus.legal-entity.delete', $legal_entity->inn) )
            ->assertStatus(204);
    }
}
