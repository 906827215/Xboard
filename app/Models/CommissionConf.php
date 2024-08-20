<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionConf extends Model
{
    protected $table = 'v2_commission_conf';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];
}
