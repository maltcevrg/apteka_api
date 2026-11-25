@extends('bonus::admin.layout')
@section('title', 'Аптеки')
@section('content')
<h1>Аптеки</h1>
<div class="card">
  <table>
    <thead><tr><th>№</th><th>Название</th><th>Текущий ИНН</th><th>Юрлицо</th><th style="width:400px;">Сменить ИНН</th><th></th></tr></thead>
    <tbody>
    @forelse ($pharmacies as $ph)
      <tr>
        <td>{{ $ph->number }}</td>
        <td>{{ $ph->name }}</td>
        <td><code>{{ $ph->legal_entity?->inn }}</code></td>
        <td>{{ $ph->legal_entity?->name }}</td>
        <td>
          <form method="post" action="{{ route('admin.pharmacies.legal-entity', $ph) }}" class="row" style="gap:6px;">
            @csrf
            <select name="legal_entity_id" required>
              @foreach ($legal_entities as $le)
                <option value="{{ $le->id }}" @selected($le->id === $ph->legal_entity_id)>{{ $le->inn }} — {{ $le->name }}</option>
              @endforeach
            </select>
            <button class="btn small" type="submit">Сохранить</button>
          </form>
        </td>
        <td>
          <form method="post" action="{{ route('admin.pharmacies.destroy', $ph) }}" onsubmit="return confirm('Удалить аптеку {{ $ph->number }} «{{ addslashes($ph->name) }}»?');">
            @csrf @method('DELETE')
            <button class="btn small danger" type="submit">Удалить</button>
          </form>
        </td>
      </tr>
    @empty
      <tr><td colspan="6" class="muted">Аптек нет — добавьте ниже или загрузите справочник на странице «Загрузка из 1С».</td></tr>
    @endforelse
    </tbody>
  </table>
</div>

<div class="card">
  <h2 style="margin-top:0;">Добавить аптеку</h2>
  <form method="post" action="{{ route('admin.pharmacies.store') }}" class="row">
    @csrf
    <div class="field"><label>Номер (0–99)</label><input type="text" name="number" value="{{ old('number') }}" pattern="[0-9]{1,2}" required style="width:100px;"></div>
    <div class="field" style="flex:1;"><label>Название</label><input type="text" name="name" value="{{ old('name') }}" required></div>
    <div class="field"><label>Юрлицо</label>
      <select name="legal_entity_id" required>
        @foreach ($legal_entities as $le)
          <option value="{{ $le->id }}" @selected((string) $le->id === old('legal_entity_id'))>{{ $le->inn }} — {{ $le->name }}</option>
        @endforeach
      </select>
    </div>
    <button class="btn" type="submit">Добавить</button>
  </form>
  @if ($legal_entities->isEmpty())<p class="muted">Сначала добавьте хотя бы одно <a href="{{ route('admin.legal-entities') }}">юрлицо</a>.</p>@endif
</div>

<p class="muted">Смена ИНН и удаление влияют только на новые операции: уже записанные чеки хранят свою аптеку и юрлицо. Удалённую аптеку можно завести снова с тем же номером.</p>
@endsection
