<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\NotificationSend;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    public function store(Request $request)
    {
        $validator = $this->validationNotification($request);

        if ( $validator->fails() ) return response()->json([
            'status' => 'Error',
            'error' => $validator->errors()
        ], Response::HTTP_NOT_ACCEPTABLE);

        $create = Device::updateOrCreate(
            ['user_id' => $request->user_id, 'device_type' => $request->device_type],
            $request->all()
        );

        if ( $create ) {

            NotificationSend::create([
                'user_id' => $request->user_id,
                'notification_id' => $create->id,
                'read_at' => '0'
            ]);

            return response()->json([
                'status' => 'Success',
                'message' => 'Stored'
            ], Response::HTTP_OK);
        }

        return response()->json([
            'status' => 'Success',
            'error' => 'Internal Server Error'
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }


    public function userNotification(Request $request)
    {
        $noti = NotificationSend::where('user_id', $request->user()->id)->with('pushnotification')->paginate(10);
        $device = Device::where('user_id', $request->user()->id)->get();

        return response()->json([
            'status' => 'Success',
            'message' => 'Data Retrived',
            'data' => [
                'notification' => $noti,
                'devices' => $device
            ]
        ], Response::HTTP_OK);
    }


    private function validationNotification(Request $request)
    {
        $request->request->add(['user_id' => $request->user()->id]);
        return Validator::make($request->all(), [
            'user_id' => 'required',
            'device_type' => 'required',
            'user_type' => 'required',
            'token' => 'required',
        ]);
    }
}
