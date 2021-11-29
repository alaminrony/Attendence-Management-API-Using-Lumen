<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $table = 'transactions';

    protected $guarded = ['_token', 'file'];

    public function pendings()
    {
        return $this->hasMany(BankPending::class);
    }


    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}
