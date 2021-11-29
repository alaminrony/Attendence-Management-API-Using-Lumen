<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PushNotification;
use App\Models\User;

class PushNotificationController extends Controller {
    
    public function __construct() {
        $this->middleware('auth:api');
    }

    public function saveNotification(Request $request){
        $target = new PushNotification;
        $target->title = $request->title;
        $target->body = $request->body;
        if($target->save()){
        	return response()->json(['response' => 'success', 'data' => $target]);
        }
    }

}
