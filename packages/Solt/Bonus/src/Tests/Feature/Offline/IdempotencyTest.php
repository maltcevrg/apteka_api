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
 * Повторы запросов от касс, номера чеков в пределах аптеки, время документа.
 */
class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected Card $card;
    protected Pharmacy $pharmacyA;
    protected Pharmacy $pharmacyB;

    protected function setUp(): void
    {
        parent::setUp();
        Passport::actingAsClient(Client::factory()->create(), ['*']);
        $this->card = Card::factory()->create();
        $this->pharmacyA = Pharmacy::factory()->create(['number' => 1]);
        $this->pharmacyB = Pharmacy::factory()->create(['number' => 2]);
    }

    private function payload(Pharmacy $ph, string $amount, string $number = '000000000001', array $extra = []): array
    {
        return array_merge([
            'short_code'      => $this->card->short_code,
            'pharmacy_number' => $ph->number,
            'inn'             => $ph->legal_entity->inn,
            'document_date'   => '20.11.2026',
            'document_number' => $number,
            'document_type'   => 'Чек ККМ',
            'amount'          => $amount,
        ], $extra);
    }

    public function test_repeated_request_returns_same_operation_without_duplicate()
    {
        $p = $this->payload($this->pharmacyA, '100');
        $first = $this->postJson(route('api.bonus.increase.store'), $p)->assertStatus(201)->assertJson(['duplicate' => false, 'balance' => 100]);
        $id = $first->json('id');

        $this->postJson(route('api.bonus.increase.store'), $p)->assertStatus(201)
            ->assertJson(['id' => $id, 'duplicate' => true, 'amount' => 100, 'balance' => 100]);
        $this->postJson(route('api.bonus.increase.store'), $p)->assertStatus(201)->assertJson(['id' => $id, 'duplicate' => true]);

        $this->assertSame(1, CardOperation::count());
        $this->getJson(route('api.bonus.balance.get', $this->card->short_code))->assertJson(['balance' => 100]);
    }

    public function test_same_document_number_in_different_pharmacies_is_allowed()
    {
        $this->postJson(route('api.bonus.increase.store'), $this->payload($this->pharmacyA, '100', '000000001000'))->assertStatus(201)->assertJson(['duplicate' => false]);
        $this->postJson(route('api.bonus.increase.store'), $this->payload($this->pharmacyB, '50', '000000001000'))->assertStatus(201)->assertJson(['duplicate' => false]);
        $this->assertSame(2, CardOperation::count());
        $this->getJson(route('api.bonus.balance.get', $this->card->short_code))->assertJson(['balance' => 150]);
    }

    public function test_same_check_can_have_increase_and_decrease()
    {
        $this->postJson(route('api.bonus.increase.store'), $this->payload($this->pharmacyA, '10', '000000000007'))->assertStatus(201);
        $this->postJson(route('api.bonus.decrease.store'), $this->payload($this->pharmacyA, '-5', '000000000007'))->assertStatus(201)->assertJson(['duplicate' => false]);
        $this->assertSame(2, CardOperation::count());
    }

    public function test_one_document_with_many_cards_is_many_operations()
    {
        // документ массового начисления: один номер, много карт
        $cards = Card::factory(3)->create();
        foreach ($cards as $c) {
            $p = $this->payload($this->pharmacyA, '100', '000000000555');
            $p['short_code'] = $c->short_code;
            $this->postJson(route('api.bonus.increase.store'), $p)->assertStatus(201)->assertJson(['duplicate' => false, 'balance' => 100]);
        }
        $this->assertSame(3, CardOperation::count());

        // повтор той же карты в том же документе — дубль
        $p = $this->payload($this->pharmacyA, '100', '000000000555');
        $p['short_code'] = $cards[1]->short_code;
        $this->postJson(route('api.bonus.increase.store'), $p)->assertStatus(201)->assertJson(['duplicate' => true]);
        $this->assertSame(3, CardOperation::count());
        foreach ($cards as $c) {
            $this->getJson(route('api.bonus.balance.get', $c->short_code))->assertJson(['balance' => 100]);
        }
    }

    public function test_repeated_cancel_is_success_and_not_doubled()
    {
        $p = $this->payload($this->pharmacyA, '100');
        $this->postJson(route('api.bonus.increase.store'), $p)->assertStatus(201);
        $cancel = ['short_code' => $p['short_code'], 'pharmacy_number' => 1, 'document_date' => $p['document_date'], 'document_number' => $p['document_number'], 'document_type' => $p['document_type']];

        $this->postJson(route('api.bonus.increase.cancel'), $cancel)->assertStatus(204);
        $this->postJson(route('api.bonus.increase.cancel'), $cancel)->assertStatus(204);
        $this->assertSame(1, CardOperation::where('action', CardOperation::$cancel_action)->count());
        $this->getJson(route('api.bonus.balance.get', $this->card->short_code))->assertJson(['balance' => 0]);

        // отмена несуществующего документа — по-прежнему отказ
        $cancel['document_number'] = '000000000099';
        $this->postJson(route('api.bonus.increase.cancel'), $cancel)->assertStatus(422);
    }

    public function test_document_number_can_be_reused_after_cancel_in_same_pharmacy()
    {
        $p = $this->payload($this->pharmacyA, '100');
        $this->postJson(route('api.bonus.increase.store'), $p)->assertStatus(201);
        $this->postJson(route('api.bonus.increase.cancel'), ['short_code' => $p['short_code'], 'document_date' => $p['document_date'], 'document_number' => $p['document_number'], 'document_type' => $p['document_type']])->assertStatus(204);
        $this->postJson(route('api.bonus.increase.store'), $this->payload($this->pharmacyA, '30'))->assertStatus(201)->assertJson(['duplicate' => false, 'balance' => 30]);
    }

    public function test_document_time_orders_operations_within_a_day()
    {
        // онлайн-чек в 12:00: +50
        $this->postJson(route('api.bonus.increase.store'), $this->payload($this->pharmacyA, '50', '000000000001', ['document_time' => '12:00']))->assertStatus(201)->assertJson(['balance_after' => 50]);
        // офлайн-чек того же дня в 10:15 (время внутри даты): −20
        $this->postJson(route('api.bonus.decrease.store'), $this->payload($this->pharmacyA, '-20', '000000000002', ['offline' => 1, 'document_date' => '20.11.2026 10:15']))
            ->assertStatus(201)->assertJson(['balance_after' => -20, 'balance' => 30, 'document_time' => '10:15:00']);
        // офлайн без времени — в начало дня
        $this->postJson(route('api.bonus.increase.store'), $this->payload($this->pharmacyA, '5', '000000000003', ['offline' => 1]))
            ->assertStatus(201)->assertJson(['balance_after' => 5, 'balance' => 35, 'document_time' => null]);

        $rows = LedgerService::chronological(CardOperation::where('card_id', $this->card->id))->get();
        $this->assertSame(['000000000003', '000000000002', '000000000001'], $rows->pluck('document_number')->all());
        $this->assertSame([5.0, -15.0, 35.0], $rows->pluck('card_after')->all());
        $this->assertSame([], LedgerService::checkCard($this->card->id));

        // онлайн без времени получает текущее время сервера
        $this->postJson(route('api.bonus.increase.store'), $this->payload($this->pharmacyA, '1', '000000000004'))->assertStatus(201);
        $this->assertNotNull(CardOperation::where('document_number', '000000000004')->value('document_time'));
    }
}
