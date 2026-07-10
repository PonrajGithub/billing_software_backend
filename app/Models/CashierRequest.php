<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashierRequest extends Model
{
    protected $table = 'cashier_requests';
    protected $guarded = [];
    public $timestamps = false;
}
