<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\RevenueExport;
use Barryvdh\DomPDF\Facade\Pdf;

class RevenueExportController extends Controller
{
    public function export(Request $request)
    {
        $type = $request->query('type', 'excel');
        $timeRange = $request->query('range', 'month'); // 'week', 'month', 'year'

        $query = Order::where('status', 'delivered');

        if ($timeRange === 'week') {
            $query->where('created_at', '>=', now()->subDays(7));
        } elseif ($timeRange === 'month') {
            $query->where('created_at', '>=', now()->subDays(30));
        } elseif ($timeRange === 'year') {
            $query->where('created_at', '>=', now()->subYear());
        }

        $orders = $query->get();

        if ($type === 'pdf') {
            $pdf = Pdf::loadView('exports.revenue', compact('orders', 'timeRange'));
            return $pdf->download('bao_cao_doanh_thu.pdf');
        }

        return Excel::download(new RevenueExport($orders), 'bao_cao_doanh_thu.xlsx');
    }
}
