<?php

namespace Solt\Bonus\Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Solt\Bonus\Models\Card;
use Solt\Bonus\Models\CardOperation;
use Solt\Bonus\Models\LegalEntity;
use Solt\Bonus\Models\Pharmacy;
use Tests\TestCase;

/**
 * Админ-панель: вход по логину и паролю, смена ИНН аптеки, загрузка CSV из 1С.
 */
class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->artisan('bonus:admin', ['login' => 'admin', 'password' => 'secret-123'])->assertSuccessful();
        return User::where('login', 'admin')->firstOrFail();
    }

    public function test_guest_is_redirected_to_login()
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/pharmacies')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('Вход в админ-панель');
    }

    public function test_login_with_wrong_and_right_password()
    {
        $this->admin();

        $this->post('/admin/login', ['login' => 'admin', 'password' => 'wrong'])
            ->assertRedirect()->assertSessionHas('error');
        $this->assertGuest();

        $this->post('/admin/login', ['login' => 'admin', 'password' => 'secret-123'])
            ->assertRedirect('/admin');
        $this->assertAuthenticated();

        $this->get('/admin')->assertOk()->assertSee('Обзор');
        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_admin_command_updates_password_and_validates()
    {
        $this->admin();
        $this->artisan('bonus:admin', ['login' => 'admin', 'password' => 'another-pass'])->assertSuccessful();
        $this->assertSame(1, User::count());
        $this->post('/admin/login', ['login' => 'admin', 'password' => 'another-pass'])->assertRedirect('/admin');

        $this->artisan('bonus:admin', ['login' => 'x', 'password' => 'short'])->assertFailed();
    }

    public function test_change_pharmacy_legal_entity()
    {
        $user = $this->admin();
        $leA = LegalEntity::factory()->create(['inn' => '544505317704']);
        $leB = LegalEntity::factory()->create(['inn' => '544509941504']);
        $ph = Pharmacy::factory()->create(['legal_entity_id' => $leA->id, 'number' => 5]);

        $this->actingAs($user)->get('/admin/pharmacies')->assertOk()->assertSee('544505317704');

        $this->actingAs($user)->post("/admin/pharmacies/{$ph->id}/legal-entity", ['legal_entity_id' => $leB->id])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame($leB->id, $ph->fresh()->legal_entity_id);
        $this->assertSame(1, Pharmacy::where('number', 5)->count());

        // то же юрлицо — без изменений
        $this->actingAs($user)->post("/admin/pharmacies/{$ph->id}/legal-entity", ['legal_entity_id' => $leB->id])
            ->assertSessionHas('info');

        // добавить юрлицо
        $this->actingAs($user)->post('/admin/legal-entities', ['inn' => '5410125764', 'name' => 'ООО Новое'])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('legal_entities', ['inn' => '5410125764', 'name' => 'ООО Новое']);
    }

    public function test_import_original_1c_files_in_cp1251()
    {
        $user = $this->admin();
        $cp = fn (string $s) => mb_convert_encoding($s, 'Windows-1251', 'UTF-8');

        $le = UploadedFile::fake()->createWithContent('СправочникЮЛ.csv',
            $cp("ИНН;Наименование\r\n544505317704;Калганова О.А. (ИП)\r\n5445108738\\544601001;ООО \"АРТ Фарм\"\r\n"));
        $ph = UploadedFile::fake()->createWithContent('СправочникАптеки.csv',
            $cp("НомерАптеки;Наименование;ИНН\r\n1;01 Первомайская 12;544505317704\r\n39;АРТ;5445108738\r\n"));
        $cards = UploadedFile::fake()->createWithContent('Бонусы544505317704.csv',
            $cp("short_code;firstname;name;lastname;birthdate;phone;city;gender;newsletter_agree;status;guid;balance;\r\n"
                . "DISN32569;Денисова;Татьяна;Андреевна;20.01.1954;+79139465878;Бердск;1;1;0;1500000001;298.51;\r\n"
                . "DISN00000;Пустая;Карта;;;;Бердск;0;0;0;1500000099;0;\r\n"));

        $this->actingAs($user)->post('/admin/import', [
            'legal_entities' => $le, 'pharmacies' => $ph, 'cards' => [$cards], 'date' => '2026-11-30',
        ])->assertOk()->assertSee('Результат')->assertSee('Бонусы544505317704.csv');

        $this->assertDatabaseHas('legal_entities', ['inn' => '5445108738', 'name' => 'ООО "АРТ Фарм"']);
        $this->assertDatabaseHas('pharmacies', ['number' => 39]);
        $card = Card::where('short_code', 'DISN32569')->firstOrFail();
        $this->assertSame('Денисова', $card->firstname);
        $this->assertEquals(298.51, $card->getBalance());
        $this->assertSame('2026-11-30', CardOperation::where('card_id', $card->id)->value('document_date'));
        $this->assertSame(0, CardOperation::where('card_id', Card::where('short_code', 'DISN00000')->value('id'))->count());

        // повторная загрузка не задваивает остатки
        $this->actingAs($user)->post('/admin/import', ['cards' => [$cards]])->assertOk();
        $this->assertSame(1, CardOperation::count());
    }

    public function test_import_without_files_or_unknown_inn()
    {
        $user = $this->admin();
        $this->actingAs($user)->post('/admin/import', [])->assertSessionHasErrors();

        $cards = UploadedFile::fake()->createWithContent('cards.csv', "short_code;balance\nX1;10\n");
        $this->actingAs($user)->post('/admin/import', ['cards' => [$cards]])->assertSessionHasErrors();
    }

    public function test_reports_page_presets()
    {
        $user = $this->admin();
        $this->actingAs($user)->get('/admin/reports?preset=last_month')->assertOk()->assertSee('Прошлый месяц');
        $this->actingAs($user)->get('/admin/reports?from=2026-01-01&to=2026-01-31')->assertOk()->assertSee('01.01.2026 — 31.01.2026');
    }
}
