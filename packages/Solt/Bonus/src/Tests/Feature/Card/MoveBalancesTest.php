<?php

namespace Solt\Bonus\Tests\Feature\Card;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Solt\Bonus\Models\BonusBalance;
use Solt\Bonus\Models\Card;

class MoveBalancesTest extends TestCase
{

    use RefreshDatabase;

    /**
     * Move balances test.
     *
     * @return void
     */
    public function test_move_balances()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $card_1 = Card::factory()->create();
        $bonus_balance_1 = BonusBalance::factory(5)->create(['card_id' => $card_1->id]);

        $card_2 = Card::factory()->create();
        $bonus_balance_2 = BonusBalance::factory(5)->create(['card_id' => $card_2->id]);

        $payload = [
            'short_code_from'   => $card_1->short_code,
            'short_code_to'     => $card_2->short_code,
            'document_date'     => '04.07.2022',
            'document_number'   => '123456789012',
            'document_type'     => '12345678901234567890'
        ];

        $this->postJson(route('api.bonus.move'), $payload)
            ->assertStatus(200);
    }

    /**
     * Move balances check balance test.
     *
     * @return void
     */
    public function test_move_balances_check_balance()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $card_1 = Card::factory()->create();
        $bonus_balance_1 = BonusBalance::factory(5)->create(['card_id' => $card_1->id]);

        $card_2 = Card::factory()->create();
        $bonus_balance_2 = BonusBalance::factory(5)->create(['card_id' => $card_2->id]);

        $result_balance = $card_1->getBalance() + $card_2->getBalance();

        $payload = [
            'short_code_from'   => $card_1->short_code,
            'short_code_to'     => $card_2->short_code,
            'document_date'     => '04.07.2022',
            'document_number'   => '123456789012',
            'document_type'     => '12345678901234567890'
        ];

        $this->postJson(route('api.bonus.move'), $payload);

        $this->getJson( route('api.bonus.balance.get', $card_2->short_code ) )
            ->assertStatus(200)
            ->assertJson([
                'balance' => round($result_balance, 2)
            ]);
    }

    /**
     * Move balances check balance empty card from test.
     *
     * @return void
     */
    public function test_move_balances_check_balance_empty_card_from()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $card_1 = Card::factory()->create();
        $bonus_balance_1 = BonusBalance::factory(5)->create(['card_id' => $card_1->id]);

        $card_2 = Card::factory()->create();
        $bonus_balance_2 = BonusBalance::factory(5)->create(['card_id' => $card_2->id]);

        $payload = [
            'short_code_from'   => $card_1->short_code,
            'short_code_to'     => $card_2->short_code,
            'document_date'     => '04.07.2022',
            'document_number'   => '123456789012',
            'document_type'     => '12345678901234567890'
        ];

        $this->postJson(route('api.bonus.move'), $payload);

        $this->getJson( route('api.bonus.balance.get', $card_1->short_code ) )
            ->assertStatus(200)
            ->assertJson([
                'balance' => round(0, 2)
            ]);
    }

    /**
     * Move balances check balance empty card to test.
     *
     * @return void
     */
    public function test_move_balances_check_balance_empty_card_to()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $card_1 = Card::factory()->create();
        $bonus_balance_1 = BonusBalance::factory(5)->create(['card_id' => $card_1->id]);

        $card_2 = Card::factory()->create();

        $result_balance = $card_1->getBalance() + $card_2->getBalance();

        $payload = [
            'short_code_from'   => $card_1->short_code,
            'short_code_to'     => $card_2->short_code,
            'document_date'     => '04.07.2022',
            'document_number'   => '123456789012',
            'document_type'     => '12345678901234567890'
        ];

        $this->postJson(route('api.bonus.move'), $payload);

        $this->getJson( route('api.bonus.balance.get', $card_2->short_code ) )
            ->assertStatus(200)
            ->assertJson([
                'balance' => round($result_balance, 2)
            ]);
    }
}
