<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppData extends Model
{
    use HasFactory;
    public $table = "appdata";
    
    protected $fillable = [
        'app_name',
        'currency',
        'min_threshold',
        'coin_rate',
        'min_user_live',
        'max_minute_live',
        'live_watching_price',
        'live_chat_price',
        'swipe_limit'
    ];

    /**
     * Get the daily swipe limit for normal users
     *
     * @return int
     */
    public function getSwipeLimit()
    {
        return $this->swipe_limit ?? 50;
    }
}
