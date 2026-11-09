<?php

namespace Solt\Bonus\Tests\Feature\Card;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Solt\Bonus\Models\Card;

class DeleteTest extends TestCase
{

    use RefreshDatabase;

    /**
     * Delete card test.
     *
     * @return void
     */
    public function test_card_deleting()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $card = Card::factory()->create();

        $this->deleteJson(route('api.bonus.card.delete', $card->short_code))
            ->assertStatus(204);
    }

    /**
     * Delete card test empty data.
     *
     * @return void
     */
    public function test_card_deleting_empty_element()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $this->deleteJson(route('api.bonus.card.delete', '1234'))
            ->assertStatus(202);
    }
}
