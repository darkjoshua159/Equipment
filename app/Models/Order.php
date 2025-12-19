<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['user_id', 'equipment_id', 'quantity', 'total_price', 'status'];

    // This links the order to the equipment item
    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }
}