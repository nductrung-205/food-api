<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Báo Cáo Doanh Thu</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; font-size: 12px; }
        th { background: #f3f3f3; }
    </style>
</head>
<body>
    <h2>Báo Cáo Doanh Thu ({{ strtoupper($timeRange) }})</h2>
    <table>
        <thead>
            <tr>
                <th>Ngày</th>
                <th>Mã đơn</th>
                <th>Khách hàng</th>
                <th>Tổng tiền</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($orders as $order)
            <tr>
                <td>{{ $order->created_at->format('d/m/Y') }}</td>
                <td>{{ $order->id }}</td>
                <td>{{ $order->user->fullname ?? 'N/A' }}</td>
                <td>{{ number_format($order->total_price, 0, ',', '.') }}₫</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
