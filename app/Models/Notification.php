<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes; // Thêm dòng này

class Notification extends Model
{
    use HasFactory, SoftDeletes; // Thêm SoftDeletes vào đây

    protected $fillable = [
        'user_id', 'title', 'message', 'is_read', 'type', 'data' // Thêm 'type' và 'data'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'data' => 'array', // Thêm cast cho trường 'data'
    ];

    // Mối quan hệ với User
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}