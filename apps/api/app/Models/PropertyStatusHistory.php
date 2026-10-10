<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyStatusHistory extends Model
{
    public $timestamps = false;

    protected $table = 'property_status_history';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime', 'from_price' => 'decimal:2', 'to_price' => 'decimal:2'];
    }
}
