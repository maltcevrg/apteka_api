<?php

namespace Solt\Bonus\Tests\Feature\Card;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Solt\Bonus\Models\BonusBalance;
use Solt\Bonus\Models\Card;

class GetBalanceTest extends TestCase
{

    use RefreshDatabase;

    /**
     * Getting card balance test.
     *
     * @return void
     */
    public function test_get_card_balance()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $bonus_balance_item = BonusBalance::factory()->create();

        $this->getJson( route('api.bonus.balance.get', $bonus_balance_item->card->short_code) )
            ->assertStatus(200)
            ->assertJsonStructure([
                'balance'
            ]);
    }

    /**
     * Getting card balance test empty card.
     *
     * @return void
     */
    public function test_get_card_balance_empty_card()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $bonus_balance_item = BonusBalance::factory()->create();

        $this->getJson('/api/v1/bonus/balance/')
            ->assertStatus(404);
    }

    /**
     * Getting card balance test wrong card.
     *
     * @return void
     */
    public function test_get_card_balance_wrong_card()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $bonus_balance_item = BonusBalance::factory()->create();

        $this->getJson( route('api.bonus.balance.get', fake()->bothify('????#####')) )
            ->assertStatus(202);
    }

     /**
     * Getting card balances test.
     *
     * @return void
     */
    public function test_get_card_balances()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $card = Card::factory()->create();
        $bonus_balance_item_1 = BonusBalance::factory()->create(['card_id' => $card->id]);
        $bonus_balance_item_2 = BonusBalance::factory()->create(['card_id' => $card->id]);

        $this->getJson( route('api.bonus.balance.get', $card->short_code ) )
            ->assertStatus(200)
            ->assertJson([
                'balance' => round($bonus_balance_item_1['sum'] + $bonus_balance_item_2['sum'], 2)
            ]);
    }

    /**
     * Getting card balance test empty balance.
     *
     * @return void
     */
    public function test_get_card_balance_empty_balance()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $card = Card::factory()->create();

        $this->getJson( route('api.bonus.balance.get', $card->short_code ) )
            ->assertStatus(200)
            ->assertJson([
                'balance' => round(0, 2)
            ]);
    }
}
