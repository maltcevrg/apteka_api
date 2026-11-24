<?php

namespace Solt\Bonus\Tests\Feature\Snapshot;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Solt\Bonus\Models\BalanceSnapshot;
use Solt\Bonus\Models\BonusBalance;
use Solt\Bonus\Models\Card;
use Solt\Bonus\Models\CardOperation;
use Solt\Bonus\Models\LegalEntity;
use Solt\Bonus\Models\Pharmacy;
use Solt\Bonus\Services\SnapshotService;
use Tests\TestCase;

class CloseMonthTest extends TestCase
{
    use RefreshDatabase;

    protected Card $card;
    protected LegalEntity $le;
    protected Pharmacy $pharmacy;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-24 12:00:00');
        $this->le = LegalEntity::factory()->create();
        $this->pharmacy = Pharmacy::factory()->create(['legal_entity_id' => $this->le->id]);
        $this->card = Card::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Операция + синхронный остаток, как это делает CardOperationService */
    protected function op(int $action, float $amount, string $date, ?Card $card = null, ?LegalEntity $le = null): CardOperation
    {
        $card = $card ?? $this->card;
        $le = $le ?? $this->le;
        $op = CardOperation::factory()->create([
            'card_id' => $card->id, 'legal_entity_id' => $le->id, 'pharmacy_id' => $this->pharmacy->id,
            'action' => $action, 'amount' => $amount, 'document_date' => $date,
        ]);
        $balance = BonusBalance::withTrashed()->firstOrCreate(
            ['card_id' => $card->id, 'legal_entity_id' => $le->id], ['sum' => 0]
        );
        $balance->sum = $balance->sum + $amount;
        $balance->save();
        return $op;
    }

    /** Отмена операции в заданный момент времени */
    protected function cancel(CardOperation $op, string $at): void
    {
        $balance = BonusBalance::where('card_id', $op->card_id)->where('legal_entity_id', $op->legal_entity_id)->first();
        $balance->sum = $balance->sum - $op->amount;
        $balance->save();
        CardOperation::withTrashed()->where('id', $op->id)->update(['deleted_at' => $at]);
    }

    public function test_first_close_builds_from_whole_history()
    {
        $this->op(0, 100, '2026-09-10');   // сентябрь: начислено 100
        $this->op(1, -30, '2026-10-05');   // октябрь: списано 30

        $result = app(SnapshotService::class)->closeMonth('2026-10');

        $this->assertSame('2026-10', $result['period']);
        $this->assertSame([], $result['mismatch']);

        $s = BalanceSnapshot::where('period', '2026-10-01')->first();
        $this->assertEquals(0, $s->opening);          // первого среза не было
        $this->assertEquals(100, $s->increased);      // вся история до конца октября
        $this->assertEquals(30, $s->decreased);
        $this->assertEquals(70, $s->closing);
        $this->assertEquals(70, $this->card->fresh()->getBalance());
    }

    public function test_next_month_starts_from_previous_closing()
    {
        $this->op(0, 100, '2026-09-10');
        app(SnapshotService::class)->closeMonth('2026-09');

        $this->op(1, -40, '2026-10-15');
        $this->op(4, 10, '2026-10-20');    // возврат списания
        $this->op(3, -5, '2026-10-21');    // возврат начисления
        $result = app(SnapshotService::class)->closeMonth('2026-10');

        $s = BalanceSnapshot::where('period', '2026-10-01')->first();
        $this->assertEquals(100, $s->opening);
        $this->assertEquals(0, $s->increased);
        $this->assertEquals(40, $s->decreased);
        $this->assertEquals(10, $s->returned_decrease);
        $this->assertEquals(5, $s->returned_increase);
        $this->assertEquals(65, $s->closing);
        $this->assertSame([], $result['mismatch']);
    }

    public function test_cancellation_of_closed_month_goes_to_current_month()
    {
        $op = $this->op(0, 100, '2026-09-10');
        app(SnapshotService::class)->closeMonth('2026-09');
        $this->assertEquals(100, BalanceSnapshot::where('period', '2026-09-01')->first()->closing);

        // В октябре отменили сентябрьское начисление
        $this->cancel($op, '2026-10-03 10:00:00');
        app(SnapshotService::class)->closeMonth('2026-10');

        // Сентябрь не изменился
        $this->assertEquals(100, BalanceSnapshot::where('period', '2026-09-01')->first()->closing);
        // Октябрь показывает отмену
        $s = BalanceSnapshot::where('period', '2026-10-01')->first();
        $this->assertEquals(100, $s->opening);
        $this->assertEquals(100, $s->cancelled_increase);
        $this->assertEquals(0, $s->closing);
    }

