<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index() { return response()->json(Banner::paginate(10)); }
    public function store(Request $r) { $b = Banner::create($r->all()); return response()->json(['message'=>'Thêm banner','data'=>$b]); }
    public function show($id) { return response()->json(Banner::findOrFail($id)); }
    public function update(Request $r, $id) { $b = Banner::findOrFail($id); $b->update($r->all()); return response()->json(['message'=>'Cập nhật banner','data'=>$b]); }
    public function destroy($id) { Banner::findOrFail($id)->delete(); return response()->json(['message'=>'Xóa banner']); }
}
