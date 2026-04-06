<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use HasFactory, SoftDeletes;
    public $table = "comments";

    protected $fillable = [
        'user_id',
        'post_id',
        'description'
    ];

    protected $dates = ['deleted_at'];

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
