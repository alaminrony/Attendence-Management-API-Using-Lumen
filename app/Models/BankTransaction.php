<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankTransaction extends Model
{
    protected $guarded = ['_token'];

    protected $table = 'bank_transaction';
}
