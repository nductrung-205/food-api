<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Đăng ký tài khoản mới
     */
    public function register(Request $request)
    {
        $request->validate([
            'fullname' => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'phone'    => 'nullable|string|max:20',
            'address'  => 'nullable|string|max:500',
        ], [
            'fullname.required' => 'Vui lòng nhập họ tên',
            'fullname.max'      => 'Họ tên không được quá 255 ký tự',

            'email.required'    => 'Vui lòng nhập email',
            'email.email'       => 'Email không hợp lệ',
            'email.max'         => 'Email không được quá 255 ký tự',
            'email.unique'      => 'Email đã tồn tại',

            'password.required' => 'Vui lòng nhập mật khẩu',
            'password.min'      => 'Mật khẩu phải có ít nhất 6 ký tự',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp',

            'phone.max'         => 'Số điện thoại không được quá 20 ký tự',
            'address.max'       => 'Địa chỉ không được quá 500 ký tự',
        ]);

        $user = User::create([
            'fullname' => $request->fullname,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => User::ROLE_USER ?? 1,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Đăng ký thành công',
            'user'    => $user,
            'token'   => $token,
        ], 201);
    }

    /**
     * Đăng nhập
     */
    public function login(Request $request)
    {
        // Validate dữ liệu
        $request->validate([
            'email'    => 'required|string|email|max:255',
            'password' => 'required|string|min:6|max:64',
        ], [
            'email.required' => 'Vui lòng nhập email',
            'email.email'    => 'Email không hợp lệ',
            'email.max'      => 'Email quá dài, tối đa 255 ký tự',

            'password.required' => 'Vui lòng nhập mật khẩu',
            'password.min'      => 'Mật khẩu phải có ít nhất 6 ký tự',
            'password.max'      => 'Mật khẩu không được vượt quá 64 ký tự',
        ]);


        // Kiểm tra email tồn tại
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Email không tồn tại'], 404);
        }

        // Kiểm tra mật khẩu
        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Sai mật khẩu'], 401);
        }

        // Xóa token cũ
        $user->tokens()->delete();

        // Tạo token mới
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'user'    => $user,
            'token'   => $token,
        ]);
    }


    /**
     * Đăng xuất
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Đăng xuất thành công']);
    }

    /**
     * Lấy thông tin user hiện tại
     */
    public function me(Request $request)
    {
        Log::info('=== /api/me called ===', [
            'Authorization' => $request->header('Authorization'),
            'Token'         => $request->bearerToken(),
            'User'          => optional($request->user())->email,
        ]);

        $user = $request->user();

        if (!$user) {
            Log::error('User not authenticated!');
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json($user);
    }

    /**
     * Đổi mật khẩu
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6|confirmed',
        ], [
            'current_password.required' => 'Vui lòng nhập mật khẩu hiện tại',
            'new_password.required'     => 'Vui lòng nhập mật khẩu mới',
            'new_password.min'          => 'Mật khẩu mới phải có ít nhất 6 ký tự',
            'new_password.confirmed'    => 'Xác nhận mật khẩu không khớp',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Mật khẩu hiện tại không đúng'],
            ]);
        }

        if (Hash::check($request->new_password, $user->password)) {
            throw ValidationException::withMessages([
                'new_password' => ['Mật khẩu mới không được trùng với mật khẩu hiện tại'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        Log::info('User changed password: ' . $user->email);

        return response()->json(['message' => 'Đổi mật khẩu thành công']);
    }

    /**
     * Cập nhật thông tin người dùng
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'fullname' => 'sometimes|string|max:255',
            'phone'    => 'sometimes|nullable|string|max:20',
            'address'  => 'sometimes|nullable|string|max:500',
        ]);


        $user->update($request->only(['fullname', 'phone', 'address']));

        return response()->json([
            'message' => 'Cập nhật thông tin thành công',
            'user'    => $user,
        ]);
    }
}
