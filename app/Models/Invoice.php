<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $table = 'invoices';
    protected $guarded = [];
    public $timestamps = false;

    // Helper to decode products JSON
    protected function casts(): array
    {
        return [
            'products' => 'array',
        ];
    }
}
