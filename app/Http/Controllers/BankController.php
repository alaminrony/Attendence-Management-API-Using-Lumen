<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Helpers\ValidationHelper;
use App\Models\BankAccount;
use App\Models\BankPending;
use App\Models\BankTransaction;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Http\Request;

class BankController extends Controller
{
    use ValidationHelper;

    public function activeBankListing()
    {
        $banks = BankAccount::where('status', 1)->get();

        return response()->json([
            'Status' => 'Success',
            'Message' => 'Data Retrieved Successfully',
            'Data' => $banks
        ]);
    }


    public function pendingBank()
    {
        $pendings = BankPending::with(['transaction'])->where('cheque_status', 0)->paginate(10);

        return response()->json([
            'Status' => 'Success',
            'Message' => 'Data Retrieved Successfully',
            'Data' => $this->addBankAccount($pendings)
        ]);
    }


    public function activeBank()
    {
        $pendings = BankPending::with('transaction')->where('cheque_status', 1)->paginate(10);


        return response()->json([
            'Status' => 'Success',
            'Message' => 'Data Retrieved Successfully',
            'Data' => $this->addBankAccount($pendings)
        ]);
    }

    private function addBankAccount($pendings )
    {
        foreach ($pendings as $pending){
            $pending->bank_account = BankAccount::where('id', $pending->bank_account_id)->first();
        }

        return $pendings;
    }

    private function addPending( $transactions ){
        foreach ($transactions as $pending){
            $pending->bank_pending = BankPending::where('id', $pending->bank_account_id)->first();
        }

        return $transactions;
    }


    public function createBankTransaction(Request $request)
    {
        $bankTransactionValidation = $this->bankTransactionValidation($request);

        if ( $bankTransactionValidation->fails() ) return Helper::returnValidationErrorResponse($bankTransactionValidation);

        if ( $request->tran_type == 'Cash Out' && Helper::checkBankBalance($request->bank_account_id) < $request->tran_amount ) return response()->json([
            'Status' => 'Error',
            'Message' => 'Insufficient Balance'
        ]);

        if ( BankTransaction::create($request->all()) ){

            if ( $request->tran_type == "Cash In" ) BankAccount::where('id', $request->bank_account_id)->increment('current_amount', $request->tran_amount);
            else BankAccount::where('id', $request->bank_account_id)->decrement('current_amount', $request->tran_amount);

            return response()->json([
                'Status' => 'Success',
                'Message' => 'Bank Transaction Created Successfully'
            ], Response::HTTP_OK);
        }

        return Helper::returnInternalServerError();
    }


    public function acceptPendingTransaction($id, Request $request)
    {
        $bankPending = BankPending::find($id);

       if ($bankPending->cheque_status == 0) {
           $bankPending->update(['cheque_status' => 1]);

           $bankTransaction = BankTransaction::where('transaction_id', $bankPending->transaction_id)->first();


           if ( ! $bankTransaction ){
               $bankTransaction = new BankTransaction();
           }

           $bankTransaction->tran_type = $bankPending->tran_type;
           $bankTransaction->tran_amount = $bankPending->amount;
           $bankTransaction->tran_date = $bankPending->insert_date;
           $bankTransaction->bank_account_id = $bankPending->bank_account_id;
           $bankTransaction->created_by = Transaction::find($bankPending->transaction_id)->created_by;
           $bankTransaction->transaction_id = $bankPending->transaction_id;

           $bankTransaction->save();

           $bankAccount = BankAccount::where('id', $bankPending->bank_account_id )->first();

           if ( $bankAccount ){
               if ( $bankPending->tran_type == "Cash In" ) $bankAccount->increment('current_amount', $bankPending->amount);
               else $bankAccount->decrement('current_amount', $bankPending->amount);
           }

           return response()->json([
               'Status' => 'Success',
               'Message' => 'Bank Pending Status Updated'
           ], Response::HTTP_OK);
       }
       return Helper::returnInternalServerError();
    }

