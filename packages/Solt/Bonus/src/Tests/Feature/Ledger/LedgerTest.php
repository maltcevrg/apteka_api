<?php

namespace Solt\Bonus\Tests\Feature\Ledger;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Solt\Bonus\Models\Card;
use Solt\Bonus\Models\CardOperation;
use Solt\Bonus\Models\LegalEntity;
use Solt\Bonus\Models\Pharmacy;
use Solt\Bonus\Services\LedgerService;
use Tests\TestCase;

/**
 * Журнал с нарастающим итогом: остатки в строках, сторно, перенос по кошелькам.
 */
class LedgerTest extends TestCase
{
    use RefreshDatabase;

    protected Card $card;
    protected Pharmacy $pharmacyA;
    protected Pharmacy $pharmacyB;
    protected int $docCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Passport::actingAsClient(Client::factory()->create(), ['*']);
        $this->card = Card::factory()->create();
        $this->pharmacyA = Pharmacy::factory()->create();
        $this->pharmacyB = Pharmacy::factory()->create();
    }

    private function payload(Pharmacy $pharmacy, string $amount, ?string $number = null): array
    {
        $this->docCounter++;
        return [
            'short_code'      => $this->card->short_code,
            'pharmacy_number' => $pharmacy->number,
            'inn'             => $pharmacy->legal_entity->inn,
            'document_date'   => '04.07.2026',
            'document_number' => $number ?? str_pad((string) $this->docCounter, 12, '0', STR_PAD_LEFT),
            'document_type'   => 'Чек ККМ',
            'amount'          => $amount,
        ];
    }

    private function cancelPayload(array $payload): array
    {
        return [
            'short_code'      => $payload['short_code'],
            'document_date'   => $payload['document_date'],
            'document_number' => $payload['document_number'],
            'document_type'   => $payload['document_type'],
        ];
    }

    public function test_balance_after_in_response_and_in_rows()
    {
        $r1 = $this->postJson(route('api.bonus.increase.store'), $this->payload($this->pharmacyA, '100'))
            ->assertStatus(201)->assertJson(['balance_after' => 100]);
        $this->postJson(route('api.bonus.decrease.store'), $this->payload($this->pharmacyA, '-30'))
            ->assertStatus(201)->assertJson(['balance_after' => 70]);
        $this->postJson(route('api.bonus.increase.store'), $this->payload($this->pharmacyB, '50'))
            ->assertStatus(201)->assertJson(['balance_after' => 120]);

        $rows = CardOperation::where('card_id', $this->card->id)->orderBy('id')->get();
        $this->assertEquals([100, 70, 120], $rows->pluck('card_after')->map(fn ($v) => (float) $v)->all());
        $this->assertEquals([100, 70, 50], $rows->pluck('wallet_after')->map(fn ($v) => (float) $v)->all());

        $this->getJson(route('api.bonus.balance.get', $this->card->short_code))->assertJson(['balance' => 120]);
        $this->assertSame([], LedgerService::checkCard($this->card->id));
    }

    public function test_amounts_are_rounded_to_kopecks()
    {
        $this->postJson(route('api.bonus.increase.store'), $this->payload($this->pharmacyA, '10.005'))
            ->assertStatus(201)->assertJson(['amount' => 10.01, 'balance_after' => 10.01]);
        $this->postJson(route('api.bonus.increase.store'), $this->payload($this->pharmacyA, '0.123'))
            ->assertStatus(201)->assertJson(['amount' => 0.12, 'balance_after' => 10.13]);

        $raw = DB::table('card_operations')->where('card_id', $this->card->id)->orderBy('id')->get();
        $this->assertSame(['10.01', '0.12'], $raw->pluck('amount')->map(fn ($v) => (string) $v)->all());
        $this->assertSame(['10.01', '10.13'], $raw->pluck('card_after')->map(fn ($v) => (string) $v)->all());

        $this->getJson(route('api.bonus.balance.get', $this->card->short_code))->assertJson(['balance' => 10.13]);
    }

    public function test_card_legal_entity_is_set_by_first_operation()
    {
        $this->postJson(route('api.bonus.increase.store'), $this->payload($this->pharmacyB, '10'))->assertStatus(201);
        $this->postJson(route('api.bonus.increase.store'), $this->payload($this->pharmacyA, '10'))->assertStatus(201);

        $this->assertEquals($this->pharmacyB->legal_entity_id, $this->card->fresh()->legal_entity_id);
    }

    public function test_cancel_creates_storno_row_and_marks_original()
    {
        $p = $this->payload($this->pharmacyA, '100');
        $this->postJson(route('api.bonus.increase.store'), $p)->assertStatus(201);
        $this->postJson(route('api.bonus.increase.store'), $this->payload($this->pharmacyA, '20'))->assertStatus(201);

        $this->postJson(route('api.bonus.increase.cancel'), $this->cancelPayload($p))->assertStatus(204);

        $rows = CardOperation::where('card_id', $this->card->id)->orderBy('id')->get();
        $this->assertCount(3, $rows);

        $original = $rows[0];
        $storno   = $rows[2];
        $this->assertNotNull($original->deleted_at);
        $this->assertEquals(CardOperation::$cancel_action, $storno->action);
        $this->assertEquals($original->id, $storno->parent_id);
        $this->assertEquals(-100, (float) $storno->amount);
        $this->assertEquals($original->document_number, $storno->document_number);
        $this->assertEquals(now()->format('Y-m-d'), substr((string) $storno->document_date, 0, 10));
        $this->assertEquals(20, (float) $storno->card_after);
        $this->assertNull($storno->deleted_at);

        $this->getJson(route('api.bonus.balance.get', $this->card->short_code))->assertJson(['balance' => 20]);
        $this->assertSame([], LedgerService::checkCard($this->card->id));
    }

    public function test_cancel_twice_is_rejected()
    {
        $p = $this->payload($this->pharmacyA, '100');
        $this->postJson(route('api.bonus.increase.store'), $p)->assertStatus(201);

        $this->postJson(route('api.bonus.increase.cancel'), $this->cancelPayload($p))->assertStatus(204);
        $this->postJson(route('api.bonus.increase.cancel'), $this->cancelPayload($p))->assertStatus(202);

        $this->assertCount(2, CardOperation::where('card_id', $this->card->id)->get());
        $this->getJson(route('api.bonus.balance.get', $this->card->short_code))->assertJson(['balance' => 0]);
    }

    public function test_cancel_increase_with_insufficient_balance_goes_negative()
    {
        $p = $this->payload($this->pharmacyA, '100');
        $this->postJson(route('api.bonus.increase.store'), $p)->assertStatus(201);
        $this->postJson(route('api.bonus.decrease.store'), $this->payload($this->pharmacyA, '-80'))->assertStatus(201);

        // Отмена начисления на 100 уводит карту в минус (20 − 100 = −80) — допустимо
        $this->postJson(route('api.bonus.increase.cancel'), $this->cancelPayload($p))->assertStatus(204);
        $this->assertNotNull(CardOperation::where('card_id', $this->card->id)->orderBy('id')->first()->deleted_at);
        $this->getJson(route('api.bonus.balance.get', $this->card->short_code))->assertJson(['balance' => -80]);
        $this->assertSame([], LedgerService::checkCard($this->card->id));
    }

    public function test_document_number_can_be_reused_after_cancel()
    {
        $p = $this->payload($this->pharmacyA, '100', '777777777777');
        $this->postJson(route('api.bonus.increase.store'), $p)->assertStatus(201);
        $this->postJson(route('api.bonus.increase.cancel'), $this->cancelPayload($p))->assertStatus(204);

        $this->postJson(route('api.bonus.increase.store'), $this->payload($this->pharmacyA, '15', '777777777777'))
            ->assertStatus(201)->assertJson(['balance_after' => 15]);
    }

    public function test_move_transfers_each_wallet_with_running_totals()
    {
        $this->postJson(route('api.bonus.increase.store'), $this->payload($this->pharmacyA, '100'))->assertStatus(201);
        $this->postJson(route('api.bonus.increase.store'), $this->payload($this->pharmacyB, '40'))->assertStatus(201);

        $target = Card::factory()->create();
        $this->postJson(route('api.bonus.move'), [
            'short_code_from' => $this->card->short_code,
            'short_code_to'   => $target->short_code,
            'document_date'   => '05.07.2026',
            'document_number' => '999999999999',
            'document_type'   => 'Перенос',
        ])->assertStatus(200);

        $leA = $this->pharmacyA->legal_entity_id;
        $leB = $this->pharmacyB->legal_entity_id;

        $this->assertEquals(0, $this->card->fresh()->getBalance());
        $this->assertEquals(140, $target->fresh()->getBalance());
        $this->assertEquals([$leA => 100.0, $leB => 40.0], LedgerService::wallets($target));
        $this->assertEquals([$leA => 0.0, $leB => 0.0], LedgerService::wallets($this->card));

        $moves = CardOperation::where('action', CardOperation::$move_action)->get();
        $this->assertCount(4, $moves);
        $this->assertTrue($moves->every(fn ($op) => $op->legal_entity_id !== null));

        $this->assertSame([], LedgerService::checkCard($this->card->id));
        $this->assertSame([], LedgerService::checkCard($target->id));
    }

    public function test_backdated_document_does_not_break_chain()
    {
        $this->postJson(route('api.bonus.increase.store'), $this->payload($this->pharmacyA, '100'))->assertStatus(201);
        $old = $this->payload($this->pharmacyA, '5');
        $old['document_date'] = '01.01.2020';
        $this->postJson(route('api.bonus.increase.store'), $old)->assertStatus(201)->assertJson(['balance_after' => 105]);

        $this->assertSame([], LedgerService::checkCard($this->card->id));
    }

    public function test_empty_card_balance_is_zero()
    {
        $this->assertEquals(0, $this->card->getBalance());
        $this->assertSame([], LedgerService::wallets($this->card));
    }

    public function test_ledger_check_detects_broken_chain()
    {
        $this->postJson(route('api.bonus.increase.store'), $this->payload($this->pharmacyA, '100'))->assertStatus(201);
        $this->postJson(route('api.bonus.increase.store'), $this->payload($this->pharmacyA, '50'))->assertStatus(201);

        DB::table('card_operations')->where('card_id', $this->card->id)->orderByDesc('id')->limit(1)->update(['card_after' => 999]);

        $errors = LedgerService::checkCard($this->card->id);
        $this->assertCount(1, $errors);
        $this->assertEquals('card_after', $errors[0]['field']);
        $this->assertEquals(150, $errors[0]['expected']);

        $this->artisan('bonus:ledger-check', ['--card' => $this->card->id])->assertExitCode(1);
    }

    public function test_factory_rows_form_valid_chain()
    {
        $le = LegalEntity::factory()->create();
        CardOperation::factory(5)->credit()->create(['card_id' => $this->card->id, 'legal_entity_id' => $le->id]);

        $this->assertSame([], LedgerService::checkCard($this->card->id));
        $this->assertEquals(
            round((float) CardOperation::where('card_id', $this->card->id)->sum('amount'), 2),
            $this->card->getBalance()
        );
    }
}
