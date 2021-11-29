<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TimeSchedule;
use Validator;
use DB;
use App\Helpers\Helper;
use App\Models\User;
use PDF;
use Illuminate\Support\Facades\Response;



class TimeScheduleController extends Controller {

    public function __construct() {
        $this->middleware('auth:api',['except'=>['dayWiseReport']]);
    }

    public function timeStore(Request $request) {
//        echo "<pre>";print_r($request->all());exit;
        $rules = [
            'user_id' => 'required|numeric',
            'latitude' => 'required',
            'longitude' => 'required',
            'location' => 'required',
            'status' => 'required',
        ];


        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }

        $checkCurrent = $this->checkCurrentStatus($request->user_id);


            $target = new TimeSchedule;
            $target->date_time = date('Y-m-d');
            $target->latitude = $request->latitude;
            $target->longitude = $request->longitude;
            $target->location = $request->location;
            $target->status = $request->status;
            $target->user_id = $request->user_id;
            $target->description = $request->description;

            $target->created_at = date('Y-m-d H:i:s');
            if ($target->save()) {
                if($request->status == '1'){
                    $user = User::findOrFail($request->user_id);
                    $user->idel_status = '1';
                    $user->save();
                }else if($request->status == '2'){
                    $user = User::findOrFail($request->user_id);
                    $user->idel_status = '2';
                    $user->save();
                }else if($request->status == '3'){
                    $user = User::findOrFail($request->user_id);
                    $user->break_status = '1';
                    $user->save();
                }else if($request->status == '4'){
                    $user = User::findOrFail($request->user_id);
                    $user->break_status = '0';
                    $user->save();
                }

            }

        return response()->json(['response' => 'success', 'message' => 'Time schedule inserted successfully']);
    }

    private function checkCurrentStatus($id){
        $user = TimeSchedule::where('user_id', $id)->where('date_time', date('Y-m-d'))->orderBy('id', 'desc')->first();

        return ( $user ) ? $user->status:null;
    }

    public function history(Request $request) {

        $rules = [
            'user_id' => 'required|numeric',
        ];
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }

        $targets = TimeSchedule::where('user_id', $request->user_id)->orderBy('id', 'desc')->whereDate('created_at', '<=', date('Y-m-d'))->select('*', DB::raw("DATE(created_at) as day"))->get()->groupBy('day');

//        echo "<pre>";print_r($targets->toArray());exit;

        $newTarget = [];

        if ($targets->isNotEmpty()) {
            $i = 0;
            foreach ($targets as $date => $targetArr) {
                //echo "<pre>"; print_r($targetArr); exit();
                foreach ($targetArr as $key => $value) {
//                    echo "<pre>";print_r($i);exit;
                    $detailsArr = Helper::time_schedule($value->user_id, $date);
                    $newTarget[$i]['date'] = !empty($date) ? $date : '';
                    $newTarget[$i]['in_time'] = !empty($detailsArr['in_time']) ? $detailsArr['in_time'] : '';
                    $newTarget[$i]['out_time'] = !empty($detailsArr['out_time']) ? $detailsArr['out_time'] : '';
                    $newTarget[$i]['in_latitude'] = !empty($detailsArr['in_latitude']) ? $detailsArr['in_latitude'] : '';
                    $newTarget[$i]['in_longitude'] = !empty($detailsArr['in_longitude']) ? $detailsArr['in_longitude'] : '';
                    $newTarget[$i]['in_location'] = !empty($detailsArr['in_location']) ? $detailsArr['in_location'] : '';
                    $newTarget[$i]['break_description'] = !empty($detailsArr['break_description']) ? $detailsArr['break_description'] : '';
                    $newTarget[$i]['out_latitude'] = !empty($detailsArr['out_latitude']) ? $detailsArr['out_latitude'] : '';
                    $newTarget[$i]['out_longitude'] = !empty($detailsArr['out_longitude']) ? $detailsArr['out_longitude'] : '';
                    $newTarget[$i]['out_location'] = !empty($detailsArr['out_location']) ? $detailsArr['out_location'] : '';
                    $newTarget[$i]['total_break'] = $detailsArr['total_break'];
                    $newTarget[$i]['total_office_time'] = !empty($detailsArr['total_office_hour']) ? $detailsArr['total_office_hour'] : '';
                    $newTarget[$i]['total_break_time'] = !empty($detailsArr['total_break_hour']) ? $detailsArr['total_break_hour'] : '';
                    $newTarget[$i]['total_office_time_without_break'] = !empty($detailsArr['total_office_hour_without_break']) ? $detailsArr['total_office_hour_without_break'] : '';
                    $newTarget[$i]['user_id'] = !empty($value->user_id) ? $value->user_id : '';
                }
                $i += 1;
            }
        }

        return response()->json(['response' => 'success', 'data' => $newTarget]);
    }

    public function monthlyReport(Request $request) {
//        echo '<pre>';print_r($request->all());exit;
        $rules = [
            'user_id' => 'required|numeric',
            'month' => 'required|numeric',
            'year' => 'required|numeric',
        ];
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }

