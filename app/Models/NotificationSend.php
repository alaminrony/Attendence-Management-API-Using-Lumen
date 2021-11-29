<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationSend extends Model
{
    use HasFactory;

    protected $guarded = ['_token'];

    public function pushnotification()
    {
        return $this->belongsTo(PushNotification::class, 'notification_id');
    }
}
