<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index()
    {
        $payments = Payment::with('order')->paginate(10);
        return response()->json($payments);
    }

    public function store(Request $request)
    {
        $payment = Payment::create($request->all());
        return response()->json(['message' => 'Tạo thanh toán thành công', 'data' => $payment]);
    }

    public function show($id)
    {
        return response()->json(Payment::with('order')->findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);
        $payment->update($request->all());
        return response()->json(['message' => 'Cập nhật thanh toán thành công', 'data' => $payment]);
    }

    public function destroy($id)
    {
        Payment::findOrFail($id)->delete();
        return response()->json(['message' => 'Xóa thanh toán thành công']);
    }
}
