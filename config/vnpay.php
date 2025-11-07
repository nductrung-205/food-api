<?php

return [
    'vnp_tmncode' => env('VNP_TMN_CODE', 'FS228GGW'),
    'vnp_hashsecret' => env('VNP_HASH_SECRET', '8F4SIRNP01DCMOT2BSYWDRJ587AA7R2W'),
    'vnp_url' => env('VNP_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
    'vnp_returnurl' => env('VNP_RETURN_URL', env('APP_URL') . '/api/vnpay/return'),
];
