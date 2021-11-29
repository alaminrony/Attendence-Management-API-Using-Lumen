<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundTransfer extends Model
{
    protected $guarded = ['_token'];

    protected $table = 'fund_transfer';
}
