<?php

namespace Solt\Bonus\Tests\Feature\Offline;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Solt\Bonus\Models\Card;
use Solt\Bonus\Models\CardOperation;
use Solt\Bonus\Models\Pharmacy;
use Solt\Bonus\Services\LedgerService;
use Tests\TestCase;

/**
 * Офлайн-операции: признак offline, частичное списание до нуля.
 */
class OfflineTest extends TestCase
{
    use RefreshDatabase;

    protected Card $card;
    protected Pharmacy $pharmacy;
    protected int $docCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Passport::actingAsClient(Client::factory()->create(), ['*']);
        $this->card = Card::factory()->create();
        $this->pharmacy = Pharmacy::factory()->create();
    }

    private function payload(string $amount, array $extra = []): array
    {
        $this->docCounter++;
        return array_merge([
            'short_code'      => $this->card->short_code,
            'pharmacy_number' => $this->pharmacy->number,
            'inn'             => $this->pharmacy->legal_entity->inn,
            'document_date'   => '04.07.2026',
            'document_number' => str_pad((string) $this->docCounter, 12, '0', STR_PAD_LEFT),
            'document_type'   => 'Чек ККМ',
            'amount'          => $amount,
        ], $extra);
    }

    public function test_offline_flag_is_stored_and_returned()
    {
        $this->postJson(route('api.bonus.increase.store'), $this->payload('100', ['offline' => true]))
            ->assertStatus(201)->assertJson(['offline' => true, 'amount' => 100, 'amount_requested' => null]);
        $this->postJson(route('api.bonus.increase.store'), $this->payload('10'))
            ->assertStatus(201)->assertJson(['offline' => false]);

        $this->assertSame([true, false], CardOperation::orderBy('id')->pluck('offline')->all());
    }

    public function test_decrease_over_balance_goes_negative()
    {
        $this->postJson(route('api.bonus.increase.store'), $this->payload('100'))->assertStatus(201);
        $this->postJson(route('api.bonus.decrease.store'), $this->payload('-150'))
            ->assertStatus(201)->assertJson(['amount' => -150, 'amount_requested' => null, 'balance_after' => -50]);
        $this->getJson(route('api.bonus.balance.get', $this->card->short_code))->assertJson(['balance' => -50]);
        $this->assertSame([], LedgerService::checkCard($this->card->id));
    }

    public function test_offline_decrease_over_balance_writes_full_amount()
    {
        $this->postJson(route('api.bonus.increase.store'), $this->payload('100'))->assertStatus(201);
        $this->postJson(route('api.bonus.decrease.store'), $this->payload('-60'))->assertStatus(201);

        $this->postJson(route('api.bonus.decrease.store'), $this->payload('-80', ['offline' => 1]))
            ->assertStatus(201)
            ->assertJson(['amount' => -80, 'amount_requested' => null, 'offline' => true, 'balance_after' => -40]);

        $this->getJson(route('api.bonus.balance.get', $this->card->short_code))->assertJson(['balance' => -40]);
        $this->assertSame([], LedgerService::checkCard($this->card->id));
    }

    public function test_cancel_of_spent_increase_goes_negative()
    {
        $inc = $this->payload('100');
        $this->postJson(route('api.bonus.increase.store'), $inc)->assertStatus(201);
        $this->postJson(route('api.bonus.decrease.store'), $this->payload('-70'))->assertStatus(201);

        $cancel = [
            'short_code'      => $inc['short_code'],
            'document_date'   => $inc['document_date'],
            'document_number' => $inc['document_number'],
            'document_type'   => $inc['document_type'],
        ];
        $this->postJson(route('api.bonus.increase.cancel'), $cancel)->assertStatus(204);

        $storno = CardOperation::where('action', CardOperation::$cancel_action)->first();
        $this->assertEquals(-100, $storno->amount);
        $this->assertNull($storno->amount_requested);
        $this->getJson(route('api.bonus.balance.get', $this->card->short_code))->assertJson(['balance' => -70]);
    }

    public function test_operation_on_deleted_card_is_rejected_not_crashed()
    {
        $this->postJson(route('api.bonus.increase.store'), $this->payload('100'))->assertStatus(201);
        $this->card->delete();
        $this->postJson(route('api.bonus.decrease.store'), $this->payload('-10'))
            ->assertStatus(202)->assertJson(['message' => 'Карта не найдена или не действует']);
    }
}
