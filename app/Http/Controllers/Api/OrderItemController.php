<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class OrderItemController extends Controller
{
    public function index()
    {
        $items = OrderItem::with(['order', 'product'])->paginate(10);
        return response()->json($items);
    }

    public function store(Request $request)
    {
        $item = OrderItem::create($request->all());
        return response()->json(['message' => 'Thêm sản phẩm vào đơn hàng thành công', 'data' => $item]);
    }

    public function show($id)
    {
        return response()->json(OrderItem::with(['order', 'product'])->findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $item = OrderItem::findOrFail($id);
        $item->update($request->all());
        return response()->json(['message' => 'Cập nhật sản phẩm trong đơn hàng thành công', 'data' => $item]);
    }

    public function destroy($id)
    {
        OrderItem::findOrFail($id)->delete();
        return response()->json(['message' => 'Xóa sản phẩm khỏi đơn hàng thành công']);
    }
}
