<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use App\Models\Order; // Make sure to import your Order model
use App\Models\Payment; // Import your Payment model
use Illuminate\Support\Facades\Log; // Good for debugging

class VNPayController extends Controller
{
    public function createPayment(Request $request)
    {
        $vnp_TmnCode = Config::get('vnpay.vnp_tmncode');
        $vnp_HashSecret = Config::get('vnpay.vnp_hashsecret');
        $vnp_Url = Config::get('vnpay.vnp_url');
        $vnp_Returnurl = Config::get('vnpay.vnp_returnurl');

        // Use the order_id passed from the frontend for vnp_TxnRef
        $orderId = $request->amount; // THIS IS WRONG. $request->amount is the amount, not the order ID.
                                     // It should be $request->order_id as per previous suggestion.
                                     // Revert to: $orderId = $request->order_id;

        // Make sure orderId is present and is actually the ID
        if (empty($request->order_id)) {
            Log::error('❌ VNPay Create Payment: Missing order_id in request', $request->all());
            return response()->json([
                'success' => false,
                'message' => 'Missing order ID for VNPay payment creation.'
            ], 400);
        }
        $vnp_TxnRef = $request->order_id; // Use your internal order ID as VNPay's transaction reference

        $vnp_OrderInfo = "Thanh toan don hang #" . $vnp_TxnRef; // This will now include your order ID
        $vnp_OrderType = "billpayment";
        $vnp_Amount = $request->amount * 100; // nhân 100 vì VNPay tính theo đơn vị nhỏ
        $vnp_Locale = "vn";
        $vnp_BankCode = $request->bank_code ?? "";
        $vnp_IpAddr = $request->ip();

        $inputData = [
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $vnp_IpAddr,
            "vnp_Locale" => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => $vnp_Returnurl,
            "vnp_TxnRef" => $vnp_TxnRef, // THIS IS YOUR ORDER ID
        ];

        if (!empty($vnp_BankCode)) {
            $inputData['vnp_BankCode'] = $vnp_BankCode;
        }

        ksort($inputData);
        $query = "";
        $i = 0;
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        $vnp_Url = $vnp_Url . "?" . $query;
        if (isset($vnp_HashSecret)) {
            $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
            $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
        }

        Log::info('🌐 VNPay Payment URL generated:', ['url' => $vnp_Url, 'order_id' => $vnp_TxnRef]);

        return response()->json([
            'payment_url' => $vnp_Url,
        ]);
    }

    public function vnpayReturn(Request $request)
    {
        Log::info('⬅️ VNPay Return Callback received:', $request->all());

        $vnp_HashSecret = Config::get('vnpay.vnp_hashsecret');
        $inputData = $request->all();

        $vnp_SecureHash = $inputData['vnp_SecureHash'] ?? ''; // Handle if not present
        unset($inputData['vnp_SecureHash']);
        unset($inputData['vnp_SecureHashType']);

        // Sort data for hash verification
        ksort($inputData);
        $hashData = "";
        $i = 0;
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashData = $hashData . '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashData = $hashData . urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
        }

