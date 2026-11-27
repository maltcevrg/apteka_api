<?php

namespace Solt\Bonus\Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Passport\Client;
use Solt\Bonus\Models\AdminLog;
use Solt\Bonus\Models\Card;
use Solt\Bonus\Models\CardOperation;
use Solt\Bonus\Models\LegalEntity;
use Solt\Bonus\Models\Pharmacy;
use Solt\Bonus\Services\LedgerService;
use Tests\TestCase;

/**
 * Админ-панель: карты, корректировки, блокировка/замена, журнал операций,
 * кассы (клиенты API), журнал действий, обзор.
 */
class CardsAndLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function admin(): User
    {
        return User::create(['login' => 'admin', 'name' => 'Админ', 'role' => User::ROLE_ADMIN, 'password' => 'Admin-123']);
    }

    private function editor(): User
    {
        return User::create(['login' => 'editor', 'name' => 'Редактор', 'role' => User::ROLE_EDITOR, 'password' => 'Editor-123']);
    }

    private function viewer(): User
    {
        return User::create(['login' => 'viewer', 'name' => 'Зритель', 'role' => User::ROLE_USER, 'password' => 'Viewer-123']);
    }

    /** @return array{Card, LegalEntity, Pharmacy} */
    private function cardWithBalance(float $amount = 100): array
    {
        $le = LegalEntity::create(['inn' => '7701000001', 'name' => 'ООО Аптека']);
        $ph = Pharmacy::factory()->create(['number' => 1, 'name' => 'Аптека 1', 'legal_entity_id' => $le->id]);
        $card = Card::factory()->create(['short_code' => 'TEST0001', 'legal_entity_id' => $le->id, 'status' => 0]);
        LedgerService::append($card, $le, $ph, CardOperation::$increase_action, $amount, [
            'document_date' => '2026-01-10', 'document_number' => 'DOC-A', 'document_type' => 'Чек',
        ]);
        return [$card, $le, $ph];
    }

    public function test_cards_search_and_card_page()
    {
        [$card] = $this->cardWithBalance(150);
        $this->actingAs($this->viewer());

        $this->get('/admin/cards')->assertOk()->assertSee('Карты');
        // одна найденная → редирект на карточку
        $this->get('/admin/cards?q=TEST0001')->assertRedirect('/admin/cards/' . $card->id);
        $this->get('/admin/cards/' . $card->id)->assertOk()
            ->assertSee('TEST0001')->assertSee('150,00')->assertSee('Начисление')
            ->assertDontSee('Корректировка баланса');
        $this->get('/admin/cards?q=нет-такой')->assertOk()->assertSee('Ничего не найдено');
    }

    public function test_editor_can_adjust_balance_including_negative()
    {
        [$card, $le] = $this->cardWithBalance(100);
        $this->actingAs($this->editor());

        $this->get('/admin/cards/' . $card->id)->assertOk()->assertSee('Корректировка баланса');

        $this->post("/admin/cards/{$card->id}/adjust", ['amount' => '-30', 'legal_entity_id' => $le->id, 'comment' => 'ошибка кассира'])
            ->assertSessionHas('success');
        $this->assertEqualsWithDelta(70, $card->fresh()->getBalance(), 0.001);

        $op = CardOperation::orderByDesc('id')->first();
        $this->assertSame('Корректировка', $op->document_type);
        $this->assertSame('ошибка кассира', $op->comment);
        $this->assertNotNull($op->user_id);
        $this->assertStringStartsWith('K', $op->document_number);

        // в минус можно
        $this->post("/admin/cards/{$card->id}/adjust", ['amount' => '-100', 'legal_entity_id' => $le->id, 'comment' => 'уход в минус'])
            ->assertSessionHas('success');
        $this->assertEqualsWithDelta(-30, $card->fresh()->getBalance(), 0.001);

        // без причины нельзя
        $this->post("/admin/cards/{$card->id}/adjust", ['amount' => '10', 'legal_entity_id' => $le->id, 'comment' => ''])
            ->assertSessionHasErrors('comment');

        $this->assertDatabaseHas('admin_logs', ['action' => 'card.adjust', 'login' => 'editor']);
        // цепочка остатков целая
        $this->assertSame([], LedgerService::checkCard($card->id));
    }

    public function test_viewer_cannot_adjust()
    {
        [$card, $le] = $this->cardWithBalance();
        $this->actingAs($this->viewer());
        $this->post("/admin/cards/{$card->id}/adjust", ['amount' => '10', 'legal_entity_id' => $le->id, 'comment' => 'попытка'])
            ->assertForbidden();
    }

    public function test_block_unblock_and_replace_card()
    {
        [$card, $le] = $this->cardWithBalance(80);
        $this->actingAs($this->editor());

        $this->post("/admin/cards/{$card->id}/block", ['reason' => 'утеряна'])->assertSessionHas('success');
        $this->assertNull(Card::findByShortCode('TEST0001'), 'заблокированную карту кассы не находят');
        // страница заблокированной карты открывается
        $this->get("/admin/cards/{$card->id}")->assertOk()->assertSee('заблокирована')->assertSee('Разблокировать');

        $this->post("/admin/cards/{$card->id}/unblock")->assertSessionHas('success');
        $this->assertNotNull(Card::findByShortCode('TEST0001'));

        // замена на несуществующую карту — ошибка
        $this->post("/admin/cards/{$card->id}/replace", ['new_short_code' => 'NOPE'])->assertSessionHasErrors();

        $new = Card::factory()->create(['short_code' => 'TEST0002', 'legal_entity_id' => $le->id, 'status' => 0]);
        $this->post("/admin/cards/{$card->id}/replace", ['new_short_code' => 'TEST0002'])
            ->assertRedirect('/admin/cards/' . $new->id);

        $this->assertEqualsWithDelta(80, $new->fresh()->getBalance(), 0.001);
        $old = Card::withTrashed()->find($card->id);
        $this->assertSame(2, (int) $old->status);
        $this->assertEqualsWithDelta(0, $old->getBalance(), 0.001);
        $this->assertNull(Card::findByShortCode('TEST0001'));
        $this->assertSame([], LedgerService::checkCard($card->id));
        $this->assertSame([], LedgerService::checkCard($new->id));
        $this->assertDatabaseHas('admin_logs', ['action' => 'card.replace']);
    }

    public function test_operations_journal_with_filters_and_check()
    {
        [$card, $le, $ph] = $this->cardWithBalance(100);
        LedgerService::append($card, $le, $ph, CardOperation::$decreser_action, -40, [
            'document_date' => '2026-02-01', 'document_number' => 'DOC-B', 'document_type' => 'Чек', 'offline' => true,
        ]);
        $this->actingAs($this->editor());

        $this->get('/admin/operations')->assertOk()->assertSee('Начисление')->assertSee('Списание');
        $this->get('/admin/operations?action=1')->assertOk()->assertSee('DOC-B')->assertDontSee('DOC-A');
        $this->get('/admin/operations?from=2026-02-01')->assertOk()->assertSee('DOC-B')->assertDontSee('DOC-A');
        $this->get('/admin/operations?document_number=DOC-A')->assertOk()->assertSee('DOC-A')->assertDontSee('DOC-B');
        $this->get('/admin/operations?offline=1')->assertOk()->assertSee('офлайн');
        $this->get('/admin/operations?short_code=TEST0001&pharmacy_id=' . $ph->id)->assertOk()->assertSee('TEST0001');

        $this->post('/admin/operations/check')->assertRedirect();
        $log = AdminLog::where('action', 'ledger.check')->first();
        $this->assertNotNull($log);
        $this->assertTrue($log->details['ok']);
        $this->get('/admin/operations')->assertOk()->assertSee('целая');
    }

    public function test_clients_crud_admin_only()
    {
        $this->actingAs($this->editor());
        $this->get('/admin/clients')->assertForbidden();

        $this->actingAs($this->admin());
        $this->get('/admin/clients')->assertOk();
        $this->post('/admin/clients', ['name' => 'Касса 1'])->assertRedirect('/admin/clients')->assertSessionHas('new_client');
        $this->post('/admin/clients', ['name' => 'Касса 2'])->assertRedirect();

        $this->get('/admin/clients')->assertOk()->assertSee('Касса 1')->assertSee('Касса 2');
        $clients = Client::orderBy('name')->get();
        $this->assertCount(2, $clients);
        $this->assertTrue($clients[0]->hasGrantType('client_credentials'));

        $this->post("/admin/clients/{$clients[0]->getKey()}/secret")->assertSessionHas('new_client');
        $this->post("/admin/clients/{$clients[0]->getKey()}/revoke")->assertSessionHas('success');
        $this->assertTrue($clients[0]->fresh()->revoked);
        // последнюю действующую отозвать нельзя
        $this->post("/admin/clients/{$clients[1]->getKey()}/revoke")->assertSessionHasErrors();
        $this->assertFalse($clients[1]->fresh()->revoked);

        $this->assertDatabaseHas('admin_logs', ['action' => 'client.create']);
    }

    public function test_admin_log_and_dashboard()
    {
        $le = LegalEntity::create(['inn' => '7701000001', 'name' => 'ООО Аптека']);
        $ph = Pharmacy::factory()->create(['number' => 1, 'name' => 'Аптека 1', 'legal_entity_id' => $le->id]);
        $this->actingAs($this->admin());

        $this->post("/admin/pharmacies/{$ph->id}", ['number' => 2, 'name' => 'Аптека 2'])->assertRedirect();
        $this->assertSame('Аптека 2', $ph->fresh()->name);
        $this->post("/admin/legal-entities/{$le->id}", ['name' => 'ООО Новое'])->assertRedirect();
        $this->assertSame('ООО Новое', $le->fresh()->name);

        $this->get('/admin/log')->assertOk()->assertSee('Аптека 2')->assertSee('ООО Новое');
        $this->get('/admin/log?action=pharmacy.update')->assertOk()->assertSee('Аптека 2')->assertDontSee('ООО Новое');
        $this->get('/admin')->assertOk()->assertSee('Состояние системы')->assertSee('Последние действия');

        $this->actingAs($this->editor());
        $this->get('/admin/log')->assertForbidden();
        $this->get('/admin')->assertOk()->assertDontSee('Последние действия');
    }

    public function test_idle_logout()
    {
        config(['bonus.admin_idle_minutes' => 30]);
        $this->actingAs($this->admin());
        $this->get('/admin')->assertOk();
        $this->travel(31)->minutes();
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_ledger_rebuild_fixes_broken_chain_admin_only()
    {
        [$card, $le, $ph] = $this->cardWithBalance(100);
        LedgerService::append($card, $le, $ph, CardOperation::$decreser_action, -30, [
            'document_date' => '2026-02-01', 'document_number' => 'DOC-B', 'document_type' => 'Чек',
        ]);
        // «портим» журнал: кто-то поправил сумму первой операции в базе
        $first = CardOperation::where('card_id', $card->id)->orderBy('id')->first();
        DB::table('card_operations')->where('id', $first->id)->update(['amount' => 50]);
        $this->assertNotSame([], LedgerService::checkCard($card->id));

        // editor не может
        $this->actingAs($this->editor());
        $this->get('/admin/cards/' . $card->id)->assertOk()->assertSee('Разрыв цепочки')->assertDontSee('Пересчитать остатки');
        $this->post("/admin/cards/{$card->id}/rebuild")->assertForbidden();

        $this->actingAs($this->admin());
        $this->get('/admin/cards/' . $card->id)->assertOk()->assertSee('Пересчитать остатки');
        $this->post("/admin/cards/{$card->id}/rebuild")->assertSessionHas('success');

        $this->assertSame([], LedgerService::checkCard($card->id));
        $this->assertEqualsWithDelta(20, $card->fresh()->getBalance(), 0.001);
        $log = AdminLog::where('action', 'ledger.rebuild')->first();
        $this->assertSame(2, $log->details['changed']);
        $this->assertEqualsWithDelta(70, $log->details['balance_before'], 0.001);

        // повторно — отказ, цепочка целая
        $this->post("/admin/cards/{$card->id}/rebuild")->assertSessionHasErrors();

        // консольная команда
        DB::table('card_operations')->where('id', $first->id)->update(['amount' => 60]);
        $this->artisan('bonus:ledger-rebuild', ['--all' => true, '--force' => true])->assertSuccessful();
        $this->assertSame([], LedgerService::checkCard($card->id));
        $this->assertEqualsWithDelta(30, $card->fresh()->getBalance(), 0.001);
    }

    public function test_profile_edit_and_negative_cards_page()
    {
        [$card, $le] = $this->cardWithBalance(10);
        $this->actingAs($this->editor());

        $this->post("/admin/cards/{$card->id}/profile", [
            'firstname' => 'Анна', 'lastname' => 'Денисова', 'name' => '', 'phone' => '+79139465878', 'email' => 'a@b.ru',
            'city' => 'Новосибирск', 'gender' => 1, 'birthdate' => '1990-05-01', 'newsletter_agree' => 1,
        ])->assertSessionHas('success');
        $card->refresh();
        $this->assertSame('Денисова', $card->lastname);
        $this->assertSame('+79139465878', $card->phone);
        $this->assertTrue((bool) $card->newsletter_agree);
        $this->assertDatabaseHas('admin_logs', ['action' => 'card.update']);

        $this->post("/admin/cards/{$card->id}/profile", ['firstname' => 'Анна', 'gender' => 1, 'phone' => 'abc'])->assertSessionHasErrors('phone');

        // в минус
        $this->get('/admin/cards-negative')->assertOk()->assertSee('нет');
        $this->post("/admin/cards/{$card->id}/adjust", ['amount' => '-40', 'legal_entity_id' => $le->id, 'comment' => 'тест минуса'])->assertSessionHas('success');
        $this->get('/admin/cards-negative')->assertOk()->assertSee('TEST0001')->assertSee('-30,00');
        $csv = $this->get('/admin/cards-negative?csv=1')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('TEST0001', $csv->getContent());
        $this->get('/admin')->assertOk()->assertSee('Карты в минусе');

        $this->actingAs($this->viewer());
        $this->post("/admin/cards/{$card->id}/profile", ['firstname' => 'X', 'gender' => 0])->assertForbidden();
    }
}
