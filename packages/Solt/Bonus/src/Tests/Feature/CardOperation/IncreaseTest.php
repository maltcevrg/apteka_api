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

class IncreaseTest extends TestCase
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

        $payload = [
            'short_code'        => $card->short_code,
            'pharmacy_number'   => $pharmacy->number,
            'inn'               => $pharmacy->legal_entity->inn,
            'document_date'     => '04.07.2022',
            'document_number'   => '123456789012',
            'document_type'     => '12345678901234567890',
            'amount'            => '0.10'
        ];

        $this->postJson(route('api.bonus.increase.store'), $payload)
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
     * Store card operation negative amount test.
     *
     * @return void
     */
    public function test_card_operation_negative_amount()
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

        $this->postJson(route('api.bonus.increase.store'), $payload)
            ->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    'amount'
                ]
            ]);
    }

    /**
     * Store card operation wrong card test.
     *
     * @return void
     */
    public function test_card_operation_wrong_card()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $card = Card::factory()->create();
        $pharmacy = Pharmacy::factory()->create();

        $payload = [
            'short_code'        => fake()->bothify('????#####'),
            'pharmacy_number'   => $pharmacy->number,
            'inn'               => $pharmacy->legal_entity->inn,
            'document_date'     => '04.07.2022',
            'document_number'   => '123456789012',
            'document_type'     => '12345678901234567890',
            'amount'            => '0.10'
        ];

        $this->postJson(route('api.bonus.increase.store'), $payload)
            ->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    'short_code'
                ]
            ]);
    }

    /**
     * Store card operation wrong pharmacy number test.
     *
     * @return void
     */
    public function test_card_operation_wrong_pharmacy_number()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $card = Card::factory()->create();
        $pharmacy = Pharmacy::factory()->create();

        $payload = [
            'short_code'        => $card->short_code,
            'pharmacy_number'   => fake()->numberBetween(100,199),
            'inn'               => $pharmacy->legal_entity->inn,
            'document_date'     => '04.07.2022',
            'document_number'   => '123456789012',
            'document_type'     => '12345678901234567890',
            'amount'            => '0.10'
        ];

        $this->postJson(route('api.bonus.increase.store'), $payload)
            ->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    'pharmacy_number'
                ]
            ]);
    }

    /**
     * Store card operation wrong inn test.
     *
     * @return void
     */
    public function test_card_operation_wrong_inn()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $card = Card::factory()->create();
        $pharmacy = Pharmacy::factory()->create();
        $legal_entity = LegalEntity::factory()->create();

        $payload = [
            'short_code'        => $card->short_code,
            'pharmacy_number'   => $pharmacy->number,
            'inn'               => fake()->numerify('##########'),
            'document_date'     => '04.07.2022',
            'document_number'   => '123456789012',
            'document_type'     => '12345678901234567890',
            'amount'            => '0.10'
        ];

        $this->postJson(route('api.bonus.increase.store'), $payload)
            ->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    'inn'
                ]
            ]);
    }

    /**
     * Store card operation duplicate document number test.
     *
     * @return void
     */
    public function test_card_operation_duplicate_document_number()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $card = Card::factory()->create();
        $pharmacy = Pharmacy::factory()->create();

        $card_operation = CardOperation::factory()->create();

        $payload = [
            'short_code'        => $card->short_code,
            'pharmacy_number'   => $pharmacy->number,
            'inn'               => $pharmacy->legal_entity->inn,
            'document_date'     => '04.07.2022',
            'document_number'   => $card_operation->document_number,
            'document_type'     => '12345678901234567890',
            'amount'            => '0.10'
        ];

        $this->postJson(route('api.bonus.increase.store'), $payload)
            ->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    'document_number'
                ]
            ]);
    }

    /**
     * Check card balance after increase
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
            'amount'            => '0.10'
        ];

        $this->postJson(route('api.bonus.increase.store'), $payload);

        $this->getJson( route('api.bonus.balance.get', $card->short_code ) )
            ->assertStatus(200)
            ->assertJson([
                'balance' => round($payload['amount'], 2)
            ]);

    }

    /**
     * Check card balance after increase with bonus balance
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

        $payload = [
            'short_code'        => $card->short_code,
            'pharmacy_number'   => $pharmacy->number,
            'inn'               => $pharmacy->legal_entity->inn,
            'document_date'     => '04.07.2022',
            'document_number'   => '123456789012',
            'document_type'     => '12345678901234567890',
            'amount'            => '0.10'
        ];

        $this->postJson(route('api.bonus.increase.store'), $payload);

        $this->getJson( route('api.bonus.balance.get', $card->short_code ) )
            ->assertStatus(200)
            ->assertJson([
                'balance' => round(($bonus_balance_item['sum'] + $payload['amount']), 2)
            ]);

    }
}