    public function test_backdated_operation_after_close_goes_to_next_open_month()
    {
        $this->op(0, 50, '2026-09-10');
        app(SnapshotService::class)->closeMonth('2026-09');

        // Чек с сентябрьской датой пришёл после закрытия сентября
        $this->op(0, 20, '2026-09-28');
        app(SnapshotService::class)->closeMonth('2026-10');

        $this->assertEquals(50, BalanceSnapshot::where('period', '2026-09-01')->first()->closing);
        $s = BalanceSnapshot::where('period', '2026-10-01')->first();
        $this->assertEquals(20, $s->increased);
        $this->assertEquals(70, $s->closing);
    }

    public function test_months_must_be_closed_in_order()
    {
        $this->op(0, 10, '2026-09-10');
        app(SnapshotService::class)->closeMonth('2026-09');

        $this->expectException(\RuntimeException::class);
        app(SnapshotService::class)->closeMonth('2026-09');  // повторно нельзя
    }

    public function test_cannot_close_unfinished_month()
    {
        $this->expectException(\InvalidArgumentException::class);
        app(SnapshotService::class)->closeMonth('2026-11');  // сегодня 24.11
    }

    public function test_snapshot_per_legal_entity()
    {
        $le2 = LegalEntity::factory()->create();
        $this->op(0, 100, '2026-10-01');                 // юрлицо 1 начислило
        $this->op(1, -30, '2026-10-02', null, $le2);     // юрлицо 2 списало
        app(SnapshotService::class)->closeMonth('2026-10');

        $rows = BalanceSnapshot::where('period', '2026-10-01')->where('card_id', $this->card->id)->get()->keyBy('legal_entity_id');
        $this->assertEquals(100, $rows[$this->le->id]->closing);
        $this->assertEquals(-30, $rows[$le2->id]->closing);
        $this->assertEquals(70, $rows->sum('closing'));
    }

    public function test_recompute_requires_admin_key()
    {
        config(['bonus.admin_key' => 'secret-123']);
        $this->op(0, 10, '2026-10-10');
        app(SnapshotService::class)->closeMonth('2026-10');

        try {
            app(SnapshotService::class)->recomputeLast('wrong');
            $this->fail('Пересчёт с неверным ключом должен быть отклонён');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('ключ', $e->getMessage());
        }

        $result = app(SnapshotService::class)->recomputeLast('secret-123');
        $this->assertSame('2026-10', $result['period']);
        $this->assertEquals(1, BalanceSnapshot::where('period', '2026-10-01')->count());
    }

    public function test_artisan_command()
    {
        $this->op(0, 10, '2026-10-10');
        $this->artisan('bonus:close-month', ['period' => '2026-10'])
            ->expectsOutputToContain('Месяц 2026-10 закрыт')
            ->assertExitCode(0);
        $this->artisan('bonus:close-month', ['period' => '2026-10'])
            ->assertExitCode(1);
    }

    public function test_command_default_period_is_previous_month()
    {
        $this->op(0, 10, '2026-10-10');
        $this->artisan('bonus:close-month')
            ->expectsOutputToContain('Месяц 2026-10 закрыт')
            ->assertExitCode(0);
    }

    public function test_move_writes_operations_per_legal_entity_and_snapshot_matches()
    {
        $le2 = LegalEntity::factory()->create();
        $cardTo = Card::factory()->create();
        $this->op(0, 100, '2026-10-01');
        $this->op(0, 50, '2026-10-02', null, $le2);

        $ok = app(\Solt\Bonus\Services\CardOperationService::class)
            ->move($this->card->short_code, $cardTo->short_code, 'MV-1', '2026-10-05 00:00:00', 'move');
        $this->assertTrue($ok);
        $this->assertEquals(0, $this->card->fresh()->getBalance());
        $this->assertEquals(150, $cardTo->fresh()->getBalance());
        // по две операции на каждое юрлицо
        $this->assertEquals(4, CardOperation::where('action', CardOperation::$move_action)->count());
        $this->assertEquals(0, CardOperation::where('action', CardOperation::$move_action)->whereNull('legal_entity_id')->count());

        $result = app(SnapshotService::class)->closeMonth('2026-10');
        $this->assertSame([], $result['mismatch']);
        $to = BalanceSnapshot::where('period', '2026-10-01')->where('card_id', $cardTo->id)->get()->keyBy('legal_entity_id');
        $this->assertEquals(100, $to[$this->le->id]->closing);
        $this->assertEquals(50, $to[$le2->id]->closing);
    }
}
