<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankPending extends Model
{
    protected $guarded = ['_token'];

    protected $table = 'bank_pending';

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
    
    public function bank()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id', 'id');
    }
}