        $secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);

        $orderId = $inputData['vnp_TxnRef'] ?? null; // Get your order ID from vnp_TxnRef
        $order = null;
        if ($orderId) {
            $order = Order::find($orderId);
            if (!$order) {
                Log::warning('⚠️ VNPay Return: Order not found for vnp_TxnRef:', ['order_id' => $orderId]);
            }
        } else {
            Log::error('❌ VNPay Return: Missing vnp_TxnRef in callback data.', $inputData);
        }

        // Prepare common payment data for the Payment record
        $paymentData = [
            'order_id' => $orderId,
            'method' => 'VNPay',
            'amount' => ($inputData['vnp_Amount'] ?? 0) / 100, // Convert back to original amount
            'status' => 'failed', // Default to failed, update if successful
            'vnp_txn_ref' => $inputData['vnp_TxnRef'] ?? null,
            'vnp_transaction_no' => $inputData['vnp_TransactionNo'] ?? null,
            'vnp_response_code' => $inputData['vnp_ResponseCode'] ?? null,
            'vnp_bank_code' => $inputData['vnp_BankCode'] ?? null,
            'vnp_card_type' => $inputData['vnp_CardType'] ?? null,
            'vnp_pay_date' => isset($inputData['vnp_PayDate']) ? \Carbon\Carbon::parse($inputData['vnp_PayDate'])->toDateTimeString() : null,
            'vnp_order_info' => $inputData['vnp_OrderInfo'] ?? null,
            'vnp_secure_hash' => $vnp_SecureHash,
            'notes' => 'VNPay callback',
        ];

        if ($secureHash === $vnp_SecureHash) {
            if ($inputData['vnp_ResponseCode'] == '00' && $inputData['vnp_TransactionStatus'] == '00') {
                // ✅ Payment successful
                Log::info('✅ VNPay Return: Payment successful for order:', ['order_id' => $orderId]);
                $paymentData['status'] = 'completed';

                if ($order) {
                    $order->status = 'paid'; // Update main order status
                    $order->paid_at = now(); // Set paid_at timestamp
                    $order->save();
                    Log::info('✅ Order status updated to paid:', ['order_id' => $orderId]);
                    // You might want to clear the cart here or on the frontend after redirect
                }

                // Create or update the Payment record
                // Assuming vnp_txn_ref is unique for each payment transaction
                Payment::updateOrCreate(
                    ['vnp_txn_ref' => $orderId], // Use vnp_txn_ref to identify the payment
                    $paymentData
                );
                Log::info('✅ Payment record created/updated:', ['vnp_txn_ref' => $orderId, 'status' => 'completed']);

                return redirect('http://localhost:5173/payment-success?vnp_ResponseCode=00&orderId=' . $orderId);

            } else {
                // ❌ Payment failed or cancelled
                Log::warning('⚠️ VNPay Return: Payment failed/cancelled for order:', [
                    'order_id' => $orderId,
                    'response_code' => $inputData['vnp_ResponseCode'],
                    'transaction_status' => $inputData['vnp_TransactionStatus']
                ]);
                $paymentData['status'] = 'failed';
                $paymentData['notes'] = 'VNPay callback: Payment failed or cancelled';

                if ($order) {
                    // Update order status to indicate payment failure
                    if ($order->status === 'pending') { // Only update if still pending
                        $order->status = 'payment_failed';
                        $order->save();
                        Log::info('⚠️ Order status updated to payment_failed:', ['order_id' => $orderId]);
                    }
                }

                Payment::updateOrCreate(
                    ['vnp_txn_ref' => $orderId],
                    $paymentData
                );
                Log::info('⚠️ Payment record created/updated:', ['vnp_txn_ref' => $orderId, 'status' => 'failed']);

                return redirect('http://localhost:5173/payment-fail?vnp_ResponseCode=' . ($inputData['vnp_ResponseCode'] ?? 'unknown') . '&orderId=' . $orderId);
            }
        } else {
            // Invalid hash - potential tampering or incorrect secret
            Log::error('🚨 VNPay Return: Invalid Secure Hash for order:', ['order_id' => $orderId, 'input_data' => $inputData]);
            $paymentData['status'] = 'invalid_hash';
            $paymentData['notes'] = 'VNPay callback: Invalid Secure Hash';

            if ($order) {
                if ($order->status === 'pending') { // Only update if still pending
                    $order->status = 'payment_error'; // Or 'cancelled' if it was a pending order
                    $order->save();
                    Log::info('🚨 Order status updated to payment_error due to invalid hash:', ['order_id' => $orderId]);
                }
            }

            Payment::updateOrCreate(
                ['vnp_txn_ref' => $orderId],
                $paymentData
            );
            Log::info('🚨 Payment record created/updated:', ['vnp_txn_ref' => $orderId, 'status' => 'invalid_hash']);

            return redirect('http://localhost:5173/payment-fail?error=invalid-hash&orderId=' . $orderId);
        }
    }
}