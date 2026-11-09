<?php

namespace Solt\Bonus\Tests\Feature\Pharmacy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Solt\Bonus\Models\Pharmacy;

class DeleteTest extends TestCase
{

    use RefreshDatabase;

    /**
     * Delete pharmacy test.
     *
     * @return void
     */
    public function test_pharmacy_delete()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $pharmacy = Pharmacy::factory()->create();

        $this->deleteJson(route('api.bonus.pharmacy.delete', $pharmacy->number) )
            ->assertStatus(204);
    }
}
