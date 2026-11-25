@extends('bonus::admin.layout')
@section('title', 'Загрузка из 1С')
@section('content')
<h1>Загрузка из 1С</h1>

@isset($stats)
<div class="card">
  <h2 style="margin-top:0;">Результат</h2>
  <div class="stats">
    <div class="stat"><span class="muted">Юрлиц</span><b>{{ $stats['legal_entities'] }}</b></div>
    <div class="stat"><span class="muted">Аптек</span><b>{{ $stats['pharmacies'] }}</b></div>
    <div class="stat"><span class="muted">Карт</span><b>{{ $stats['cards'] }}</b></div>
    <div class="stat"><span class="muted">Остатков введено</span><b>{{ $stats['openings'] }}</b></div>
  </div>
  @if ($stats['log'])<ul style="margin-top:14px;">@foreach ($stats['log'] as $l)<li>{{ $l }}</li>@endforeach</ul>@endif
  @if ($stats['skipped'] || !empty($file_errors))
    <div class="flash warn" style="margin-top:14px;"><b>Пропущено:</b><ul style="margin:6px 0 0; padding-left:18px;">
      @foreach (array_merge($file_errors ?? [], $stats['skipped']) as $s)<li>{{ $s }}</li>@endforeach
    </ul></div>
  @endif
</div>
@endisset

<div class="card">
  <form method="post" action="{{ route('admin.import.store') }}" enctype="multipart/form-data">
    @csrf
    <p>Файлы выгрузки 1С как есть: разделитель «;», кодировка Windows-1251 или UTF-8, первая строка — заголовки. Можно загружать любую часть: только справочники, только карты.</p>
    <div class="row" style="margin-bottom:14px;">
      <div class="field"><label>Юрлица (СправочникЮЛ.csv: ИНН;Наименование)</label><input type="file" name="legal_entities" accept=".csv,.txt"></div>
      <div class="field"><label>Аптеки (СправочникАптеки.csv: НомерАптеки;Наименование;ИНН)</label><input type="file" name="pharmacies" accept=".csv,.txt"></div>
    </div>
    <div class="row" style="margin-bottom:14px;">
      <div class="field"><label>Карты с остатками (Бонусы&lt;ИНН&gt;.csv, можно несколько)</label><input type="file" name="cards[]" accept=".csv,.txt" multiple></div>
      <div class="field"><label>ИНН для файлов карт (если его нет в имени файла)</label><input type="text" name="inn" value="{{ old('inn') }}" pattern="[0-9]{10,12}" style="width:180px;"></div>
      <div class="field"><label>Дата документа «Ввод остатков»</label><input type="date" name="date" value="{{ old('date', now()->format('Y-m-d')) }}"></div>
    </div>
    <button class="btn" type="submit">Загрузить</button>
  </form>
</div>

<div class="card">
  <h2 style="margin-top:0;">Как это работает</h2>
  <ul>
    <li>Юрлицо ищется по ИНН (КПП после «\» отбрасывается), аптека — по номеру, карта — по номеру карты. Существующие записи обновляются, новые создаются.</li>
    <li>Остаток карты становится <b>первой операцией журнала</b> — начислением от юрлица из имени файла с типом документа «Ввод остатков» и указанной датой.</li>
    <li>Если у карты уже есть хоть одна операция, остаток <b>не вводится повторно</b> — повторная загрузка безопасна.</li>
    <li>Всё загружается одной транзакцией: при ошибке в файле ничего не записывается.</li>
  </ul>
</div>
@endsection
