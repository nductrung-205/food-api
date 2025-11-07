<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MomoController extends Controller
{
    private string $endpoint;
    private string $partnerCode;
    private string $accessKey;
    private string $secretKey;
    private string $returnUrl;
    private string $notifyUrl;

    public function __construct()
    {
        $this->endpoint = env('MOMO_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/create');
        $this->partnerCode = env('MOMO_PARTNER_CODE');
        $this->accessKey = env('MOMO_ACCESS_KEY');
        $this->secretKey = env('MOMO_SECRET_KEY');

        // URL frontend để redirect sau khi thanh toán
        $frontendUrl = env('APP_URL_FRONTEND', 'http://localhost:5173');
        $this->returnUrl = $frontendUrl . '/payment-success';

        // URL backend để nhận IPN notification
        $backendUrl = env('APP_URL', 'http://localhost:8000'); // Thay 'http://localhost:8000' bằng URL backend thực tế của bạn
        $this->notifyUrl = $backendUrl . '/api/momo/notify';
    }

    /**
     * Tạo payment URL MoMo
     */
    public function createPayment(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1000',
            'your_internal_order_id' => 'required'
        ]);

        $amount = $request->amount;
        $yourInternalOrderId = $request->your_internal_order_id;

        // Tạo orderId và requestId
        $orderId = $yourInternalOrderId . '_' . time() . '_' . random_int(1000, 9999);
        $requestId = $yourInternalOrderId . '_' . time() . '_' . random_int(1000, 9999);
        $orderInfo = "Thanh toán đơn hàng #$yourInternalOrderId";
        $extraData = base64_encode(json_encode(['your_internal_order_id' => $yourInternalOrderId]));

        // Tạo raw signature (theo thứ tự alphabet)
        $rawHash = "accessKey={$this->accessKey}&amount={$amount}&extraData={$extraData}&ipnUrl={$this->notifyUrl}&orderId={$orderId}&orderInfo={$orderInfo}&partnerCode={$this->partnerCode}&redirectUrl={$this->returnUrl}&requestId={$requestId}&requestType=captureWallet";
        $signature = hash_hmac('sha256', $rawHash, $this->secretKey);

        // Payload gửi MoMo
        $data = [
            "partnerCode" => $this->partnerCode,
            "accessKey"   => $this->accessKey,
            "requestId"   => $requestId,
            "amount"      => (string)$amount,
            "orderId"     => $orderId,
            "orderInfo"   => $orderInfo,
            "redirectUrl" => $this->returnUrl,
            "ipnUrl"      => $this->notifyUrl,
            "extraData"   => $extraData,
            "requestType" => "captureWallet",
            "signature"   => $signature,
            "lang"        => "vi"
        ];

        // Gửi request tới MoMo
        $ch = curl_init($this->endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $result = curl_exec($ch);

        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            Log::error('CURL Error: ' . $error);
            return response()->json([
                'code' => '01',
                'message' => 'Không thể kết nối tới MoMo',
                'error' => $error
            ]);
        }

        curl_close($ch);
        $response = json_decode($result, true);

        // Kiểm tra response
        if (isset($response['payUrl'])) {
            return response()->json([
                'code' => '00',
                'message' => 'success',
                'payUrl' => $response['payUrl'],
                'orderId' => $orderId
            ]);
        }

        return response()->json([
            'code' => '01',
            'message' => 'Không thể khởi tạo thanh toán MoMo',
            'data' => $response
        ]);
    }

    /**
     * Callback MoMo return URL (khi user quay về từ MoMo)
     */
    public function momoReturn(Request $request)
    {
        $data = $request->all();

        Log::info('MoMo return URL received:', $data);

        $signature = $data['signature'] ?? '';
        $resultCode = $data['resultCode'] ?? -1;
        $amount = $data['amount'] ?? 0;
        $orderId = $data['orderId'] ?? null;

        if (!$orderId) {
            return response()->json([
                'status' => 'error',
                'message' => 'orderId không tồn tại'
            ]);
        }

        // 1️⃣ Tạo raw signature để xác thực
        $rawHash = "accessKey={$this->accessKey}&amount={$data['amount']}&extraData={$data['extraData']}&message={$data['message']}&orderId={$data['orderId']}&orderInfo={$data['orderInfo']}&orderType={$data['orderType']}&partnerCode={$data['partnerCode']}&payType={$data['payType']}&requestId={$data['requestId']}&responseTime={$data['responseTime']}&resultCode={$data['resultCode']}&transId={$data['transId']}";
        $checkSignature = hash_hmac("sha256", $rawHash, $this->secretKey);

        if ($checkSignature !== $signature) {
            Log::warning('MoMo signature verification failed', ['data' => $data]);
            return response()->json([
                'status' => 'error',
                'message' => 'Chữ ký MoMo không hợp lệ!'
            ]);
        }

        // 2️⃣ Kiểm tra resultCode
        if ($resultCode != 0) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Thanh toán MoMo thất bại!',
                'resultCode' => $resultCode,
                'data' => $data
            ]);
        }

        // 3️⃣ Lấy your_internal_order_id từ extraData hoặc orderId
        $extraDataDecoded = json_decode(base64_decode($data['extraData'] ?? ''), true);
        $yourInternalOrderId = $extraDataDecoded['your_internal_order_id'] ?? null;

        // Nếu không có trong extraData, lấy từ orderId (format: {id}_{timestamp})
        if (!$yourInternalOrderId) {
            $yourInternalOrderId = explode('_', $orderId)[0];
        }

        // 4️⃣ TODO: Cập nhật trạng thái đơn hàng trong DB
        /*
        $order = Order::find($yourInternalOrderId);
        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Đơn hàng không tồn tại!'
            ]);
        }

        $order->update([
            'status' => 'paid',
            'paid_amount' => $amount,
            'momo_trans_id' => $data['transId'],
            'payment_date' => now(),
        ]);
        */

        return response()->json([
            'status'  => 'success',
            'message' => 'Thanh toán MoMo thành công!',
            'orderId' => $yourInternalOrderId,
            'amount' => $amount,
            'transId' => $data['transId'] ?? null,
        ]);
    }

    /**
     * Callback MoMo IPN (Server-to-Server notification)
     */
    public function notify(Request $request)
    {
        $data = $request->all();

        Log::info('MoMo IPN notify received:', $data);

        $signature = $data['signature'] ?? '';
        $resultCode = $data['resultCode'] ?? -1;
        $amount = $data['amount'] ?? 0;
        $orderId = $data['orderId'] ?? null;

        // Xác thực signature
        $rawHash = "accessKey={$this->accessKey}&amount={$data['amount']}&extraData={$data['extraData']}&message={$data['message']}&orderId={$data['orderId']}&orderInfo={$data['orderInfo']}&orderType={$data['orderType']}&partnerCode={$data['partnerCode']}&payType={$data['payType']}&requestId={$data['requestId']}&responseTime={$data['responseTime']}&resultCode={$data['resultCode']}&transId={$data['transId']}";
        $checkSignature = hash_hmac("sha256", $rawHash, $this->secretKey);

        if ($checkSignature !== $signature) {
            Log::warning('MoMo IPN signature verification failed', ['data' => $data]);
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        // Lấy your_internal_order_id
        $extraDataDecoded = json_decode(base64_decode($data['extraData'] ?? ''), true);
        $yourInternalOrderId = $extraDataDecoded['your_internal_order_id'] ?? explode('_', $orderId)[0];

        // Xử lý theo resultCode
        if ($resultCode == 0) {
            // Thanh toán thành công
            Log::info('MoMo payment successful', [
                'your_internal_order_id' => $yourInternalOrderId,
                'amount' => $amount,
                'transId' => $data['transId'] ?? null
            ]);

            // TODO: Cập nhật DB
            /*
            $order = Order::find($yourInternalOrderId);
            if ($order) {
                $order->update([
                    'status' => 'paid',
                    'paid_amount' => $amount,
                    'momo_trans_id' => $data['transId'],
                    'payment_date' => now(),
                ]);
            }
            */
        } else {
            // Thanh toán thất bại
            Log::info('MoMo payment failed', [
                'your_internal_order_id' => $yourInternalOrderId,
                'resultCode' => $resultCode,
                'message' => $data['message'] ?? 'Unknown error'
            ]);
        }

        // MoMo yêu cầu response 204 No Content
        return response()->json(['message' => 'Notify received'], 200);
    }

    /**
     * Kiểm tra trạng thái giao dịch
     */
    public function transactionStatus(Request $request)
    {
        $orderId = $request->orderId;

        if (!$orderId) {
            return response()->json([
                'code' => '01',
                'message' => 'orderId là bắt buộc'
            ]);
        }

        $requestId = time() . rand(1000, 9999);
        $rawHash = "accessKey={$this->accessKey}&orderId={$orderId}&partnerCode={$this->partnerCode}&requestId={$requestId}";
        $signature = hash_hmac('sha256', $rawHash, $this->secretKey);

        $data = [
            "partnerCode" => $this->partnerCode,
            "accessKey"   => $this->accessKey,
            "requestId"   => $requestId,
            "orderId"     => $orderId,
            "signature"   => $signature,
            "lang"        => "vi"
        ];

        $endpoint = 'https://test-payment.momo.vn/v2/gateway/api/query';

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $result = curl_exec($ch);

        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            Log::error('CURL Error: ' . $error);
            return response()->json([
                'code' => '01',
                'message' => 'Không thể kết nối tới MoMo',
                'error' => $error
            ]);
        }

        curl_close($ch);
        $response = json_decode($result, true);

        return response()->json($response);
    }
}
