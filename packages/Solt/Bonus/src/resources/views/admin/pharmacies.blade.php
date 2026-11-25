@extends('bonus::admin.layout')
@section('title', 'Аптеки')
@section('content')
<h1>Аптеки</h1>
<div class="card">
  <table>
    <thead><tr><th>№</th><th>Название</th><th>Текущий ИНН</th><th>Юрлицо</th><th style="width:420px;">Сменить ИНН</th></tr></thead>
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
      </tr>
    @empty
      <tr><td colspan="5" class="muted">Аптек нет — загрузите справочник на странице «Загрузка из 1С».</td></tr>
    @endforelse
    </tbody>
  </table>
</div>

<div class="card">
  <h2 style="margin-top:0;">Добавить юрлицо</h2>
  <p class="muted">Если нужного ИНН нет в списке — добавьте его здесь, затем выберите у аптеки.</p>
  <form method="post" action="{{ route('admin.legal-entities.store') }}" class="row">
    @csrf
    <div class="field"><label>ИНН</label><input type="text" name="inn" value="{{ old('inn') }}" pattern="[0-9]{10,12}" required style="width:180px;"></div>
    <div class="field" style="flex:1;"><label>Название</label><input type="text" name="name" value="{{ old('name') }}" required></div>
    <button class="btn" type="submit">Добавить</button>
  </form>
</div>

<p class="muted">Смена ИНН влияет только на новые операции: уже записанные чеки хранят своё юрлицо и не меняются.</p>
@endsection
