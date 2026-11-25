<?php

namespace Solt\Bonus\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Solt\Bonus\Models\Card;
use Solt\Bonus\Models\CardOperation;
use Solt\Bonus\Models\LegalEntity;
use Solt\Bonus\Models\Pharmacy;

class DashboardController extends Controller
{
    public function index()
    {
        return view('bonus::admin.dashboard', [
            'counts' => [
                'legal_entities' => LegalEntity::count(),
                'pharmacies'     => Pharmacy::count(),
                'cards'          => Card::count(),
                'operations'     => CardOperation::count(),
            ],
            'last_operation' => CardOperation::orderByDesc('id')->first(),
        ]);
    }
}
