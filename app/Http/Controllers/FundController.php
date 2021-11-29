<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\BankAccount;
use App\Models\BankPending;
use App\Models\FundTransfer;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class FundController extends Controller
{
    public function store(Request $request)
    {
        $validate = $this->checkValidation($request);

        if ( $validate->fails() ) return Helper::returnValidationErrorResponse($validate);

        $fundTransfer = new FundTransfer();

        $fundTransfer->transfer_from = $request->user('api')->id;
        $fundTransfer->transfer_to = $request->transfer_to;
        $fundTransfer->amount = $request->amount;
        $fundTransfer->note = $request->note;

        if ( $request->has('bank_id') ) {

            if (Helper::checkBankBalance( $request->bank_id ) < $request->amount){
                return response()->json([
                    'Status' => 'Error',
                    'Message' => 'Not enough balance'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $secondValidation = $this->bankTransferValidation($request);

            if ( $secondValidation->fails() ) return Helper::returnValidationErrorResponse($secondValidation);

            $fundTransfer->bank_id = $request->bank_id;
            $fundTransfer->cheque_no = $request->check_no;
            $fundTransfer->paymrnt_type = ( $request->bank_id ) ? 'cheque' : 'cash';
        } else {
            if ( Helper::checkTotalAmountInTransaction() >= $request->amount ) {
                $fundTransfer->paymrnt_type = 'bank transfer';
            } else {
                return response()->json([
                    'Status' => 'Error',
                    'Message' => 'Insufficient Balance'
                ]);
            }
        }

        if ( $fundTransfer->save() ){
            if( $request->has('bank_id') && $this->createBankPending($request, $fundTransfer) == false ) return response()->json([
                'Status' => 'Error',
                'Message' => 'Bank Account Not Found'
            ], Response::HTTP_NOT_FOUND);

            elseif(!$request->has('bank_id')) $this->createTransaction($request, $fundTransfer);

            return response()->json([
                'Status' => 'Success',
                'Message' => 'Fund Transfer Created Successfully'
            ]);
        }


        return Helper::returnInternalServerError();
    }


    public function edit(Request $request, $id)
    {
        $fundTransfer = FundTransfer::find($id);

        if ( empty($fundTransfer) ) return response()->json([
            'Status' => 'Error',
            'Message' => 'Data Not Found'
        ], Response::HTTP_NOT_FOUND);


        if ( $this->checkForActiveFundChange($id, $request) ){
            if ( $fundTransfer->update($request->except('_token')) ) return response()->json([
                'Status' => 'Success',
                'Message' => 'Updated Successfully',
            ], Response::HTTP_OK);

            return Helper::returnInternalServerError();
        } else {
            return response()->json([
                'Status' => 'Error',
                'Message' => 'Can not update Fund',
            ], Response::HTTP_UNAUTHORIZED);
        }
    }


    public function destroy($id, Request $request)
    {
        if ( $request->user('api')->role_id != 3 ) return response()->json([
            'Status' => 'Error',
            'Message' => 'Unauthorised Request'
        ], Response::HTTP_UNAUTHORIZED);

        $fundTranscation = FundTransfer::find($id);

        if ( empty($fundTranscation) ) return response()->json([
            'Status' => 'Error',
            'Message' => 'Fund Not Found'
        ], Response::HTTP_NOT_FOUND);

        if ( $fundTranscation->delete() ) return response()->json([
            'Status' => 'Success',
            'Message' => 'Fund Deleted'
        ], Response::HTTP_OK);

        return Helper::returnInternalServerError();
    }


    public function monthlyReport(Request $request, $date)
    {
        $dateFormat = Carbon::createFromFormat('Y-m', $date);
        $fundTransaction = FundTransfer::whereMonth('created_at', '=',$dateFormat->month);

        if ( $request->user('api')->role_id != 3 ){
            $fundTransaction->where('transfer_to', $request->user('api')->id);
        }

        return response()->json([
            'Status' => 'Success',
            'Message' => 'Data Retrieved Successfully',
            'Data' => $fundTransaction->get()
        ]);
    }


    private function checkValidation(Request $request): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($request->all(), [
            'transfer_to' => 'required',
            'amount' => 'required'
        ]);
    }


    private function bankTransferValidation(Request $request): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($request->all(), [
            'bank_id' => 'required',
            'check_no' => 'required'
        ]);
    }

    private function createBankPending(Request $request, $fundTransfer)
    {
        $bankAccount = BankAccount::find($request->bank_id);

        if ( empty($bankAccount) ) return false;

        $data = [
            'bank_account_id' => $bankAccount->id,
            'cheque_no' => $request->check_no,
            'insert_date' => date('Y-m-d'),
            'tran_type' => 'Cash Out',
            'amount' => $request->amount,
            'transaction_id' => $this->createTransaction($request, $fundTransfer, $bankAccount)
        ];

        return BankPending::create($data);
    }


    private function createTransaction(Request $request, $fundTransfer, $bankAccount = null)
    {
        if ( ! $request->has('bank_id') ) $request->request->add(['paymrnt_type' => 'cash']);
        else $request->request->add(['paymrnt_type' => 'cheque']);

        $data = [
            'tran_type' => 'expense',
            'header_id' => 0,
            'tran_amount' => $request->amount,
            'tran_date' => date('Y-m-d H:i:s'),
            'payment_type' => $request->paymrnt_type,
            'cheque_no'=> $request->check_no,
            'bank_account_id' => ( $bankAccount != null ) ? $bankAccount->id : null,
            'created_by' => $request->user('api')->id,
            'fund_transfer_id' => $fundTransfer->id,
        ];

        return Transaction::create($data)->id;
    }

    private function checkBankBalance($bank_id)
    {
        $bank = BankAccount::find($bank_id);

        return ($bank) ? $bank->current_amount: null;
    }


    private function checkForActiveFundChange($id, $request)
    {
        $fund = FundTransfer::find($id);

        if ( $fund ){
            if ( $fund->paymrnt_type == "cheque" ){
                $bank = BankAccount::find($fund->bank_id);

                if ( $bank ){
                    $transaction = Transaction::where([
                        'fund_transfer_id' => $id
                    ])->first();

                    if ( $transaction ){
                        $pending = BankPending::where('transaction_id', $transaction->id);
                        $st = $pending;

                        if ( $st->first()->cheque_status != 1 ){
                            $pending->update(['amount' => $request->amount]);
                            $transaction->update(['tran_amount' => $request->amount]);
                        } else return false;
                    }
                }
            } else {
                $transaction = Transaction::where('fund_transfer_id', $id)->first();

                $transaction->tran_amount = $request->amount;
                $transaction->save();
            }
        }
        return true;
    }
}
