<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\BankAccount;
use App\Models\BankPending;
use App\Models\BankTransaction;
use App\Models\FundTransfer;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Setting;
use App\Models\User;

class HomeController extends Controller {

    public function __construct() {
        $this->middleware('auth:api', ['except' => ['rulesAndRegulation', 'termsAndCondition', 'schedule']]);
    }

    public function index(Request $request) {
        $user = User::findOrFail($request->user_id);
        $setting = Setting::where('id', '1')->first();

        if($user->break_status == '0'){
           $break_status = '0';
        }elseif($user->break_status == '1'){
            $break_status = '1';
        }else{
            $break_status = '';
        }

        $newArr = [
            'idel_status' => !empty($user->idel_status) ? $user->idel_status : '',
            'break_status' => $break_status,
            'company_name' => !empty($setting->company_name) ? $setting->company_name : '',
            'company_address' => !empty($setting->company_address) ? $setting->company_address : '',
            'office_time' => !empty($setting->office_time) ? $setting->office_time : '',
        ];
        $statusArr = ['1'=>'start office','2'=>'End office','3'=>'Break start','4'=>'Break end'];
        $breakStatusArr = ['1'=>'Start bresk','0'=>'End break'];
        return response()->json(['response' => 'success', 'data' => $newArr,'statusArr'=>$statusArr,'breakStatusArr'=>$breakStatusArr]);
    }

    public function rulesAndRegulation() {
        $setting = Setting::where('id', '1')->select('rules_and_regulations')->first();
        if ($setting->rules_and_regulations) {
            return response()->json(['response' => 'success', 'data' => $setting->rules_and_regulations]);
        } else {
            return response()->json(['response' => 'error', 'message' => 'No data available']);
        }
    }

    public function termsAndCondition() {
        $setting = Setting::where('id', '1')->select('terms_and_condition')->first();
        if ($setting->terms_and_condition) {
            return response()->json(['response' => 'success', 'data' => $setting->terms_and_condition]);
        } else {
            return response()->json(['response' => 'error', 'message' => 'No data available']);
        }
    }

    public function allUser(Request $request){
        $users = User::with('role')->orderBy('id','desc')->get();
        return response()->json(['response' => 'success', 'data' => $users]);
    }


    public function monthlySummery($date)
    {
        $bankTotal=0;
        $cashTotal = 0;
        $incomeTotal = 0;
        $expenseTotal = 0;

        $dateFormate = Carbon::createFromFormat('Y-m', $date);
        $bankAccount = BankAccount::all();

        foreach ($bankAccount as $bank){
            $bankTotal += $bank->current_amount;
        }

        $transactionsMonth = Transaction::with('creator.role')->whereMonth('created_at', '=', $dateFormate->month)->whereYear('created_at', '=', $dateFormate->year)->get();

        foreach ($transactionsMonth as $transaction){
            if ( $transaction->payment_type == "cash" ){
                if ($transaction->tran_type == "income") {
                    $incomeTotal += $transaction->tran_amount;
                    $cashTotal += $transaction->tran_amount;
                } else {
                    if (request()->user('api')->role->role == 'admin'){
                        if( $transaction->creator->role && $transaction->creator->role->role != 'manager' ){
                            $expenseTotal += (double)$transaction->tran_amount;
                        }
                    } else {
                        $expenseTotal += (double)$transaction->tran_amount;
                    }
                }
            } else {
                $pending = BankPending::where([
                    'bank_account_id' => $transaction->bank_account_id,
                    'cheque_status' => 1,
                    'transaction_id' => $transaction->id
                ])->first();

                if ( $pending ){
                    if ($pending->tran_type == "Cash In") {
                        $incomeTotal += (double)$pending->amount;
                    } else {
                        if (request()->user('api')->role->role == 'admin'){
                            if( $transaction->creator->role && $transaction->creator->role->role != 'manager' ){
                                $expenseTotal += (double)$pending->amount;
                            }
                        } else {
                            $expenseTotal += (double)$pending->amount;
                        }
                    }
                }
            }
        }


        return response()->json([
            "Status" => "Success",
            "Message" => "Data Retrieved Successfully",
            "Data" => [
                'presentBalance' => $bankTotal + $cashTotal,
                'bank' => $bankTotal,
                'cash' => $cashTotal,
                'income' => $incomeTotal,
                'expense' => $expenseTotal
            ]
        ]);
    }


    public function managerExpense(Request $request)
    {
        if ( $request->user('api')->role_id == 2 ){
            $transactionTotal = Transaction::where([
                'tran_type' => 'expense',
                'created_by' => $request->user('api')->id
            ])->sum('tran_amount');

            $fundT0tal = FundTransfer::where('transfer_to', $request->user('api')->id)->sum('amount');

            return response()->json([
                'Status' => 'Success',
                'Message' => 'Data retrieved successfully',
                'Data' => [
                    'transaction_total' => (int)$transactionTotal,
                    'fund_total' => (int)$fundT0tal,
                    'balance_total' => (int)($fundT0tal - $transactionTotal)
                ]
            ]);
        }
        return response()->json([
            'Status' => 'Error',
            'Message' => 'No data found'
        ]);
    }


    public function schedule()
    {
        Helper::updateUserStatus();
    }
}
