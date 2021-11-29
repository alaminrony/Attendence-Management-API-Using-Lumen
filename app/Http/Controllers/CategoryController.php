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
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    use ValidationHelper;

    public function addCategory(Request $request, $type)
    {
        $validation = null;
        if ( $type == 'income' ){
            $validation = $this->incomeCategory($request);
        } else {
            $validation = $this->expenseCategory($request);
        }

        if ( $validation->fails() ){
            return Helper::returnValidationErrorResponse($validation);
        }

        if ( $type == 'income' ){
            $lastID = IncomeCategory::orderBy('id', 'desc')->first();

            if ( ! empty($lastID) ){
                $request->request->add(['head_code' => ((int)$lastID->head_code + 1)]);
            } else {
                $request->request->add(['head_code' => 1000]);
            }

            IncomeCategory::create($request->all());
        } else {
            $lastID = ExpenseCategory::orderBy('id', 'desc')->first();

            if ( ! empty($lastID) ){
                $request->request->add(['head_code' => ((int)$lastID->head_code + 1)]);
            } else {
                $request->request->add(['head_code' => 3000]);
            }

            ExpenseCategory::create($request->all());
        }

        return response()->json([
            'Status' => 'Success',
            'Message' => ucfirst($type) . " Category Created Successfully "
        ], Response::HTTP_OK);
    }


    public function createBankAccount(Request $request)
    {
        $validator = $this->bankAccountValidation($request);

        if ( $validator->fails() ) return Helper::returnValidationErrorResponse($validator);

        $bankAccount = BankAccount::create($request->all());

        if ($bankAccount) {
            BankTransaction::create([
                'tran_type'=> "Cash In",
                'tran_amount' => $request->current_amount,
                'tran_date' => date('Y-m-d H:i:s'),
                'bank_account_id' => Helper::getBankAccountId($bankAccount->account_no),
                'tran_note' => 'Opening Balance',
                'created_by' => $request->user('api')->id
            ]);

            return response()->json([
                'Status' => 'Success',
                'Message' => "Bank Account Created Successfully "
            ], Response::HTTP_OK);
        }

        return Helper::returnInternalServerError();
    }


    public function createTransaction(Request $request)
    {
        $validationFirstPart = $this->transactionValidationFirstPart($request);

        if ( $validationFirstPart->fails() ) return Helper::returnValidationErrorResponse($validationFirstPart);

        if ( $request->payment_type == 'cheque' ){
            $validationSecondPart = $this->transactionValidationSecondPart($request);

            if ( $validationSecondPart->fails() ) return Helper::returnValidationErrorResponse($validationSecondPart);
        }

        $totalCahsInHand = Helper::checkTotalAmountInTransaction();

        if ($request->tran_type != 'income'){
            if ( $request->payment_type != 'cheque' && $totalCahsInHand != null && $totalCahsInHand < $request->tran_amount ){
                return response()->json([
                    'Status' => 'Success',
                    'Message' => 'insufficient Balance'
                ]);
            }
            elseif ( $request->payment_type == 'cheque' ){
                $bankBalance = Helper::checkBankBalance($request->bank_account_id);

                if( $bankBalance != null && $bankBalance < $request->tran_amount ){
                    return response()->json([
                        'Status' => 'Success',
                        'Message' => 'insufficient Balance'
                    ]);
                }
            }
        }

        if ( $request->hasFile('file') ){
            $imagePath = Helper::upload($request, 'file');

            $request->request->add([
                'attachment' => $imagePath
            ]);
        }

        $transaction = Transaction::create($request->all());
        if ( $transaction ) {

            if ( $request->payment_type == 'cheque' ){

                BankPending::create([
                    'amount' => $request->tran_amount,
                    'bank_account_id' => $request->bank_account_id,
                    'cheque_no' => $request->cheque_no,
                    'insert_date' => date('Y-m-d'),
                    'tran_type' => ($request->tran_type == 'income') ? 'Cash In' : 'Cash Out',
                    'transaction_id' => $transaction->id
                ]);
            }

            return response()->json([
                'Status' => 'Success',
                'Message' => "Transaction Created Successfully "
            ], Response::HTTP_OK);
        }

        return Helper::returnInternalServerError();
    }

    public function getCategory($type)
    {
        $data = null;
        if ( $type == "income" ) $data = IncomeCategory::all();
        else $data = ExpenseCategory::all();

        return response()->json([
            'Status' => 'Success',
            'Message' => "Data Retrieved Successfully",
            'Data' => $data
        ], Response::HTTP_OK);
    }
}
