<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Helpers\ValidationHelper;
use App\Models\BankAccount;
use App\Models\BankPending;
use App\Models\BankTransaction;
use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Models\Transaction;
use Illuminate\Http\Response;
use Illuminate\Http\Request;

class EditController extends Controller
{
    use ValidationHelper;

    public function editBankTransaction( $id, Request $request)
    {
        $bankTransaction = BankTransaction::find($id);

        if ( ! $bankTransaction ) return response()->json([
            'Status' => 'Error',
            'Message' => 'Transaction Not found'
        ], Response::HTTP_NOT_FOUND);

        $bankAccount = BankAccount::where('id', $bankTransaction->bank_account_id )->first();

        if ( ! $bankAccount ) return response()->json([
            'Status' => 'Error',
            'Message' => 'Bank Not found'
        ], Response::HTTP_NOT_FOUND);

        $previousTransactionAmount = $bankTransaction->tran_amount;
        $previousAmount = $bankAccount->current_amount;

        if ( $request->tran_amount != null) $bankTransaction->tran_amount = $request->tran_amount;
        if ( $request->tran_note != null) $bankTransaction->tran_note = $request->tran_note;

        if ( $bankTransaction->save() ){
            $bankAccount =  BankAccount::where('id', $bankTransaction->bank_account_id );
            if ( $request->tran_amount != null ){
                $new = 0.0;

                if ( $bankTransaction->tran_type == "Cash In") $new = (((double)$previousAmount - (double)$previousTransactionAmount) + (double)$request->tran_amount);
                else $new = (((double)$previousAmount + (double)$previousTransactionAmount) - (double)$request->tran_amount);
                $bankAccount->update(['current_amount' => $new]);
            }

            return response()->json([
                'Status' => 'Success',
                'Message' => 'Bank Transaction Edited'
            ], Response::HTTP_OK);
        }
        return Helper::returnInternalServerError();
    }


    public function editTransaction(Request $request, $id)
    {
        $transaction = Transaction::find($id);

        if ( ! $transaction ) return response()->json([
            "Status" => "Error",
            "Message" => "Not data found"
        ]);

        $totalCahsInHand = Helper::checkTotalAmountInTransaction();

        if ( $request->payment_type != 'cheque' && $totalCahsInHand < $request->tran_amount ){
            return response()->json([
                'Status' => 'Error',
                'Message' => 'Insufficient Balance'
            ]);
        }
        elseif ( $request->payment_type == 'cheque' ){
            $bankBalance = Helper::checkBankBalance($request->bank_account_id);

            if( $bankBalance != null && $bankBalance < $request->tran_amount ){
                return response()->json([
                    'Status' => 'Error',
                    'Message' => 'Insufficient Balance'
                ]);
            }
        }

        if ( $request->tran_type != null ) $transaction->tran_type = $request->tran_type;
        if ( $request->header_id != null ) $transaction->header_id = $request->header_id;
        if ( $request->tran_amount != null ) $transaction->tran_amount = $request->tran_amount;
        if ( $request->tran_date != null ) $transaction->tran_date = $request->tran_date;
        if ( $request->payment_type != null ) $transaction->payment_type = $request->payment_type;
        if ( $request->cheque_no != null ) $transaction->cheque_no = $request->cheque_no;
        if ( $request->bank_account_id != null ) $transaction->bank_account_id = $request->bank_account_id;
        if ( $request->tran_note != null ) $transaction->tran_note = $request->tran_note;
        if ( $request->reference != null ) $transaction->tran_note = $request->reference;
        if ( $request->created_by != null ) $transaction->created_by = $request->created_by;

        if ( $request->hasFile('file') ) $transaction->attachment = Helper::upload($request, 'file');

        if ( $request->payment_type == "cheque" || $transaction->payment_type == "cheque" ){
            $pendingTransaction = BankPending::where('transaction_id', $id)->first();

            if ( ! $pendingTransaction ) return response()->json([
                "Status" => "Error",
                "Message" => "Not data found"
            ]);

            if ( $request->tran_amount != null ) $pendingTransaction->amount = $request->tran_amount;
            if ( $request->bank_account_id != null ) $pendingTransaction->bank_account_id = $request->bank_account_id;
            if ( $request->cheque_no != null ) $pendingTransaction->cheque_no = $request->cheque_no;
            if ( $request->insert_date != null ) $pendingTransaction->insert_date = $request->insert_date;
            if ( $request->tran_type != null ) $pendingTransaction->tran_type = ( $request->tran_type == "income" ||  $transaction->tran_type == "income") ? 'Cash In' : 'Cash Out' ;
            if ( $request->cheque_status != null ) $pendingTransaction->cheque_status = $request->cheque_status;

            if (  $pendingTransaction->save() && $transaction->save()){
                if ( $request->payment_type == "cheque" || $transaction->payment_type == "cheque" ){
                    if ( $request->cheque_status != 1 ){
                        $transaction = Transaction::find($id);
                        $pendingTransaction = BankPending::where('transaction_id', $id)->first();

                        $bankTransaction = BankTransaction::where('transaction_id', $id)->first();

                        if ( ! $bankTransaction ) return response()->json([
                            "Status" => "Error",
                            "Message" => "Not data found"
                        ]);

                        if ( ! $bankTransaction ){
                            $bankTransaction = new BankTransaction();
                        }

                        $bankTransaction->tran_type = ( $transaction->tran_type == "income") ? 'Cash In' : 'Cash Out';
                        $bankTransaction->tran_amount = $pendingTransaction->amount;
                        $bankTransaction->tran_date = $pendingTransaction->insert_date;
                        $bankTransaction->bank_account_id = ($request->bank_account_id == null) ? $pendingTransaction->bank_account_id : $request->bank_account_id;
                        $bankTransaction->tran_note = $transaction->tran_note;
                        $bankTransaction->created_by = $transaction->created_by;
                        $bankTransaction->transaction_id = $id;

                        if ( $bankTransaction->save() ){
                            $bankAccount = BankAccount::where('id', $transaction->bank_account_id )->first();

                            if ( $bankAccount ){
                                if ( $pendingTransaction->tran_type == "Cash In" ) $bankAccount->increment('current_amount', $pendingTransaction->amount);
                                else $bankAccount->decrement('current_amount', $pendingTransaction->amount);
                            } else {
                                BankAccount::create([
                                    'account_no' => ($request->bank_account_id == null) ? $pendingTransaction->bank_account_id : $request->bank_account_id,
                                    'current_amount' => ($request->tran_amount == null) ? $pendingTransaction->amount : $request->tran_amount
                                ]);
                            }

                        }
                    } else {
                        return response()->json([
                            'Status' => 'Error',
                            'Message' => 'Active Transaction can not be deleted'
                        ], Response::HTTP_UNAUTHORIZED);
                    }
                }
            }
            return response()->json([
                'Status' => 'Success',
                'Message' => 'Transaction Edited'
            ], Response::HTTP_OK);
        }
        $transaction->save();

        return response()->json([
            'Status' => 'Success',
            'Message' => 'Transaction Edited'
        ], Response::HTTP_OK);
    }

