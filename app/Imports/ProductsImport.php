<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Category; // Import Category model
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Str;
use Throwable; // Import Throwable for catching exceptions

class ProductsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure, WithChunkReading
{
    use SkipsFailures; // Required for SkipsOnFailure

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // Log the row data for debugging
        Log::info('Processing row for import:', $row);

        // Basic validation for required fields
        if (empty($row['ten_san_pham']) || empty($row['gia']) || empty($row['ten_danh_muc'])) {
            // You can log this or throw a custom exception if you want to explicitly skip
            Log::warning('Skipping row due to missing required data:', $row);
            return null;
        }

        // Find category by name, or create if it doesn't exist
        // You might want to handle case sensitivity or aliases here
        $category = Category::firstOrCreate(
            ['name' => $row['ten_danh_muc']],
            ['slug' => Str::slug($row['ten_danh_muc'])] // Auto-generate slug if new category
        );

        // Generate slug from product name if not provided
        $slug = $row['slug'] ?? Str::slug($row['ten_san_pham']);

        return new Product([
            'category_id' => $category->id,
            'name'        => $row['ten_san_pham'],
            'slug'        => $slug,
            'description' => $row['mo_ta'] ?? null,
            'price'       => $row['gia'],
            'stock'       => $row['ton_kho'] ?? 0,
            'image'       => $row['link_anh'] ?? null, // Assuming image is a URL for simplicity
            'status'      => isset($row['trang_thai']) ? (bool)$row['trang_thai'] : true, // Default to true
        ]);
    }

    /**
     * Define validation rules for each row
     * The array keys should match your Excel column headers (after sanitization by WithHeadingRow)
     */
    public function rules(): array
    {
        return [
            'ten_san_pham' => 'required|string|max:255',
            'gia'          => 'required|numeric|min:0',
            'ton_kho'      => 'nullable|integer|min:0',
            'ten_danh_muc' => 'required|string|max:255',
            'link_anh'     => 'nullable|url', // Validate if it's a valid URL
            'trang_thai'   => 'nullable|boolean',
            // 'slug' might be optional as we generate it, but can be validated if provided
            'slug'         => 'nullable|string|max:255|unique:products,slug',
        ];
    }

    /**
     * Custom validation messages
     */
    public function customValidationMessages()
    {
        return [
            'ten_san_pham.required' => 'Cột "Tên sản phẩm" là bắt buộc.',
            'gia.required'          => 'Cột "Giá" là bắt buộc.',
            'gia.numeric'           => 'Cột "Giá" phải là số.',
            'ton_kho.integer'       => 'Cột "Tồn kho" phải là số nguyên.',
            'ton_danh_muc.required' => 'Cột "Tên danh mục" là bắt buộc.',
            'link_anh.url'          => 'Cột "Link ảnh" phải là một URL hợp lệ.',
            'trang_thai.boolean'    => 'Cột "Trạng thái" phải là 0 hoặc 1.',
            'slug.unique'           => 'Slug ":input" đã tồn tại, vui lòng kiểm tra lại.',
        ];
    }

    /**
     * Chunk size for processing records (improves performance for large files)
     */
    public function chunkSize(): int
    {
        return 1000;
    }
}