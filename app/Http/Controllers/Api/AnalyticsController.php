<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AnalyticsController extends Controller
{
    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /**
     * Beacon kunjungan halaman (dipanggil otomatis dari frontend saat navigasi).
     * Publik — user_id terisi bila pengunjung sedang login, NULL untuk tamu.
     */
    public function pageview(Request $request): JsonResponse
    {
        $path = substr((string) $request->query('path', ''), 0, 255);
        if ($path === '') {
            return response()->json(['ok' => false]);
        }

        $sessionId = substr((string) $request->query('session_id', ''), 0, 64);
        if ($sessionId === '') {
            $sessionId = (string) Str::uuid();
        }

        $referrer = substr((string) $request->query('referrer', ''), 0, 255);
        $userAgent = substr((string) $request->userAgent(), 0, 255);

        DB::table('page_views')->insert([
            'uuid' => (string) Str::uuid(),
            'user_id' => $request->user('sanctum')?->id,
            'session_id' => $sessionId,
            'path' => $path,
            'referrer' => $referrer !== '' ? $referrer : null,
            'user_agent' => $userAgent !== '' ? $userAgent : null,
            'ip' => $request->ip(),
            'device' => $this->detectDevice($userAgent),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'session_id' => $sessionId]);
    }

    /**
     * Snapshot analytics lengkap (JSON) untuk dashboard admin.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->analyticsPayload());
    }

    /**
     * Payload analytics lengkap: KPI, deret waktu, halaman populer, perangkat,
     * dan feed aktivitas pengguna terbaru.
     *
     * @return array<string, mixed>
     */
    private function analyticsPayload(): array
    {
        $now = now();

        return [
            'generated_at' => $now->toIso8601String(),
            'overview' => $this->overview($now),
            'series' => [
                'hourly' => $this->seriesHourly($now),
                'daily' => $this->seriesDaily($now),
                'weekly' => $this->seriesWeekly($now),
                'monthly' => $this->seriesMonthly($now),
            ],
            'active_users' => $this->activeUsers($now),
            'session' => $this->sessionMetrics($now),
            'downloads' => $this->downloads($now),
            'devices' => $this->devices(),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function overview(\Illuminate\Support\Carbon $now): array
    {
        $startOfToday = $now->copy()->startOfDay();
        $endOfToday = $now->copy()->addDay()->startOfDay();

        return [
            'page_views_today' => (int) DB::table('page_views')
                ->where('created_at', '>=', $startOfToday)
                ->where('created_at', '<', $endOfToday)
                ->count(),
            'page_views_total' => (int) DB::table('page_views')->count(),
            'unique_visitors_today' => (int) DB::table('page_views')
                ->where('created_at', '>=', $startOfToday)
                ->where('created_at', '<', $endOfToday)
                ->distinct()
                ->count('session_id'),
            'active_users_today' => (int) DB::table('page_views')
                ->where('created_at', '>=', $startOfToday)
                ->where('created_at', '<', $endOfToday)
                ->whereNotNull('user_id')
                ->distinct()
                ->count('user_id'),
            'new_users_today' => (int) DB::table('users')
                ->where('created_at', '>=', $startOfToday)
                ->where('created_at', '<', $endOfToday)
                ->count(),
            'total_users' => (int) DB::table('users')->count(),
            'projects_total' => (int) DB::table('projects')->count(),
            'exports_total' => (int) DB::table('audit_logs')->where('action', 'export_pdf')->count(),
            'revenue_paid' => (float) DB::table('payments')->where('status', 'paid')->sum('amount'),
        ];
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    private function seriesHourly(\Illuminate\Support\Carbon $now): array
    {
        $rows = DB::table('page_views')
            ->where('created_at', '>=', $now->copy()->subHours(23)->startOfHour())
            ->select(DB::raw("to_char(created_at, 'YYYY-MM-DD HH24') as bucket"), DB::raw('count(*) as c'))
            ->groupBy(DB::raw("to_char(created_at, 'YYYY-MM-DD HH24')"))
            ->pluck('c', 'bucket');

        $out = [];
        for ($i = 23; $i >= 0; $i--) {
            $t = $now->copy()->subHours($i)->startOfHour();
            $out[] = [
                'label' => $t->format('H:00'),
                'value' => (int) ($rows[$t->format('Y-m-d H')] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    private function seriesDaily(\Illuminate\Support\Carbon $now): array
    {
        $rows = DB::table('page_views')
            ->where('created_at', '>=', $now->copy()->subDays(29)->startOfDay())
            ->select(DB::raw("to_char(created_at, 'YYYY-MM-DD') as bucket"), DB::raw('count(*) as c'))
            ->groupBy(DB::raw("to_char(created_at, 'YYYY-MM-DD')"))
            ->pluck('c', 'bucket');

        $out = [];
        for ($i = 29; $i >= 0; $i--) {
            $t = $now->copy()->subDays($i)->startOfDay();
            $out[] = [
                'label' => $t->format('j').' '.self::MONTHS[$t->month - 1],
                'value' => (int) ($rows[$t->format('Y-m-d')] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    private function seriesWeekly(\Illuminate\Support\Carbon $now): array
    {
        $rows = DB::table('page_views')
            ->where('created_at', '>=', $now->copy()->subWeeks(11)->startOfWeek())
            ->select(DB::raw("date_trunc('week', created_at)::date as bucket"), DB::raw('count(*) as c'))
            ->groupBy(DB::raw("date_trunc('week', created_at)::date"))
            ->pluck('c', 'bucket');

        $out = [];
        for ($i = 11; $i >= 0; $i--) {
            $t = $now->copy()->subWeeks($i)->startOfWeek();
            $out[] = [
                'label' => $t->format('j').' '.self::MONTHS[$t->month - 1],
                'value' => (int) ($rows[$t->format('Y-m-d')] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    private function seriesMonthly(\Illuminate\Support\Carbon $now): array
    {
        $rows = DB::table('page_views')
            ->where('created_at', '>=', $now->copy()->subMonths(11)->startOfMonth())
            ->select(DB::raw("to_char(created_at, 'YYYY-MM') as bucket"), DB::raw('count(*) as c'))
            ->groupBy(DB::raw("to_char(created_at, 'YYYY-MM')"))
            ->pluck('c', 'bucket');

        $out = [];
        for ($i = 11; $i >= 0; $i--) {
            $t = $now->copy()->subMonths($i)->startOfMonth();
            $out[] = [
                'label' => self::MONTHS[$t->month - 1],
                'value' => (int) ($rows[$t->format('Y-m')] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Pengguna aktif (login) per jam (24 jam) dan per hari (30 hari).
     *
     * @return array{today: int, hourly: array<int, array{label: string, value: int}>, daily: array<int, array{label: string, value: int}>}
     */
    private function activeUsers(\Illuminate\Support\Carbon $now): array
    {
        $startOfToday = $now->copy()->startOfDay();
        $endOfToday = $now->copy()->addDay()->startOfDay();

        $today = (int) DB::table('page_views')
            ->where('created_at', '>=', $startOfToday)
            ->where('created_at', '<', $endOfToday)
            ->whereNotNull('user_id')
            ->distinct()
            ->count('user_id');

        $hourlyRows = DB::table('page_views')
            ->where('created_at', '>=', $now->copy()->subHours(23)->startOfHour())
            ->whereNotNull('user_id')
            ->select(DB::raw("to_char(created_at, 'YYYY-MM-DD HH24') as bucket"), DB::raw('count(distinct user_id) as c'))
            ->groupBy(DB::raw("to_char(created_at, 'YYYY-MM-DD HH24')"))
            ->pluck('c', 'bucket');

        $hourly = [];
        for ($i = 23; $i >= 0; $i--) {
            $t = $now->copy()->subHours($i)->startOfHour();
            $hourly[] = [
                'label' => $t->format('H:00'),
                'value' => (int) ($hourlyRows[$t->format('Y-m-d H')] ?? 0),
            ];
        }

        $dailyRows = DB::table('page_views')
            ->where('created_at', '>=', $now->copy()->subDays(29)->startOfDay())
            ->whereNotNull('user_id')
            ->select(DB::raw("to_char(created_at, 'YYYY-MM-DD') as bucket"), DB::raw('count(distinct user_id) as c'))
            ->groupBy(DB::raw("to_char(created_at, 'YYYY-MM-DD')"))
            ->pluck('c', 'bucket');

        $daily = [];
        for ($i = 29; $i >= 0; $i--) {
            $t = $now->copy()->subDays($i)->startOfDay();
            $daily[] = [
                'label' => $t->format('j').' '.self::MONTHS[$t->month - 1],
                'value' => (int) ($dailyRows[$t->format('Y-m-d')] ?? 0),
            ];
        }

        return ['today' => $today, 'hourly' => $hourly, 'daily' => $daily];
    }

    /**
     * Durasi sesi pengguna (lama membuka aplikasi) berdasarkan kunjungan 7 hari terakhir.
     *
     * @return array{average_duration_seconds: int, average_duration_label: string, total_sessions: int, bounce_sessions: int}
     */
    private function sessionMetrics(\Illuminate\Support\Carbon $now): array
    {
        $since = $now->copy()->subDays(7)->startOfDay();

        $totalSessions = (int) DB::table('page_views')
            ->where('created_at', '>=', $since)
            ->distinct()
            ->count('session_id');

        $rows = DB::table('page_views')
            ->where('created_at', '>=', $since)
            ->select('session_id', DB::raw('min(created_at) as started'), DB::raw('max(created_at) as ended'), DB::raw('count(*) as views'))
            ->groupBy('session_id')
            ->get();

        $totalDuration = 0;
        $multi = 0;
        $bounce = 0;
        foreach ($rows as $r) {
            $views = (int) $r->views;
            if ($views <= 1) {
                $bounce++;
                continue;
            }
            $multi++;
            $totalDuration += \Illuminate\Support\Carbon::parse($r->started, 'UTC')
                ->diffInSeconds(\Illuminate\Support\Carbon::parse($r->ended, 'UTC'));
        }
        $avg = $multi > 0 ? (int) round($totalDuration / $multi) : 0;

        return [
            'average_duration_seconds' => $avg,
            'average_duration_label' => $this->formatDuration($avg),
            'total_sessions' => $totalSessions,
            'bounce_sessions' => $bounce,
        ];
    }

    /**
     * Traffic unduhan (export PDF) — total, hari ini, dan deret 30 hari.
     *
     * @return array{total: int, today: int, daily: array<int, array{label: string, value: int}>}
     */
    private function downloads(\Illuminate\Support\Carbon $now): array
    {
        $total = (int) DB::table('audit_logs')->where('action', 'export_pdf')->count();
        $today = (int) DB::table('audit_logs')
            ->where('action', 'export_pdf')
            ->where('created_at', '>=', $now->copy()->startOfDay())
            ->count();

        $rows = DB::table('audit_logs')
            ->where('action', 'export_pdf')
            ->where('created_at', '>=', $now->copy()->subDays(29)->startOfDay())
            ->select(DB::raw("to_char(created_at, 'YYYY-MM-DD') as bucket"), DB::raw('count(*) as c'))
            ->groupBy(DB::raw("to_char(created_at, 'YYYY-MM-DD')"))
            ->pluck('c', 'bucket');

        $daily = [];
        for ($i = 29; $i >= 0; $i--) {
            $t = $now->copy()->subDays($i)->startOfDay();
            $daily[] = [
                'label' => $t->format('j').' '.self::MONTHS[$t->month - 1],
                'value' => (int) ($rows[$t->format('Y-m-d')] ?? 0),
            ];
        }

        return ['total' => $total, 'today' => $today, 'daily' => $daily];
    }

    /**
     * Format durasi detik menjadi label ringkas, mis. "3m 42s" / "1j 5m".
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' dtk';
        }
        if ($seconds < 3600) {
            return intdiv($seconds, 60).'m '.($seconds % 60).'s';
        }

        return intdiv($seconds, 3600).'j '.intdiv($seconds % 3600, 60).'m';
    }

    /**
     * Halaman terpopuler (server-side, paginated).
     */
    public function topPages(Request $request): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 10)));

        $page = DB::table('page_views')
            ->select('path', DB::raw('count(*) as views'))
            ->groupBy('path')
            ->orderByDesc('views')
            ->paginate($perPage);

        $data = collect($page->items())
            ->map(fn ($r) => ['path' => $r->path, 'views' => (int) $r->views])
            ->values()
            ->all();

        return response()->json([
            'data' => $data,
            'pagination' => $this->pagination($page),
        ]);
    }

    /**
     * @return array<int, array{device: string, label: string, count: int}>
     */
    private function devices(): array
    {
        $rows = DB::table('page_views')
            ->select('device', DB::raw('count(*) as c'))
            ->whereNotNull('device')
            ->groupBy('device')
            ->orderByDesc('c')
            ->pluck('c', 'device');

        $labels = ['desktop' => 'Desktop', 'mobile' => 'Mobile', 'tablet' => 'Tablet'];
        $out = [];
        foreach (['desktop', 'mobile', 'tablet'] as $device) {
            if (isset($rows[$device])) {
                $out[] = [
                    'device' => $device,
                    'label' => $labels[$device],
                    'count' => (int) $rows[$device],
                ];
            }
        }

        return $out;
    }

    /**
     * Feed aktivitas pengguna (server-side, paginated + filter tab & user).
     */
    public function activity(Request $request): JsonResponse
    {
        $type = (string) $request->query('type', '');
        $user = trim((string) $request->query('user', ''));
        $perPage = min(50, max(1, (int) $request->query('per_page', 5)));

        $union = $this->activityQuery();
        $query = DB::table(DB::raw("({$union->toSql()}) as activity"))
            ->mergeBindings($union);

        if (in_array($type, ['view', 'export', 'project', 'payment'], true)) {
            $query->where('type', $type);
        }
        if ($user !== '') {
            $query->where('username', 'ilike', '%'.$user.'%');
        }

        $page = $query->orderBy('at', 'desc')->paginate($perPage);

        $data = collect($page->items())
            ->map(function ($r) {
                return [
                    'at' => $this->toIso($r->at),
                    'user' => $r->username,
                    'action' => $r->action,
                    'detail' => $r->detail,
                    'type' => $r->type,
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'data' => $data,
            'pagination' => $this->pagination($page),
        ]);
    }

    /**
     * Subquery gabungan (UNION ALL) sumber aktivitas: kunjungan, export,
     * project dibuat, dan pembayaran.
     */
    private function activityQuery()
    {
        $views = DB::table('page_views')
            ->select([
                DB::raw("page_views.created_at as at"),
                DB::raw("coalesce(users.name, 'Tamu') as username"),
                DB::raw("'Mengunjungi' as action"),
                DB::raw('page_views.path as detail'),
                DB::raw("'view' as type"),
            ])
            ->leftJoin('users', 'users.id', '=', 'page_views.user_id');

        $exports = DB::table('audit_logs')
            ->select([
                DB::raw('audit_logs.created_at as at'),
                DB::raw("coalesce(users.name, 'Sistem') as username"),
                DB::raw("'Mengekspor PDF' as action"),
                DB::raw("coalesce(audit_logs.after->>'project', '') as detail"),
                DB::raw("'export' as type"),
            ])
            ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->where('audit_logs.action', 'export_pdf');

        $projects = DB::table('projects')
            ->select([
                DB::raw('projects.created_at as at'),
                DB::raw("coalesce(users.name, 'Tamu') as username"),
                DB::raw("'Membuat project' as action"),
                DB::raw('projects.title as detail'),
                DB::raw("'project' as type"),
            ])
            ->leftJoin('users', 'users.id', '=', 'projects.user_id');

        $payments = DB::table('payments')
            ->select([
                DB::raw('payments.created_at as at'),
                DB::raw("coalesce(users.name, 'Tamu') as username"),
                DB::raw("'Melakukan pembayaran' as action"),
                DB::raw("'Rp ' || to_char(payments.amount, 'FM999G999G999') as detail"),
                DB::raw("'payment' as type"),
            ])
            ->leftJoin('users', 'users.id', '=', 'payments.user_id')
            ->where('payments.status', 'paid');

        return $views->unionAll($exports)->unionAll($projects)->unionAll($payments);
    }

    /**
     * Normalisasi meta pagination menjadi array ringkas.
     *
     * @return array{current_page: int, last_page: int, per_page: int, total: int}
     */
    private function pagination($page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ];
    }

    /**
     * Normalisasi timestamp (string UTC dari DB) menjadi ISO 8601 agar waktu
     * tampil benar di frontend tanpa selisih zona.
     */
    private function toIso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse($value, 'UTC')->toIso8601String();
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    /**
     * Deteksi jenis perangkat dari user-agent.
     */
    private function detectDevice(string $ua): ?string
    {
        if ($ua === '') {
            return null;
        }
        $ua = strtolower($ua);
        if (preg_match('/(tablet|ipad|playbook|silk)/', $ua)) {
            return 'tablet';
        }
        if (preg_match('/mobile|iphone|ipod|android|blackberry|opera mini|windows phone/', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }
}