    public function editBankAccount(Request $request, $bankAccount)
    {
        $bank = BankAccount::find($bankAccount);

        if ( ! $bank ) return response()->json([
            'Status' => 'Error',
            'Message' => 'Bank not found'
        ], Response::HTTP_NOT_FOUND);

        if ( $request->bank_name != null ) $bank->bank_name = $request->bank_name;
        if ( $request->ac_holder_name != null ) $bank->ac_holder_name = $request->ac_holder_name;
        if ( $request->account_no != null ) $bank->account_no = $request->account_no;
        if ( $request->current_amount != null ) $bank->current_amount = $request->current_amount ;
        if ( $request->status != null ) $bank->status = $request->status;

        if ( $bank->save() ){
            return response()->json([
                'Status' => 'Success',
                'Message' => "Bank Account Edited"
            ]);
        }

        return response()->json([
            'Status' => 'Error',
            'Message' => "Something Went Wrong"
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }


    public function editHead($type, $id, Request $request)
    {
        $headvalidator = $this->editHeadValidation($request);

        if ( $headvalidator->fails() ) Helper::returnValidationErrorResponse($headvalidator);

        if( $type == "income" ){
            $income = IncomeCategory::find($id);
            $income->update(['income_head' => $request->head]);
        } else {
            $income = ExpenseCategory::find($id);
            $income->update(['expense_head' => $request->head]);
        }

        return response()->json([
            "Status" => "Success",
            "Message" => ucfirst($type) . " Head Edited"
        ]);
    }


    public function deleteHead($type, $id)
    {
        $condition = [];
        $condition['header_id'] = (int)$id;

        if( $type == "income" ){
            $condition['tran_type'] = 'income';
        } else {
            $condition['tran_type'] = 'expense';
        }


        $transactions = Transaction::where($condition)->get();

        if ( count($transactions) > 0 ) return response()->json([
            "Status" => "Error",
            "Message" => "Delete Transactions before"
        ], Response::HTTP_UNAUTHORIZED);

        if( $type == "income" ){
            IncomeCategory::find($id)->delete();
        } else {
            ExpenseCategory::find($id)->delete();
        }

        return response()->json([
            "Status" => "Success",
            "Message" => ucfirst($type). " Delete"
        ], Response::HTTP_OK);
    }
}
