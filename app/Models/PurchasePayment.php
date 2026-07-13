<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchasePayment extends Model
{
    protected $table = 'purchase_payments';
    protected $guarded = [];
    public $timestamps = true;

    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }
}
