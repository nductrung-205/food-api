<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index() { return response()->json(Comment::with('user', 'product')->paginate(10)); }
    public function store(Request $r) { $c = Comment::create($r->all()); return response()->json(['message' => 'Thêm bình luận', 'data' => $c]); }
    public function show($id) { return response()->json(Comment::findOrFail($id)); }
    public function update(Request $r, $id) { $c = Comment::findOrFail($id); $c->update($r->all()); return response()->json(['message'=>'Cập nhật bình luận','data'=>$c]); }
    public function destroy($id) { Comment::findOrFail($id)->delete(); return response()->json(['message'=>'Xóa bình luận']); }
}
