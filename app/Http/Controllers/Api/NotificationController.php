<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::with('user')->orderBy('created_at', 'desc')->paginate(10);
        return response()->json($notifications);
    }

    public function store(Request $request)
    {
        $notification = Notification::create($request->all());
        return response()->json(['message' => 'Gửi thông báo thành công', 'data' => $notification]);
    }

    public function show($id)
    {
        return response()->json(Notification::with('user')->findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $n = Notification::findOrFail($id);
        $n->update($request->all());
        return response()->json(['message' => 'Cập nhật thông báo thành công', 'data' => $n]);
    }

    public function destroy($id)
    {
        Notification::findOrFail($id)->delete();
        return response()->json(['message' => 'Xóa thông báo thành công']);
    }
}
