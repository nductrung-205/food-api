<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'stock',
        'image',
        'status',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        // Nếu ảnh là URL tuyệt đối (link ngoài), trả luôn
        if ($this->image && filter_var($this->image, FILTER_VALIDATE_URL)) {
            return $this->image;
        }

        // Chuẩn hoá đường dẫn, fix lỗi Windows (\ thành /)
        if ($this->image) {
            $path = str_replace('\\', '/', $this->image);

            // Nếu tồn tại file trong storage/public
            if (Storage::disk('public')->exists($path)) {
                return asset('storage/' . $path);
            }
        }

        // Nếu không có thì trả no-image
        return asset('images/no-image.png');
    }


    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
