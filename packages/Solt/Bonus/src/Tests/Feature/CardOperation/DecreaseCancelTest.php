<?php

namespace Solt\Bonus\Tests\Feature\CardOperation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Solt\Bonus\Models\BonusBalance;
use Solt\Bonus\Models\Card;
use Solt\Bonus\Models\CardOperation;
use Solt\Bonus\Models\LegalEntity;
use Solt\Bonus\Models\Pharmacy;

class DecreaseCancelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Cancel card operation test
     *
     * @return void
     */
    public function test_card_operation_decrease_operation_cancel()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $card_operation = CardOperation::factory()->create();
        $bonus_balance = BonusBalance::factory(5)->create(['card_id' => $card_operation->card->id]);

        $payload = [
            'short_code'        => $card_operation->card->short_code,
            'document_date'     => $card_operation->document_date,
            'document_number'   => $card_operation->document_number,
            'document_type'     => $card_operation->document_type
        ];

        $this->postJson(route('api.bonus.decrease.cancel'), $payload)
            ->assertStatus(204);
    }

    /**
     * Cancel card operation wrong short code test
     *
     * @return void
     */
    public function test_card_operation_decrease_operation_cancel_wrong_short_code()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $card_operation = CardOperation::factory()->create();
        $card_operation_1 = CardOperation::factory()->create();

        $payload = [
            'short_code'        => $card_operation_1->card->short_code,
            'document_date'     => $card_operation->document_date,
            'document_number'   => $card_operation->document_number,
            'document_type'     => $card_operation->document_type
        ];

        $this->postJson(route('api.bonus.decrease.cancel'), $payload)
            ->assertStatus(202);
    }

    /**
     * Check card balance after decrease cancel
     *
     * @return void
     */
    public function test_check_card_balance()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $card = Card::factory()->create();
        $pharmacy = Pharmacy::factory()->create();

        $payload = [
            'short_code'        => $card->short_code,
            'pharmacy_number'   => $pharmacy->number,
            'inn'               => $pharmacy->legal_entity->inn,
            'document_date'     => '04.07.2022',
            'document_number'   => '123456789012',
            'document_type'     => '12345678901234567890',
            'amount'            => '-0.10'
        ];

        $this->postJson(route('api.bonus.decrease.store'), $payload);

        $payload_cancel = [
            'short_code'        => $card->short_code,
            'document_date'     => $payload['document_date'],
            'document_number'   => $payload['document_number'],
            'document_type'     => $payload['document_type']
        ];

        $this->postJson(route('api.bonus.decrease.cancel'), $payload_cancel);

        $this->getJson( route('api.bonus.balance.get', $card->short_code ) )
            ->assertStatus(200)
            ->assertJson([
                'balance' => round(0, 2)
            ]);

    }

     /**
     * Check card balance after decrease cancel with bonus balance
     *
     * @return void
     */
    public function test_check_card_balance_with_bonus_balance()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $card = Card::factory()->create();
        $pharmacy = Pharmacy::factory()->create();

        $bonus_balance_item = BonusBalance::factory()->create(['card_id' => $card->id]);

        $start_sum = $bonus_balance_item['sum'];

        $payload = [
            'short_code'        => $card->short_code,
            'pharmacy_number'   => $pharmacy->number,
            'inn'               => $pharmacy->legal_entity->inn,
            'document_date'     => '04.07.2022',
            'document_number'   => '123456789012',
            'document_type'     => '12345678901234567890',
            'amount'            => '-0.10'
        ];

        $this->postJson(route('api.bonus.decrease.store'), $payload);

        $payload_cancel = [
            'short_code'        => $card->short_code,
            'document_date'     => $payload['document_date'],
            'document_number'   => $payload['document_number'],
            'document_type'     => $payload['document_type']
        ];

        $this->postJson(route('api.bonus.decrease.cancel'), $payload_cancel);

        $this->getJson( route('api.bonus.balance.get', $card->short_code ) )
            ->assertStatus(200)
            ->assertJson([
                'balance' => round(($start_sum), 2)
            ]);

    }
}
