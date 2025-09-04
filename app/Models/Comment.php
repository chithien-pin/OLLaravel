<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;
    public $table = "comments";

    protected $fillable = [
        'user_id',
        'post_id',
        'description'
    ];
    
    public function user()
    {
        return $this->hasOne(Users::class, 'id','user_id');
    }

    // Relationship with Post
    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id', 'id');
    }
}
