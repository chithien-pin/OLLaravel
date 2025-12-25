<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes;
    public $table = "posts";

    /**
     * Get post media content (R2 storage)
     * Named 'content' for backward compatibility with Flutter app
     */
    public function content()
    {
        return $this->hasMany(PostMedia::class, 'post_id', 'id');
    }

    public function user()
    {
        return $this->hasOne(Users::class, 'id','user_id');
    }

}
