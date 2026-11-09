<?php
namespace Solt\Bonus\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class BonusController extends Controller{

    /**
     * Check API function
     * @return Response
     */
    function checkApi(){
        return response(['message' => 'API работает!']);
    }
}
