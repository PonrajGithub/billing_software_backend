<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $table = 'purchases';
    protected $guarded = [];
    public $timestamps = true;

    public function items()
    {
        return $this->hasMany(PurchaseItem::class, 'purchase_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
