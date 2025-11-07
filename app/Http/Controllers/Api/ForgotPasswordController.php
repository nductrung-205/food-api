<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Mail\ResetPasswordMail; // Import mail
use Illuminate\Support\Facades\Mail; // Import Mail Facade

class ForgotPasswordController extends Controller
{
    /**
     * Gửi liên kết đặt lại mật khẩu đến email người dùng.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email'], [
            'email.required' => 'Vui lòng nhập địa chỉ email.',
            'email.email'    => 'Địa chỉ email không hợp lệ.',
        ]);

        $user = \App\Models\User::where('email', $request->email)->first();

        // Trả về message chung (tránh lộ thông tin user)
        $message = 'Nếu tài khoản của bạn tồn tại, một liên kết đặt lại mật khẩu đã được gửi đến email của bạn.';

        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ], 200);
        }

        // Xóa các token cũ nếu có
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // Tạo token mới (bản text)
        $plainToken = Str::random(60);

        // Lưu token được hash
        DB::table('password_reset_tokens')->insert([
            'email'      => $request->email,
            'token'      => Hash::make($plainToken),
            'created_at' => Carbon::now(),
        ]);

        // Gửi mail (hoặc log)
        Mail::to($user->email)->send(new \App\Mail\ResetPasswordMail($plainToken, $user->email));

        // ✅ Nếu đang chạy local, trả luôn link reset để frontend hiển thị
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if (config('app.env') === 'local') {
            $response['dev_reset_url'] = "http://localhost:5173/reset-password?token={$plainToken}&email={$user->email}";
        }

        return response()->json($response, 200);
    }

    /**
     * Đặt lại mật khẩu người dùng với token đã cung cấp.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'token.required'     => 'Token đặt lại mật khẩu là bắt buộc.',
            'email.required'     => 'Vui lòng nhập địa chỉ email.',
            'email.email'        => 'Địa chỉ email không hợp lệ.',
            'password.required'  => 'Vui lòng nhập mật khẩu mới.',
            'password.min'       => 'Mật khẩu mới phải có ít nhất 6 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ]);

        $passwordReset = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$passwordReset || !Hash::check($request->token, $passwordReset->token)) {
            throw ValidationException::withMessages([
                'email' => ['Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.'],
            ]);
        }

        // Kiểm tra thời gian hết hạn (ví dụ: 60 phút)
        $createdAt = Carbon::parse($passwordReset->created_at);
        if ($createdAt->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            throw ValidationException::withMessages([
                'email' => ['Liên kết đặt lại mật khẩu đã hết hạn. Vui lòng thử lại.'],
            ]);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['Không tìm thấy người dùng với email này.'],
            ]);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        // Xóa token sau khi sử dụng
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Mật khẩu của bạn đã được đặt lại thành công.'], 200);
    }
}
