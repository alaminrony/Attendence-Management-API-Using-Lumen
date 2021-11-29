<?php

namespace App\Helpers;

use App\Models\BankAccount;
use App\Models\BankPending;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\RoleToAccess;
use App\Models\User;
use App\Models\Transaction;
use App\Models\TimeSchedule;
use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\File;

class Helper {

    public static function dateFormat($date) {
        //return date('d M Y \a\t h:i A', strtotime("-6 hours",   strtotime($date) ));
        return date('d M Y \a\t h:i A', strtotime($date));
    }

    public static function status($data) {

        if ($data == '1') {
            echo "Active";
        } else {
            echo "Inactive";
        }
    }

    public static function pendingCheque($data) {

        if ($data == '1') {
            echo "Approved";
        } else {
            echo "Pending";
        }
    }

    public static function accessToMethod() {
        if (Auth::check()) {
            echo "User logged , user_id : " . $userID;
        } else {
            echo "Not logged"; //It is returning this
        }
        exit;

//        echo "<pre>";print_r(Auth::user()->id);exit;
//        $roleToAccess = RoleToAccess::join('module_operations','module_operations.id','=','role_to_accesses.module_operation_id')
//                ->select('role_to_accesses.id','role_to_accesses.role_id','role_to_accesses.module_id','role_to_accesses.module_operation_id','module_operations.operation','module_operations.method')
//                ->where('role_to_accesses.role_id', Auth::user()->role_id)->get();
//        echo "<pre>";print_r($roleToAccess->toArray());exit;
        if ($roleToAccess->isNotEmpty()) {
            $accessArr = [];
            $i = 0;
            foreach ($roleToAccess as $access) {
                $accessArr[$access->module_id][$i] = $access->module_operation_id;
                $i++;
            }
        }
    }

    public static function time_schedule($user_id, $date) {

        $in_time = TimeSchedule::where(['user_id' => $user_id, 'status' => '1'])->whereDate('created_at',  $date)->select('latitude', 'longitude', 'location', 'user_id', 'description', 'status', 'created_at')->first();
        $out_time = TimeSchedule::where(['user_id' => $user_id, 'status' => '2'])->whereDate('created_at', $date)->select('latitude', 'longitude', 'location', 'user_id', 'description', 'status', 'created_at')->first();

        $total_break = TimeSchedule::where(['user_id' => $user_id, 'status' => '3'])->whereDate('created_at', $date)->count();

        $end_break_time = TimeSchedule::where(['user_id' => $user_id, 'status' => '4'])->select('created_at')->whereDate('created_at',  $date)->orderBy('created_at', 'desc')->get();
        $start_break_time = TimeSchedule::where(['user_id' => $user_id, 'status' => '3'])->select('created_at')->whereDate('created_at', $date)->orderBy('created_at', 'desc')->get();

        $newTimeArr = [];
        if ($total_break == count($end_break_time)) {
            foreach ($end_break_time as $end_key => $end_break) {
                foreach ($start_break_time as $start_key => $start_break) {
                    if ($end_key == $start_key) {
//                        $diff = date_diff($first_date, $second_date);
//                        $difference = Helper::format_interval($diff);
//                        $data['duration'] = $difference;
                        $newTimeArr[] = abs(strtotime($end_break->created_at) - strtotime($start_break->created_at));
                        break;
                    }
                }
            }
        }

        $total_break_time_in_second = !empty(array_sum($newTimeArr)) ? array_sum($newTimeArr) : 0;


        $data['in_time'] = !empty($in_time) ? Helper::dateFormat($in_time->created_at) : '';
        $data['out_time'] = !empty($out_time) ? Helper::dateFormat($out_time->created_at) : '';

        $data['in_latitude'] = !empty($in_time) ? $in_time->latitude : '';
        $data['in_longitude'] = !empty($in_time) ? $in_time->longitude : '';
        $data['in_location'] = !empty($in_time) ? $in_time->location : '';
        $data['break_description'] = !empty($in_time) ? $in_time->description : '';


        $data['out_latitude'] = !empty($out_time) ? $out_time->latitude : '';
        $data['out_longitude'] = !empty($out_time) ? $out_time->longitude : '';
        $data['out_location'] = !empty($out_time) ? $out_time->location : '';


//        $first_date = !empty($in_time) ? new DateTime($in_time->created_at) : '';
//        $second_date = !empty($out_time) ? new DateTime($out_time->created_at) : '';
        $data['duration'] = 0;
        if (!empty($in_time->created_at) && !empty($out_time->created_at)) {
            $duration = abs(strtotime($in_time->created_at) - strtotime($out_time->created_at));
//            $diff = date_diff($first_date, $second_date);
//            $difference = Helper::format_interval($diff);
            $data['duration'] = $duration;
        }

        $office_our_in_second = $data['duration'] - $total_break_time_in_second;


        $data['total_office_hour'] = Helper::time_format($data['duration']);
        $data['total_break_hour'] = Helper::time_format($total_break_time_in_second);
        $data['total_office_hour_without_break'] = Helper::time_format($office_our_in_second);

        $data['date'] = date('d F Y', strtotime($date));
        $data['total_break'] = $total_break > 0 ? $total_break : 0;
        return $data;
    }

