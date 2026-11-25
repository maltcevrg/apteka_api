<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('code') — @yield('title')</title>
<style>
  :root { --bg:#f4f6f8; --text:#1f2933; --muted:#616e7c; --accent:#1f6feb; --line:#dfe3e8; }
  * { box-sizing:border-box; }
  html, body { height:100%; margin:0; }
  body { font:16px/1.5 -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color:var(--text);
         background:var(--bg) radial-gradient(circle at 20% 20%, #e3edfb 0, transparent 40%),
                    radial-gradient(circle at 80% 80%, #e6f4ea 0, transparent 40%);
         display:flex; align-items:center; justify-content:center; padding:24px; }
  .box { background:#fff; border:1px solid var(--line); border-radius:16px; padding:40px 44px; max-width:560px; width:100%;
         box-shadow:0 10px 40px rgba(31,41,51,.08); text-align:center; }
  .pill { width:88px; height:88px; margin:0 auto 18px; border-radius:50%; background:#eef3fb; display:flex; align-items:center; justify-content:center; }
  .pill svg { width:46px; height:46px; }
  .code { font-size:64px; font-weight:700; letter-spacing:2px; line-height:1; color:var(--accent); margin:0 0 6px; font-variant-numeric:tabular-nums; }
  h1 { font-size:22px; margin:0 0 10px; }
  p { color:var(--muted); margin:0 0 22px; }
  .actions a { display:inline-block; padding:10px 18px; border-radius:8px; text-decoration:none; font-weight:500; margin:0 6px 8px; }
  .primary { background:var(--accent); color:#fff; }
  .secondary { background:#fff; color:var(--accent); border:1px solid #bcd0f0; }
  .foot { margin-top:22px; font-size:13px; color:#9aa5b1; }
  code { background:#eef1f4; padding:2px 6px; border-radius:4px; font-size:14px; color:var(--text); word-break:break-all; }
</style>
</head>
<body>
  <div class="box">
    <div class="pill">
      {{-- капсула-таблетка: бонусная система аптечной сети --}}
      <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="6" y="18" width="36" height="12" rx="6" transform="rotate(-35 24 24)" fill="#1f6feb"/>
        <path d="M18.5 32.3 29.5 15.7" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".9"/>
        <rect x="6" y="18" width="18" height="12" rx="6" transform="rotate(-35 24 24)" fill="#8fb4f5"/>
      </svg>
    </div>
    <div class="code">@yield('code')</div>
    <h1>@yield('title')</h1>
    <p>@yield('message')</p>
    <div class="actions">
      <a class="primary" href="{{ url('/') }}">На главную</a>
      <a class="secondary" href="javascript:history.back()">Назад</a>
    </div>
    <div class="foot">Бонусные карты · @yield('hint')</div>
  </div>
</body>
</html>
