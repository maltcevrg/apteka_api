<?php

namespace Solt\Bonus\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Журнал действий в админ-панели: кто, когда, что изменил.
 */
class AdminLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'login', 'action', 'subject', 'details', 'ip', 'created_at'];

    protected $casts = ['details' => 'array', 'created_at' => 'datetime'];

    public const ACTIONS = [
        'login'                => 'Вход',
        'pharmacy.create'      => 'Аптека: создана',
        'pharmacy.update'      => 'Аптека: изменена',
        'pharmacy.legal_entity'=> 'Аптека: смена ИНН',
        'pharmacy.delete'      => 'Аптека: удалена',
        'legal_entity.create'  => 'Юрлицо: создано',
        'legal_entity.update'  => 'Юрлицо: изменено',
        'legal_entity.delete'  => 'Юрлицо: удалено',
        'import'               => 'Загрузка из 1С',
        'card.adjust'          => 'Карта: корректировка баланса',
        'card.block'           => 'Карта: блокировка',
        'card.unblock'         => 'Карта: разблокировка',
        'card.replace'         => 'Карта: замена',
        'user.create'          => 'Пользователь: создан',
        'user.role'            => 'Пользователь: роль',
        'user.reset'           => 'Пользователь: сброс пароля',
        'user.delete'          => 'Пользователь: удалён',
        'client.create'        => 'Касса (клиент API): создана',
        'client.secret'        => 'Касса: новый секрет',
        'client.revoke'        => 'Касса: отозвана',
        'ledger.check'         => 'Проверка цепочки остатков',
    ];

    public static function write(string $action, ?string $subject = null, array $details = []): self
    {
        $user = auth()->user();
        return self::create([
            'user_id'    => $user?->id,
            'login'      => $user?->login,
            'action'     => $action,
            'subject'    => $subject,
            'details'    => $details ?: null,
            'ip'         => request()?->ip(),
            'created_at' => now(),
        ]);
    }

    public function actionName(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }
}
