<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushNotification extends Model
{
    protected $guarded = ['_token'];


    public function notification()
    {
        return $this->hasMany(NotificationSend::class, 'notification_id');
    }
}