//        $targets = TimeSchedule::where('user_id', $request->user_id)->select('*',DB::raw("MONTH(created_at) as month"),DB::raw("DATE(created_at) as day"),DB::raw("YEAR(created_at) as year"))->get()->groupBy(['year','month','day']);
        $targets = TimeSchedule::where('user_id', $request->user_id)
            ->select('*', DB::raw("DATE(created_at) as day"))
            ->whereDate('created_at', '!=', date('Y-m-d'))
            ->whereMonth('created_at', $request->month)
            ->whereYear('created_at', $request->year)
            ->get()
            ->groupBy('day');

//        echo "<pre>";print_r($targets->toArray());exit;
        $newTarget = [];
        $i = 0;
        if ($targets->isNotEmpty()) {
            foreach ($targets as $date => $targetArr) {
                foreach ($targetArr as $key => $value) {
                    $detailsArr = Helper::monthly_report($value->user_id, $date);
//                    echo "<pre>";print_r($detailsArr);exit;
                    $newTarget[$i]['date'] = !empty($date) ? $date : '';
                    $newTarget[$i]['in_time'] = !empty($detailsArr['in_time']) ? $detailsArr['in_time'] : '';
                    $newTarget[$i]['out_time'] = !empty($detailsArr['out_time']) ? $detailsArr['out_time'] : '';
                    $newTarget[$i]['in_latitude'] = !empty($detailsArr['in_latitude']) ? $detailsArr['in_latitude'] : '';
                    $newTarget[$i]['in_longitude'] = !empty($detailsArr['in_longitude']) ? $detailsArr['in_longitude'] : '';
                    $newTarget[$i]['in_location'] = !empty($detailsArr['in_location']) ? $detailsArr['in_location'] : '';
                    $newTarget[$i]['break_description'] = !empty($detailsArr['break_description']) ? $detailsArr['break_description'] : '';
                    $newTarget[$i]['out_latitude'] = !empty($detailsArr['out_latitude']) ? $detailsArr['out_latitude'] : '';
                    $newTarget[$i]['out_longitude'] = !empty($detailsArr['out_longitude']) ? $detailsArr['out_longitude'] : '';
                    $newTarget[$i]['out_location'] = !empty($detailsArr['out_location']) ? $detailsArr['out_location'] : '';
                    $newTarget[$i]['total_break'] = $detailsArr['total_break'];
                    $newTarget[$i]['total_office_time'] = !empty($detailsArr['total_office_hour']) ? $detailsArr['total_office_hour'] : '';
                    $newTarget[$i]['total_break_time'] = !empty($detailsArr['total_break_hour']) ? $detailsArr['total_break_hour'] : '';
                    $newTarget[$i]['total_office_time_without_break'] = !empty($detailsArr['total_office_hour_without_break']) ? $detailsArr['total_office_hour_without_break'] : '';
                }
                $i++;
            }
        }
        return response()->json(['response' => 'success', 'data' => $newTarget]);
    }

    public function dayWiseReport(Request $request){
        $rules = [
            'filter_date' => 'required',
        ];
        
        
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }


        $withinTime = $request->filter_date;

        $users = TimeSchedule::distinct()->get('user_id');


        $finalArr = [];
        if($users->isNotEmpty()){
            foreach($users as $user){
                $finalArr[] = $this->dayWiseReportByUserId($user->user_id,$request->filter_date);
            }
        }