    public static function monthly_report($user_id, $date) {

        $in_time = TimeSchedule::where(['user_id' => $user_id, 'status' => '1'])->whereDate('created_at', '=', $date)->select('latitude', 'longitude', 'location', 'user_id', 'description', 'status', 'created_at', 'id')->first();
        $out_time = TimeSchedule::where(['user_id' => $user_id, 'status' => '2'])->whereDate('created_at', '=', $date)->select('latitude', 'longitude', 'location', 'user_id', 'description', 'status', 'created_at')->first();

        $total_break = TimeSchedule::where(['user_id' => $user_id, 'status' => '3'])->whereDate('created_at', '=', $date)->count();

        $end_break_time = TimeSchedule::where(['user_id' => $user_id, 'status' => '4'])->select('created_at')->whereDate('created_at', '=', $date)->orderBy('created_at', 'desc')->get();
        $start_break_time = TimeSchedule::where(['user_id' => $user_id, 'status' => '3'])->select('created_at')->whereDate('created_at', '=', $date)->orderBy('created_at', 'desc')->get();
//  if ( $in_time && date('h:i:s', strtotime($in_time->created_at)) > "09:30:00" && Auth::id() == 30 ) dd( date('h:i:s', strtotime($in_time->created_at)) > "09:30:00" );
        $newTimeArr = [];
        $totalLateArr = 0;


        $in = null;

        if ( ! empty($in_time->created_at) ) $in = date('H:i:s', strtotime( $in_time->created_at));
        
        $endTime = date('H:i:s', strtotime(self::officeStartTime()));
       
        //dd($endTime);
        if ( $in_time && $in > $endTime ) $totalLateArr+=1;
        
        if ($total_break == count($end_break_time)) {
            foreach ($end_break_time as $end_key => $end_break) {
                foreach ($start_break_time as $start_key => $start_break) {
                    if ($end_key == $start_key) {
//                        $diff = date_diff($first_date, $second_date);
//                        $difference = Helper::format_interval($diff);
//                        $data['duration'] = $difference;

                        $newTimeArr[] = abs(strtotime($end_break->created_at) - strtotime($start_break->created_at));
                        break;
                    }
                }
            }
        }

        //  if ( $in_time && date('h:i:s', strtotime($in_time->created_at)) > "09:30:00" && Auth::id() == 30 ) dd( $totalLateArr );

        $total_break_time_in_second = !empty(array_sum($newTimeArr)) ? array_sum($newTimeArr) : 0;


        $data['in_time'] = !empty($in_time) ? Helper::dateFormat($in_time->created_at) : '';
        $data['out_time'] = !empty($out_time) ? Helper::dateFormat($out_time->created_at) : '';

        $data['in_latitude'] = !empty($in_time) ? $in_time->latitude : '';
        $data['in_longitude'] = !empty($in_time) ? $in_time->longitude : '';
        $data['in_location'] = !empty($in_time) ? $in_time->location : '';
        $data['break_description'] = !empty($in_time) ? $in_time->description : '';


        $data['out_latitude'] = !empty($out_time) ? $out_time->latitude : '';
        $data['out_longitude'] = !empty($out_time) ? $out_time->longitude : '';
        $data['out_location'] = !empty($out_time) ? $out_time->location : '';



//        $first_date = !empty($in_time) ? new DateTime($in_time->created_at) : '';
//        $second_date = !empty($out_time) ? new DateTime($out_time->created_at) : '';

        $data['duration'] = 0;
        if (!empty($in_time->created_at) && !empty($out_time->created_at)) {
            $duration = abs(strtotime($in_time->created_at) - strtotime($out_time->created_at));
//            $diff = date_diff($first_date, $second_date);
//            $difference = Helper::format_interval($diff);
            $data['duration'] = $duration;
        }

        $office_our_in_second = $data['duration'] - $total_break_time_in_second;


        $data['total_office_hour'] = Helper::time_format($data['duration']);
        $data['total_break_hour'] = Helper::time_format($total_break_time_in_second);
        $data['total_office_hour_without_break'] = Helper::time_format($office_our_in_second);

        $data['date'] = date('d F Y', strtotime($date));
        $data['total_break'] = $total_break > 0 ? $total_break : 0;
        $data['total_late'] = ($in_time) ? $totalLateArr : 0;
        // if(Auth::id() == 30)
   
        return $data;
    }

