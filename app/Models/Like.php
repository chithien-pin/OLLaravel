<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Like extends Model
{
    use HasFactory;
    public $table = "likes";

    protected $fillable = [
        'user_id',
        'post_id'
    ];

    // Relationship with Post
    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id', 'id');
    }

    // Relationship with User
    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }
}
