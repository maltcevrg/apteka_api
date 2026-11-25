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

    public function test_online_decrease_over_balance_is_rejected()
    {
        $this->postJson(route('api.bonus.increase.store'), $this->payload('100'))->assertStatus(201);
        $this->postJson(route('api.bonus.decrease.store'), $this->payload('-150'))->assertStatus(202);
        $this->getJson(route('api.bonus.balance.get', $this->card->short_code))->assertJson(['balance' => 100]);
    }

    public function test_offline_decrease_over_balance_writes_off_to_zero()
    {
        // 9:00 онлайн +100, 12:00 онлайн -60, 13:00 приходит офлайн -80 (пробит в 10:00)
        $this->postJson(route('api.bonus.increase.store'), $this->payload('100'))->assertStatus(201);
        $this->postJson(route('api.bonus.decrease.store'), $this->payload('-60'))->assertStatus(201);

        $this->postJson(route('api.bonus.decrease.store'), $this->payload('-80', ['offline' => 1]))
            ->assertStatus(201)
            ->assertJson(['amount' => -40, 'amount_requested' => -80, 'offline' => true, 'balance_after' => 0]);

        $this->getJson(route('api.bonus.balance.get', $this->card->short_code))->assertJson(['balance' => 0]);
        $this->assertSame([], LedgerService::checkCard($this->card->id));

        // при нулевом балансе офлайн-списание записывается нулевой строкой
        $this->postJson(route('api.bonus.decrease.store'), $this->payload('-5', ['offline' => true]))
            ->assertStatus(201)->assertJson(['amount' => 0, 'amount_requested' => -5, 'balance_after' => 0]);
    }

    public function test_offline_cancel_of_spent_increase_writes_off_to_zero()
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
        // онлайн — отказ (30 - 100 < 0)
        $this->postJson(route('api.bonus.increase.cancel'), $cancel)->assertStatus(202);
        // офлайн — сторно на 30, баланс 0
        $this->postJson(route('api.bonus.increase.cancel'), $cancel + ['offline' => true])->assertStatus(204);

        $storno = CardOperation::where('action', CardOperation::$cancel_action)->first();
        $this->assertEquals(-30, $storno->amount);
        $this->assertEquals(-100, $storno->amount_requested);
        $this->assertTrue($storno->offline);
        $this->getJson(route('api.bonus.balance.get', $this->card->short_code))->assertJson(['balance' => 0]);
    }
}
