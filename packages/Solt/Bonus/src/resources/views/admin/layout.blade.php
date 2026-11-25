<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Админ-панель') — Бонусные карты</title>
<style>
  :root { --bg:#f4f6f8; --card:#fff; --line:#dfe3e8; --text:#1f2933; --muted:#616e7c; --accent:#1f6feb; --ok:#1a7f37; --err:#b42318; --warn:#9a6700; }
  * { box-sizing:border-box; }
  body { margin:0; font:15px/1.45 -apple-system, "Segoe UI", Roboto, Arial, sans-serif; background:var(--bg); color:var(--text); }
  header { background:#1f2933; color:#fff; padding:0 24px; display:flex; align-items:center; gap:24px; height:52px; }
  header .brand { font-weight:600; }
  header nav a { color:#cbd2d9; text-decoration:none; margin-right:18px; padding:16px 0; display:inline-block; border-bottom:2px solid transparent; }
  header nav a.active, header nav a:hover { color:#fff; border-color:var(--accent); }
  header .user { margin-left:auto; color:#cbd2d9; display:flex; align-items:center; gap:12px; }
  header button { background:none; border:1px solid #52606d; color:#fff; padding:4px 10px; border-radius:4px; cursor:pointer; }
  main { max-width:1100px; margin:24px auto; padding:0 16px; }
  h1 { font-size:22px; margin:0 0 16px; }
  h2 { font-size:17px; margin:24px 0 10px; }
  .card { background:var(--card); border:1px solid var(--line); border-radius:8px; padding:18px 20px; margin-bottom:18px; }
  .flash { padding:10px 14px; border-radius:6px; margin-bottom:14px; border:1px solid; }
  .flash.success { background:#e6f4ea; border-color:#a6d8b4; color:var(--ok); }
  .flash.error { background:#fdecea; border-color:#f5b5ae; color:var(--err); }
  .flash.info { background:#eef3fb; border-color:#bcd0f0; color:var(--accent); }
  .flash.warn { background:#fff8e1; border-color:#f3dc8a; color:var(--warn); }
  table { width:100%; border-collapse:collapse; font-size:14px; }
  th, td { text-align:left; padding:8px 10px; border-bottom:1px solid var(--line); vertical-align:middle; }
  th { color:var(--muted); font-weight:600; background:#fafbfc; }
  tr:hover td { background:#fafbfc; }
  input[type=text], input[type=password], input[type=date], input[type=file], select { font:inherit; padding:7px 9px; border:1px solid #c3cad2; border-radius:5px; background:#fff; }
  input[type=text], input[type=password] { width:100%; }
  .btn { font:inherit; padding:7px 14px; border-radius:5px; border:1px solid var(--accent); background:var(--accent); color:#fff; cursor:pointer; text-decoration:none; display:inline-block; }
  .btn.secondary { background:#fff; color:var(--accent); }
  .btn.small { padding:4px 10px; font-size:13px; }
  .row { display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; }
  .field { display:flex; flex-direction:column; gap:4px; }
  .field label { font-size:13px; color:var(--muted); }
  .muted { color:var(--muted); }
  .stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px,1fr)); gap:14px; }
  .stat { background:var(--card); border:1px solid var(--line); border-radius:8px; padding:14px 16px; }
  .stat b { display:block; font-size:26px; }
  .num { text-align:right; font-variant-numeric:tabular-nums; }
  .presets a { display:inline-block; margin:0 8px 8px 0; padding:5px 10px; border:1px solid var(--line); border-radius:16px; text-decoration:none; color:var(--text); background:#fff; font-size:13px; }
  .presets a.active { border-color:var(--accent); color:var(--accent); background:#eef3fb; }
  code { background:#eef1f4; padding:1px 5px; border-radius:3px; font-size:13px; }
</style>
</head>
<body>
@auth
<header>
  <span class="brand">Бонусные карты</span>
  <nav>
    <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Обзор</a>
    <a href="{{ route('admin.pharmacies') }}" class="{{ request()->routeIs('admin.pharmacies*') ? 'active' : '' }}">Аптеки</a>
    <a href="{{ route('admin.import') }}" class="{{ request()->routeIs('admin.import*') ? 'active' : '' }}">Загрузка из 1С</a>
    <a href="{{ route('admin.reports') }}" class="{{ request()->routeIs('admin.reports*') ? 'active' : '' }}">Отчёты</a>
  </nav>
  <div class="user">
    <span>{{ auth()->user()->login ?? auth()->user()->name }}</span>
    <form method="post" action="{{ route('admin.logout') }}">@csrf<button type="submit">Выйти</button></form>
  </div>
</header>
@endauth
<main>
  @foreach (['success', 'error', 'info', 'warn'] as $type)
    @if (session($type))<div class="flash {{ $type }}">{{ session($type) }}</div>@endif
  @endforeach
  @if ($errors->any())
    <div class="flash error"><ul style="margin:0; padding-left:18px;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
  @endif
  @yield('content')
</main>
</body>
</html>
