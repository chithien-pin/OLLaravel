<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    use HasFactory;
    public $table = "user_notification";
    
    protected $fillable = [
        'user_id', 'my_user_id', 'item_id', 'type', 'title', 'message'
    ];

    public function user()
    {
        return $this->hasOne(Users::class, "id", 'my_user_id');
    }
}