    public function deleteTransaction($id, Request $request)
    {
        $transaction = Transaction::find($id);

        $delete = false;
        if ( $transaction->payment_type == "cheque" ){
            $pendings = BankPending::where('transaction_id', $id)->first();

            if($pendings){
                if ( $pendings->cheque_status != 1 ) {
                    $delete = true;
                    $bank = BankAccount::where('id', $pendings->bank_account_id);
                    if ( $pendings->tran_type != "Cash In" ) $bank->increment('current_amount', $pendings->amount);
                    else $bank->decrement('current_amount', $pendings->amount);
                    $pendings->delete();
                }
            }
        }

        if ( $delete ) {
            $transaction->delete();

            return response()->json([
                'Status' => 'Success',
                'Message' => 'Transaction Deleted'
            ], Response::HTTP_OK);
        }

        return response()->json([
            'Status' => 'Error',
            'Message' => 'Transaction can not be deleted'
        ], Response::HTTP_UNAUTHORIZED);
    }


    public function deleteBankTransaction($id)
    {
        $bank_transaction = BankTransaction::find($id);

        if ( $bank_transaction->tran_type != "Cash In" ) BankAccount::where('account_no', $bank_transaction->bank_account_id)->increment('current_amount', $bank_transaction->tran_amount);
        else BankAccount::where('account_no', $bank_transaction->bank_account_id)->decrement('current_amount', $bank_transaction->tran_amount);

        $bank_transaction->delete();

        return response()->json([
            'Status' => 'Success',
            'Message' => 'Bank Transaction Deleted'
        ], Response::HTTP_OK);
    }


    public function transactionByAccount($id)
    {
        $bankAccount = BankAccount::find($id);

        $bankTransaction = BankTransaction::where('bank_account_id', $bankAccount->id)->paginate(10);

        return response()->json([
            'Status' => 'Success',
            'Message' => 'Data Retrieved Successfully',
            'Data' => $bankTransaction
        ], Response::HTTP_OK);
    }

    public function transactionByDate($day, $date, Request $request)
    {
        $pendings = null;

        if ( $day == "day" ) {
            $dateFormate = Carbon::createFromFormat('Y-m-d', $date);
            $pendings = Transaction::whereDay('created_at', '=', $dateFormate->day);
        } else {
            $dateFormate = Carbon::createFromFormat('Y-m', $date);
            $pendings = Transaction::whereMonth('created_at', '=', $dateFormate->month);

            if ( $request->user('api')->role_id != 3 ){
                $pendings->where('created_by', $request->user('api')->id);
            }
        }

        $pendings->whereYear('created_at', '=', $dateFormate->year)->get();


        return response()->json([
            'Status' => 'Success',
            'Message' => 'Data Retrieved Successfully',
            'Data' => $this->addPending($pendings->get())
        ]);
    }


    public function singleBankAccount($id)
    {
        return response()->json([
            'Status' => "Success",
            "Message" => "Data Retrieved successfully",
            "Data" => BankAccount::find($id)
        ]);
    }

    public function deleteBankAccount($id)
    {
        $bankAccount = BankAccount::find($id);

        $bangTransaction = BankTransaction::where('bank_account_id', $bankAccount->account_no)->first();
        $transaction = Transaction::where('bank_account_id', $bankAccount->account_no)->first();
        $pending = BankPending::where('bank_account_id', $bankAccount->account_no)->first();

        $message = " ";

        $needDelete = true;

        if ( $bangTransaction ) {
            $message .= " Bank Transaction, ";
            $needDelete = false;
        }
        if ( $transaction ) {
            $needDelete = false; $message .= " Transaction,";
        }
        if ( $pending ) {
            $message .= " Pending Transaction";
            $needDelete = false;
        }

        if ( $needDelete ) {
            $bankAccount->delete();
        } else {
            return response()->json([
                "Status" => "Warning",
                "Message" => $message . " has Data for this account"
            ]);
        }

        return response()->json([
            "Status" => "Success",
            "Message" => "Bank Account Deleted and "
        ]);
    }

}
