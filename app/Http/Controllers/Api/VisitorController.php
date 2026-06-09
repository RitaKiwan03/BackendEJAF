<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class VisitorController extends Controller
{
    /**
     * POST /api/visitors/track — Public
     * تتبع الزوار مع حماية من التكرار والـ spam
     * Rate limiting مضبوط في api.php (throttle:30,1)
     */
    public function track(Request $request): JsonResponse
    {
        $ip  = $request->ip();
        $now = now();

        // ✅ تجاهل البوتات المعروفة
        $userAgent = $request->userAgent() ?? '';
        $botKeywords = ['bot', 'crawler', 'spider', 'slurp', 'baiduspider', 'googlebot'];
        foreach ($botKeywords as $keyword) {
            if (stripos($userAgent, $keyword) !== false) {
                return response()->json(['tracked' => false]);
            }
        }

        // ✅ تحقق إذا نفس الـ IP زار اليوم
        $visitedToday = DB::table('visitor_logs')
            ->where('ip_address', $ip)
            ->whereDate('created_at', today())
            ->exists();

        if (!$visitedToday) {
            DB::table('visitor_logs')->insert([
                'ip_address'   => $ip,
                'page'         => substr($request->input('page', '/'), 0, 500), // ✅ تحديد طول الـ page
                'user_agent'   => substr($userAgent, 0, 500),
                'last_seen_at' => $now,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        } else {
            DB::table('visitor_logs')
                ->where('ip_address', $ip)
                ->whereDate('created_at', today())
                ->update(['last_seen_at' => $now, 'updated_at' => $now]);
        }

        return response()->json(['tracked' => true]);
    }

    /**
     * GET /api/visitors/stats — Protected (auth:sanctum)
     * إحصائيات الزوار للأدمن فقط
     */
    public function stats(): JsonResponse
    {
        $now = now();

        $total = DB::table('visitor_logs')
            ->distinct('ip_address')
            ->count('ip_address');

        $thisMonth = DB::table('visitor_logs')
            ->distinct('ip_address')
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->count('ip_address');

        $thisYear = DB::table('visitor_logs')
            ->distinct('ip_address')
            ->whereYear('created_at', $now->year)
            ->count('ip_address');

        // ✅ آخر 5 دقائق = أونلاين
        $online = DB::table('visitor_logs')
            ->distinct('ip_address')
            ->where('last_seen_at', '>=', $now->copy()->subMinutes(5))
            ->count('ip_address');

        $monthly = DB::table('visitor_logs')
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(DISTINCT ip_address) as count')
            ->where('created_at', '>=', $now->copy()->subMonths(6))
            ->groupByRaw('YEAR(created_at), MONTH(created_at)')
            ->orderByRaw('YEAR(created_at), MONTH(created_at)')
            ->get()
            ->map(function ($row) {
                return [
                    'label' => date('M Y', mktime(0, 0, 0, $row->month, 1, $row->year)),
                    'count' => $row->count,
                ];
            });

        return response()->json([
            'total'      => $total,
            'this_month' => $thisMonth,
            'this_year'  => $thisYear,
            'online'     => $online,
            'monthly'    => $monthly,
        ]);
    }
}
