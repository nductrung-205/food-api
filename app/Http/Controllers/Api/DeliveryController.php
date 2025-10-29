<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function index()
    {
        $deliveries = Delivery::with('order')->paginate(10);
        return response()->json($deliveries);
    }

    public function store(Request $request)
    {
        $delivery = Delivery::create($request->all());
        return response()->json(['message' => 'Thêm đơn giao hàng thành công', 'data' => $delivery]);
    }

    public function show($id)
    {
        return response()->json(Delivery::with('order')->findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $delivery = Delivery::findOrFail($id);
        $delivery->update($request->all());
        return response()->json(['message' => 'Cập nhật đơn giao hàng thành công', 'data' => $delivery]);
    }

    public function destroy($id)
    {
        Delivery::findOrFail($id)->delete();
        return response()->json(['message' => 'Xóa đơn giao hàng thành công']);
    }
}
