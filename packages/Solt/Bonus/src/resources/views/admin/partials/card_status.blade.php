@php($st = \Solt\Bonus\Models\Card::$statuses[$card->status] ?? $card->status)
@if ((int) $card->status === 0 && !$card->deleted_at)<span class="badge ok">действительна</span>
@elseif ((int) $card->status === 2)<span class="badge">заменена</span>
@else<span class="badge err">{{ (int) $card->status === 1 ? 'заблокирована' : $st }}</span>@endif
