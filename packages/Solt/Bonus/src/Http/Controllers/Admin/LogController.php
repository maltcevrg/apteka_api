<?php

namespace Solt\Bonus\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Solt\Bonus\Models\AdminLog;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->validate([
            'action' => ['nullable', 'string', 'max:64'],
            'login'  => ['nullable', 'string', 'max:64'],
            'from'   => ['nullable', 'date'],
            'to'     => ['nullable', 'date'],
        ]);
        $q = AdminLog::orderByDesc('id');
        if (!empty($f['action'])) $q->where('action', $f['action']);
        if (!empty($f['login']))  $q->where('login', $f['login']);
        if (!empty($f['from']))   $q->whereDate('created_at', '>=', $f['from']);
        if (!empty($f['to']))     $q->whereDate('created_at', '<=', $f['to']);

        return view('bonus::admin.log', [
            'logs'    => $q->paginate(100)->withQueryString(),
            'filters' => $f,
            'actions' => AdminLog::ACTIONS,
            'logins'  => AdminLog::whereNotNull('login')->distinct()->orderBy('login')->pluck('login'),
        ]);
    }
}
