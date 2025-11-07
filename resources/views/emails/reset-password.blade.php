@component('mail::message')
# Đặt lại mật khẩu

Bạn nhận được email này vì chúng tôi đã nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.

Vui lòng nhấp vào nút bên dưới để đặt lại mật khẩu của bạn:

@component('mail::button', ['url' => $resetUrl])
Đặt lại mật khẩu
@endcomponent

Liên kết đặt lại mật khẩu này sẽ hết hạn trong 60 phút.

Nếu bạn không yêu cầu đặt lại mật khẩu, bạn có thể bỏ qua email này một cách an toàn.

Trân trọng,<br>
{{ config('app.name') }}
@endcomponent