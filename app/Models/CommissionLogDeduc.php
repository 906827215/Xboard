<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionLogDeduc extends Model
{
    protected $table = 'v2_commission_log_deduc';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];
}