    public static function time_format($time) {
        $convert_hour_min_sec = gmdate("H:i:s", $time);
        $convert_array = explode(':', $convert_hour_min_sec);

        $result = "";
        if (!empty($convert_array[0]) && $convert_array[0] > 0) {
            $result .= (int) $convert_array[0] . ' hours ';
        }
        if (!empty($convert_array[1]) && $convert_array[1] > 0) {
            $result .= (int) $convert_array[1] . ' minutes ';
        }

        return $result;
    }

    public static function format_interval($interval) {
        $result = "";
        if ($interval->y) {
            $result .= $interval->format("%y years ");
        }
        if ($interval->m) {
            $result .= $interval->format("%m months ");
        }
        if ($interval->d) {
            $result .= $interval->format("%d days ");
        }
        if ($interval->h) {
            $result .= $interval->format("%h hours ");
        }
        if ($interval->i) {
            $result .= $interval->format("%i minutes ");
        }

        return $result;
    }


    public static function returnValidationErrorResponse( $validator)
    {
        return response()->json([
            'Status' => 'Error',
            'Message' => 'Validation Error',
            'Error' => $validator->errors()
        ], Response::HTTP_UNAUTHORIZED);
    }

    public static function returnInternalServerError()
    {
        return response()->json([
            'Status' => 'Error',
            'Message' => "Internal Server Error"
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }


    public static function upload(Request $request, $name)
    {
        $picName = $request->file($name)->getClientOriginalName();
        $picName = uniqid() . '_' . time() . $picName;
        $path = 'uploads' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR ;
        $destinationPath = public_path($path); // upload path
        File::makeDirectory($destinationPath, 0777, true, true);
        $request->file($name)->move($destinationPath, $picName);

        return $path . $picName;
    }

    public static function updateUserStatus()
    {
        $date = date('Y-m-d');

        $users = User::whereDate('updated_at', $date)->get();

        foreach ($users as $user){
            if ( $user->idel_status == '1' ) $user->idel_status = '2';
            if ( $user->break_status == '1' ) $user->break_status = '0';
            $user->save();
        }
    }

    public static function checkBankBalance($bank_id)
    {
        $bank = BankAccount::find($bank_id);

        return ( $bank ) ? $bank->current_amount : null;
    }

    public static function getBankAccountId($id)
    {
        $bank = BankAccount::where('account_no', $id)->first();

        return ($bank) ? $bank->account_no : null;
    }


    public static function checkTotalAmountInTransaction()
    {
        $cashTotal = 0;
        $transactions = Transaction::all();

        foreach ($transactions as $transaction){
            if ( $transaction->payment_type == "cash" ){
                if ($transaction->tran_type == "income") {
                    $cashTotal += $transaction->tran_amount;
                } else {
                    $pending = BankPending::where([
                        'bank_account_id' => $transaction->bank_account_id,
                        'transaction_id' => $transaction->id,
                        'cheque_status' => 1
                    ])->first();

                    if ( $pending && $pending->tran_type == "Cash In" ) $cashTotal += $transaction->tran_amount;
                    else $cashTotal -= $transaction->tran_amount;
                }
            }
        }

        return $cashTotal;
    }
    
    
    /**
     * @return string
     */
    public static function officeStartTime()
    {
        $settings = Setting::first();

        return ($settings) ? $settings->office_start_at : "09:00:00";
    }
}
