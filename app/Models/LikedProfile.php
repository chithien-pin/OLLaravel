<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LikedProfile extends Model
{
    use HasFactory;
    public $table = "like_profiles";
    
    protected $fillable = [
        'my_user_id', 
        'user_id'
    ];

    /**
     * User who gave the like (my_user_id)
     */
    public function liker()
    {
        return $this->belongsTo(Users::class, 'my_user_id', 'id');
    }
    
    /**
     * User who received the like (user_id)
     */
    public function liked()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }
    
    /**
     * Legacy method for backward compatibility
     */
    function user()
    {
        return $this->hasOne(Users::class, 'id', 'user_id');
    }
}
