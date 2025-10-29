<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json(Category::paginate(10));
    }
    public function store(Request $request)
    {
        $data = Category::create($request->all());
        return response()->json(['message' => 'Thêm danh mục thành công', 'data' => $data]);
    }
    public function show($id)
    {
        return response()->json(Category::findOrFail($id));
    }
    public function update(Request $request, $id)
    {
        $c = Category::findOrFail($id);
        $c->update($request->all());
        return response()->json(['message' => 'Cập nhật danh mục', 'data' => $c]);
    }
    public function destroy($id)
    {
        Category::findOrFail($id)->delete();
        return response()->json(['message' => 'Xóa danh mục thành công']);
    }

    public function productsBySlug($slug)
    {
        $category = Category::where('slug', $slug)->firstOrFail();
        $products = $category->products()
            ->where('status', true)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'category' => $category,
            'products' => $products
        ]);
    }
}
