<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gifts extends Model
{
    use HasFactory;
    public $table = "gifts";
    
    protected $fillable = [
        'name',
        'image',
        'coin_price'
    ];
}
