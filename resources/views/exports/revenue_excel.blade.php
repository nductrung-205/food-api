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