//dd($finalArr);
        $presentLateCount = $this->presentAndLateEmployee($withinTime);


        //return view('print.day-wise-report', compact('finalArr', 'request','presentLateCount'));

        $pdf = PDF::loadView('print.day-wise-report', compact('finalArr', 'request','presentLateCount'))
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif']);

        $fileName = "day_wise_report_" . date('d_m_Y_H_i_s').'.pdf';
        $path = 'uploads/pdf/' . $fileName;
        $pdf->save($path);
        // return $pdf->stream("$fileName.pdf");
        // return $pdf->download("$fileName.pdf");

        return response()->json(
            [
                'Status' => 'success',
                'Message' => "Pdf Generated successfully!!",
                'data' => url('uploads/pdf/' . $fileName),
            ]);


    }


    public function dayWiseReportByUserId($user_id,$filter_date){

        $targets = TimeSchedule::where('user_id', $user_id)->where('date_time',$filter_date)->select('*',DB::raw("DATE(created_at) as day"))->get()->groupBy('day');
    
        $user = User::where('id',$user_id)->first();
        $newTarget = [];

        if ($targets->isNotEmpty()) {
            $i = 0;
            foreach ($targets as $date => $targetArr) {
                foreach ($targetArr as $key => $value) {
                    $detailsArr = Helper::time_schedule($value->user_id, $date);
                    //dd($detailsArr);
                    $newTarget['date'] = !empty($date) ? $date : '';
                    $newTarget['in_time'] = !empty($detailsArr['in_time']) ? $detailsArr['in_time'] : '';
                    $newTarget['out_time'] = !empty($detailsArr['out_time']) ? $detailsArr['out_time'] : '';
                    $newTarget['in_latitude'] = !empty($detailsArr['in_latitude']) ? $detailsArr['in_latitude'] : '';
                    $newTarget['in_longitude'] = !empty($detailsArr['in_longitude']) ? $detailsArr['in_longitude'] : '';
                    $newTarget['in_location'] = !empty($detailsArr['in_location']) ? $detailsArr['in_location'] : '';
                    $newTarget['break_description'] = !empty($detailsArr['break_description']) ? $detailsArr['break_description'] : '';
                    $newTarget['out_latitude'] = !empty($detailsArr['out_latitude']) ? $detailsArr['out_latitude'] : '';
                    $newTarget['out_longitude'] = !empty($detailsArr['out_longitude']) ? $detailsArr['out_longitude'] : '';
                    $newTarget['out_location'] = !empty($detailsArr['out_location']) ? $detailsArr['out_location'] : '';
                    $newTarget['total_break'] = $detailsArr['total_break'];
                    $newTarget['total_office_time'] = !empty($detailsArr['total_office_hour']) ? $detailsArr['total_office_hour'] : '';
                    $newTarget['total_break_time'] = !empty($detailsArr['total_break_hour']) ? $detailsArr['total_break_hour'] : '';
                    $newTarget['total_office_time_without_break'] = !empty($detailsArr['total_office_hour_without_break']) ? $detailsArr['total_office_hour_without_break'] : '';
                    $newTarget['user_id'] = !empty($value->user_id) ? $value->user_id : '';
                    $newTarget['user_name'] = !empty($user->name) ? $user->name : '';
                }
                $i++;
            }
        }

        return $newTarget;

    }


    public function monthWiseReport(Request $request){

        $rules = [
            'month' => 'required',
            'year' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }



        $targets = TimeSchedule::select(DB::raw("DATE(created_at) as day"))
            ->whereMonth('created_at', $request->month)
            ->whereYear('created_at', $request->year)
            ->get()
            ->groupBy('day');


        $presentLateCount = [];
        if($targets->isNotEmpty()){
            foreach($targets as $date => $target){
                $presentLateCount[] = $this->presentAndLateEmployee($date);
            }
        }

//dd($presentLateCount);
        $present = 0;
        $late = 0;
        if(!empty($presentLateCount)){
            foreach($presentLateCount as $dateWiseData){
                $present += $dateWiseData['present'];
                $late += $dateWiseData['late'];
            }
        }


        $users = TimeSchedule::distinct()->whereMonth('created_at', $request->month)
            ->whereYear('created_at', $request->year)->get('user_id');

        $finalArr = [];
        if($users->isNotEmpty()){
            foreach($users as $user){
                
                $monthWise = $this->monthWiseReportByUserId($user->user_id,$request->month,$request->year);
                
                $finalArr[] = $monthWise[0];
                //$late += $monthWise[1];
            }
        }

        if ( ! $request->has('pdf') ){

            //return view('print.month-wise-report', compact('finalArr', 'request','present','late'));

            $pdf = PDF::loadView('print.month-wise-report', compact('finalArr', 'request','present','late'))
                ->setPaper('a4', 'portrait')
                ->setOptions(['defaultFont' => 'sans-serif']);

            $fileName = "month_wise_report_" . date('d_m_Y_H_i_s').'.pdf';
            $path = 'uploads/pdf/' . $fileName;
            $pdf->save($path);

            return response()->json(
                [
                    'Status' => 'success',
                    'Message' => "Pdf Generated successfully!!",
                    'data' => url('uploads/pdf/' . $fileName),
                ]);
        } else {
            $resArr = [];
            foreach ($finalArr as $innerArr){
                if ( count($innerArr) > 0 ){
//                $resArr['user_name'] = $innerArr[0]['user_name'];
                    $total = 0;
                    foreach ($innerArr as $main){
                        $total += $main['total_late'];
                    }

                    array_push($resArr, [
                        'user_name' => $innerArr[0]['user_name'],
                        'total_present' => count($innerArr),
                        'total_late' => $total
                    ]);
                }
            }

            return response()->json(
                [
                    'Status' => 'success',
                    'Message' => "Data retrieved successfully",
                    'data' => $resArr,
                ]);
        }


        // return $pdf->stream("$fileName.pdf");
        // return $pdf->download("$fileName.pdf");






    }


    public function monthWiseReportByUserId($user_id, $month, $year){

        $targets = TimeSchedule::select('*', DB::raw("DATE(created_at) as day"))
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->where('user_id', $user_id)
            ->get()
            ->groupBy('day');

        $user = User::where('id',$user_id)->first();


        $newTarget = [];
        $i = 0;
        $presentLateCount = [];
        $totalLate = 0;
        if ($targets->isNotEmpty()) {
            foreach ($targets as $date => $targetArr) {
                $presentLateCount[] = $this->presentAndLateEmployee($date);
                foreach ($targetArr as $key => $value) {
                    //dd(Helper::monthly_report($value->user_id, $date));
                    $detailsArr = Helper::monthly_report($value->user_id, $date);
                    $newTarget[$i]['date'] = !empty($date) ? $date : '';
                    $newTarget[$i]['in_time'] = !empty($detailsArr['in_time']) ? $detailsArr['in_time'] : '';
                    $newTarget[$i]['out_time'] = !empty($detailsArr['out_time']) ? $detailsArr['out_time'] : '';
                    $newTarget[$i]['in_latitude'] = !empty($detailsArr['in_latitude']) ? $detailsArr['in_latitude'] : '';
                    $newTarget[$i]['in_longitude'] = !empty($detailsArr['in_longitude']) ? $detailsArr['in_longitude'] : '';
                    $newTarget[$i]['in_location'] = !empty($detailsArr['in_location']) ? $detailsArr['in_location'] : '';
                    $newTarget[$i]['break_description'] = !empty($detailsArr['break_description']) ? $detailsArr['break_description'] : '';
                    $newTarget[$i]['out_latitude'] = !empty($detailsArr['out_latitude']) ? $detailsArr['out_latitude'] : '';
                    $newTarget[$i]['out_longitude'] = !empty($detailsArr['out_longitude']) ? $detailsArr['out_longitude'] : '';
                    $newTarget[$i]['out_location'] = !empty($detailsArr['out_location']) ? $detailsArr['out_location'] : '';
                    $newTarget[$i]['total_break'] = $detailsArr['total_break'];
                    $newTarget[$i]['total_office_time'] = !empty($detailsArr['total_office_hour']) ? $detailsArr['total_office_hour'] : '';
                    $newTarget[$i]['total_break_time'] = !empty($detailsArr['total_break_hour']) ? $detailsArr['total_break_hour'] : '';
                    $newTarget[$i]['total_office_time_without_break'] = !empty($detailsArr['total_office_hour_without_break']) ? $detailsArr['total_office_hour_without_break'] : '';
                    $newTarget[$i]['user_id'] = !empty($value->user_id) ? $value->user_id : '';
                    $newTarget[$i]['user_name'] = !empty($user->name) ? $user->name : '';
                    $newTarget[$i]['total_late'] = $detailsArr['total_late'];
                    $totalLate += $detailsArr['total_late'];
                }
                $i++;
            }
        }

//dd($newTarget);

        return [$newTarget, $totalLate];
    }

    public function presentAndLateEmployee($withinTime){
      
        $endTime = date('H:i:s', strtotime(Helper::officeStartTime()));
      
        $attendEmployeeInPerfectTime = TimeSchedule::where('status',1)
            ->whereDate('created_at',$withinTime)
            ->where('created_at','<=',$withinTime.' ' . $endTime)->distinct()->get('user_id')->count();
//dd($endTime, $withinTime, $attendEmployeeInPerfectTime);

        $numberOfEmployeeThisDay = TimeSchedule::where('status',1)->whereDate('created_at',$withinTime)->distinct()->get('user_id')->count();
//dd($numberOfEmployeeThisDay, $attendEmployeeInPerfectTime);
        $attendEmployeeInWithoutPerfectTime = $numberOfEmployeeThisDay - $attendEmployeeInPerfectTime;

        return [
            'date' => $withinTime,
            'present' => $numberOfEmployeeThisDay,
            'late' => $attendEmployeeInWithoutPerfectTime,
        ];
        // echo "<pre>";print_r($date);
    }
}
