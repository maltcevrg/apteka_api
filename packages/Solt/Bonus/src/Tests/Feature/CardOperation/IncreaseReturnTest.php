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

class IncreaseReturnTest extends TestCase
{

    use RefreshDatabase;

    /**
     * Store card operation test.
     *
     * @return void
     */
    public function test_card_operation_creating()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $card = Card::factory()->create();
        $pharmacy = Pharmacy::factory()->create();

        $bonus_balance_item = BonusBalance::factory()->create(['card_id' => $card->id]);

        $payload = [
            'short_code'        => $card->short_code,
            'pharmacy_number'   => $pharmacy->number,
            'inn'               => $pharmacy->legal_entity->inn,
            'document_date'     => '04.07.2022',
            'document_number'   => '123456789012',
            'document_type'     => '12345678901234567890',
            'amount'            => '-0.10'
        ];

        $this->postJson(route('api.bonus.increase.return'), $payload)
            ->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'card',
                'legal_entity',
                'pharmacy',
                'action',
                'document_date',
                'document_number',
                'document_type',
                'amount'
            ]);
    }

    /**
     * Store card operation test empty balance.
     *
     * @return void
     */
    public function test_card_operation_creating_empty_balance()
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

        $this->postJson(route('api.bonus.increase.return'), $payload)
            ->assertStatus(202);
    }
}
