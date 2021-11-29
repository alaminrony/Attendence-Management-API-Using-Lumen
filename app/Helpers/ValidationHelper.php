<?php


namespace App\Helpers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

trait ValidationHelper
{
    private function incomeCategory(Request $request)
    {
        return Validator::make($request->all(), [
            'income_head' => 'required',
            'parent_id' => 'required',
        ]);
    }


    private function expenseCategory(Request $request)
    {
        return Validator::make($request->all(), [
            'expense_head' => 'required',
            'parent_id' => 'required',
        ]);
    }


    private function bankAccountValidation(Request $request){
        return Validator::make($request->all(), [
            'bank_name' => 'required',
            'ac_holder_name' => 'required',
            'account_no' => 'required|int',
            'current_amount' => 'required'
        ]);
    }


    private function transactionValidationFirstPart(Request $request)
    {
        return Validator::make($request->all(), [
            'tran_type' => 'required',
            'header_id' => 'required|int',
            'tran_amount' => 'required',
            'payment_type' => 'required',
            'created_by' => 'required|int',
            'tran_date' => 'required|date_format:Y-m-d H:i:s',
        ]);
    }


    private function transactionValidationSecondPart(Request $request)
    {
        return Validator::make($request->all(), [
            'cheque_no' => 'required',
            'bank_account_id' => 'required|int',
            'tran_note' => 'nullable',
            'attachment' => 'nullable',
            'reference' => 'nullable'
        ]);
    }

    private function bankTransactionValidation(Request $request)
    {
        return Validator::make($request->all(), [
            'tran_type' => 'required',
            'tran_amount' => 'required|int',
            'tran_date' => 'required|date_format:Y-m-d H:i:s',
            'bank_account_id' => 'required|exists:bank_account,id',
            'tran_note' => 'nullable',
            'created_by' => 'nullable',
        ]);
    }


    private function editHeadValidation(Request $request)
    {
        return Validator::make($request->all(), [
            'head' => 'required'
        ]);
    }
}
