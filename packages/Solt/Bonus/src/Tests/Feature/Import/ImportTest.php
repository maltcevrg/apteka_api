<?php

namespace Solt\Bonus\Tests\Feature\Import;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Solt\Bonus\Console\ImportCommand;
use Solt\Bonus\Models\Card;
use Solt\Bonus\Models\CardOperation;
use Solt\Bonus\Models\LegalEntity;
use Solt\Bonus\Models\Pharmacy;
use Solt\Bonus\Services\LedgerService;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/bonus-import-' . uniqid();
        mkdir($this->dir);
        file_put_contents($this->dir . '/legal_entities.csv',
            "inn;name\n544505317704;Калганова О.А. (ИП)\n5445108738\\544601001;ООО \"АРТ Фарм\"\n");
        file_put_contents($this->dir . '/pharmacies.csv',
            "number;name;inn\n1;01 Первомайская 12;544505317704\n39;АРТ;5445108738\n77;Нет такого ИНН;1234567890\n");
        file_put_contents($this->dir . '/cards_544505317704.csv',
            "short_code;firstname;name;lastname;birthdate;phone;city;gender;newsletter_agree;status;guid;balance\n"
            . "DISN32569;Денисова;Татьяна;Андреевна;20.01.1954;+79139465878;Бердск;1;1;0;1500000001;298.51\n"
            . "DISN40072;Маркелова;Анастасия;Юрьевна;;;Бердск;1;1;0;1500000009;6.11\n"
            . "DISN00000;Пустая;Карта;;01.01.1990;+79000000000;Бердск;0;0;0;1500000099;0.00\n");
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*'));
        rmdir($this->dir);
        parent::tearDown();
    }

    public function test_import_creates_directories_cards_and_opening_balances()
    {
        $this->artisan('bonus:import', ['dir' => $this->dir, '--date' => '2026-11-01'])->assertExitCode(0);

        $this->assertEquals(2, LegalEntity::count());
        $this->assertNotNull(LegalEntity::where('inn', '5445108738')->first(), 'КПП отрезан');
        $this->assertEquals(2, Pharmacy::count(), 'аптека с неизвестным ИНН пропущена');

        $card = Card::where('short_code', 'DISN32569')->first();
        $this->assertEquals('Денисова', $card->firstname);
        $this->assertEquals(298.51, $card->getBalance());
        $this->assertEquals(LegalEntity::where('inn', '544505317704')->value('id'), $card->fresh()->legal_entity_id);

        $op = CardOperation::where('card_id', $card->id)->first();
        $this->assertEquals(ImportCommand::OPENING_DOCUMENT_TYPE, $op->document_type);
        $this->assertEquals('2026-11-01', substr((string) $op->document_date, 0, 10));
        $this->assertNull($op->pharmacy_id);

        $noDate = Card::where('short_code', 'DISN40072')->first();
        $this->assertNull($noDate->birthdate);
        $this->assertNull($noDate->phone);

        $this->assertEquals(0, Card::where('short_code', 'DISN00000')->first()->getBalance());
        $this->assertEquals(2, CardOperation::count(), 'нулевой остаток не создаёт операцию');
    }

    public function test_import_is_idempotent()
    {
        $this->artisan('bonus:import', ['dir' => $this->dir])->assertExitCode(0);
        $this->artisan('bonus:import', ['dir' => $this->dir])->assertExitCode(0);

        $this->assertEquals(3, Card::count());
        $this->assertEquals(2, CardOperation::count());
        $card = Card::where('short_code', 'DISN32569')->first();
        $this->assertEquals(298.51, $card->getBalance());
        $this->assertSame([], LedgerService::checkCard($card->id));
    }

    public function test_bundled_import_files_are_consistent()
    {
        $this->artisan('bonus:import')->assertExitCode(0);

        $this->assertEquals(6, LegalEntity::count());
        $this->assertEquals(40, Pharmacy::count());
        $this->assertEquals(520, Card::count());
        $this->assertEquals(Card::count(), Card::distinct()->count('short_code'));

        foreach (Card::pluck('id') as $id) {
            $this->assertSame([], LedgerService::checkCard($id));
        }
    }
}
