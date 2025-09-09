<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RedeemRequest extends Model
{
    use HasFactory;
    public $table = "redeem_requests";

    protected $fillable = [
        'user_id',
        'request_id', 
        'coin_amount',
        'payment_gateway',
        'account_details',
        'account_holder_name',
        'bank_name',
        'account_number',
        'amount_paid',
        'status'
    ];

    function user(){
        return $this->hasOne(Users::class, 'id','user_id');
    }
}
