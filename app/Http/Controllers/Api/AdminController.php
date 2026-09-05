<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use App\Models\Project;
use App\Models\ProjectAiResult;
use App\Models\Referral;
use App\Models\Role;
use App\Models\SharedDocument;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Ringkasan analytics, traffic, dan penggunaan fitur untuk dashboard super-admin.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $paidRevenue = (float) DB::table('payments')->where('status', 'paid')->sum('amount');

        $stats = [
            [
                'label' => 'Total Users',
                'value' => User::count(),
                'hint' => 'Akun terdaftar',
            ],
            [
                'label' => 'Total Project',
                'value' => DB::table('projects')->count(),
                'hint' => 'Semua dokumen',
            ],
            [
                'label' => 'Total Transaksi',
                'value' => 'Rp ' . number_format($paidRevenue, 0, ',', '.'),
                'hint' => 'Pendapatan (paid)',
            ],
            [
                'label' => 'Tiket Terbuka',
                'value' => DB::table('tickets')->where('status', 'open')->count(),
                'hint' => 'Perlu respons',
            ],
            [
                'label' => 'Koin Beredar',
                'value' => (int) DB::table('wallets')->sum('balance'),
                'hint' => 'Saldo semua wallet',
            ],
            [
                'label' => 'Scan AI',
                'value' => DB::table('project_ai_results')->count(),
                'hint' => 'Turnitin & plagiarism',
            ],
            [
                'label' => 'Dokumen Dibagikan',
                'value' => DB::table('shared_documents')->count(),
                'hint' => 'Link berbagi',
            ],
            [
                'label' => 'Export PDF',
                'value' => DB::table('audit_logs')->where('action', 'export_pdf')->count(),
                'hint' => 'Total unduhan PDF',
            ],
        ];

        $traffic = [
            'page_views' => DB::table('page_views')->count(),
            'unique_sessions' => DB::table('page_views')->distinct()->count('session_id'),
            'recent' => DB::table('page_views')
                ->leftJoin('users', 'users.id', '=', 'page_views.user_id')
                ->select('page_views.path', 'page_views.device', 'page_views.created_at', 'users.name as user_name')
                ->latest('page_views.created_at')
                ->limit(10)
                ->get(),
            'top_pages' => DB::table('page_views')
                ->select('path', DB::raw('count(*) as views'))
                ->groupBy('path')
                ->orderByDesc('views')
                ->limit(5)
                ->get(),
        ];

        return response()->json([
            'stats' => $stats,
            'traffic' => $traffic,
        ]);
    }

    /**
     * Daftar semua pengguna beserta role-nya.
     */
    public function users(Request $request): JsonResponse
    {
        $users = User::with('roles:id,name')
            ->latest()
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'uuid' => $u->uuid,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'status' => $u->status,
                'is_super_admin' => $u->isSuperAdmin(),
                'roles' => $u->roles->pluck('name')->all(),
                'created_at' => $u->created_at?->toISOString(),
            ]);

        return response()->json([
            'total' => $users->count(),
            'users' => $users,
        ]);
    }

    /**
     * Perbarui status/role pengguna (suspend/aktifkan, ganti role).
     */
    public function updateUser(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $admin = $request->user();

        if ($user->id === $admin->id) {
            return response()->json(['error' => 'Anda tidak dapat mengubah akun sendiri.'], 422);
        }

        $data = $request->validate([
            'status' => ['sometimes', 'string', 'in:active,suspended,pending'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        if (array_key_exists('status', $data)) {
            $user->status = $data['status'];
            $user->save();
        }

        if (array_key_exists('roles', $data)) {
            $roleIds = Role::whereIn('name', $data['roles'])->pluck('id');
            $user->roles()->sync($roleIds);
        }

        return response()->json([
            'message' => 'Pengguna diperbarui.',
            'user' => $this->userSummary($user),
        ]);
    }

    /**
     * Daftar role beserta jumlah permission & pengguna.
     */
    public function roles(Request $request): JsonResponse
    {
        $roles = Role::withCount(['permissions', 'users'])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $r) => [
                'id' => $r->id,
                'uuid' => $r->uuid,
                'name' => $r->name,
                'description' => $r->description,
                'permissions_count' => $r->permissions_count,
                'users_count' => $r->users_count,
            ]);

        return response()->json(['roles' => $roles]);
    }

    /**
     * Daftar pengajuan kredit (untuk verifikasi).
     */
    public function creditSubmissions(Request $request): JsonResponse
    {
        $submissions = DB::table('credit_submissions')
            ->join('users', 'users.id', '=', 'credit_submissions.user_id')
            ->leftJoin('users as reviewer', 'reviewer.id', '=', 'credit_submissions.reviewed_by')
            ->select(
                'credit_submissions.*',
                'users.name as user_name',
                'users.email as user_email',
                'reviewer.name as reviewer_name',
            )
            ->latest('credit_submissions.created_at')
            ->get();

        return response()->json([
            'total' => $submissions->count(),
            'submissions' => $submissions,
        ]);
    }

    /**
     * Setujui / tolak pengajuan koin, lalu kreditkan saldo bila disetujui.
     */
    public function reviewCreditSubmission(Request $request, int $id): JsonResponse
    {
        $submission = DB::table('credit_submissions')->where('id', $id)->first();

        if (! $submission) {
            return response()->json(['error' => 'Pengajuan tidak ditemukan.'], 404);
        }

        if ($submission->status !== 'pending') {
            return response()->json(['error' => 'Pengajuan ini sudah diproses.'], 422);
        }

        $data = $request->validate([
            'decision' => ['required', 'string', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:500'],
            'credits' => ['sometimes', 'integer', 'min:0'],
        ]);

        $approved = $data['decision'] === 'approve';
        $credits = array_key_exists('credits', $data)
            ? (int) $data['credits']
            : (int) $submission->credits_awarded;

        if ($approved && $credits > 0) {
            $wallet = Wallet::firstOrCreate(['user_id' => $submission->user_id]);
            $wallet->credit($credits, 'credit_submission', 'credit_submission', $id);
        }

        DB::table('credit_submissions')->where('id', $id)->update([
            'status' => $approved ? 'approved' : 'rejected',
            'credits_awarded' => $approved ? $credits : (int) $submission->credits_awarded,
            'reviewed_by' => $request->user()->id,
            'review_note' => $data['note'] ?? null,
            'reviewed_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => $approved ? 'Pengajuan koin disetujui.' : 'Pengajuan koin ditolak.',
        ]);
    }

    /**
     * Daftar semua project beserta pemilik & jumlah riwayat.
     */
    public function projects(Request $request): JsonResponse
    {
        $projects = Project::with('user:id,name,email')
            ->withCount(['revisions', 'aiResults'])
            ->latest()
            ->get()
            ->map(fn (Project $p) => [
                'id' => $p->id,
                'uuid' => $p->uuid,
                'title' => $p->title,
                'category' => $p->category,
                'format' => $p->format,
                'status' => $p->status,
                'is_public' => $p->is_public,
                'version' => $p->version,
                'user_name' => $p->user?->name,
                'user_email' => $p->user?->email,
                'revisions_count' => $p->revisions_count,
                'ai_results_count' => $p->ai_results_count,
                'created_at' => $p->created_at?->toISOString(),
                'updated_at' => $p->updated_at?->toISOString(),
            ]);

        return response()->json([
            'total' => $projects->count(),
            'projects' => $projects,
        ]);
    }

    /**
     * Daftar seluruh hasil scan AI (turnitin / plagiarism).
     */
    public function aiResults(Request $request): JsonResponse
    {
        $results = ProjectAiResult::with(['project:id,title,uuid,user_id', 'project.user:id,name,email'])
            ->latest()
            ->get()
            ->map(fn (ProjectAiResult $r) => [
                'id' => $r->id,
                'type' => $r->type,
                'score' => $r->score,
                'matches_count' => is_array($r->matches) ? count($r->matches) : 0,
                'project_title' => $r->project?->title,
                'project_uuid' => $r->project?->uuid,
                'user_name' => $r->project?->user?->name,
                'created_at' => $r->created_at?->toISOString(),
            ]);

        return response()->json([
            'total' => $results->count(),
            'results' => $results,
        ]);
    }

    /**
     * Riwayat seluruh pergerakan koin (credit/debit) di platform.
     */
    public function creditTransactions(Request $request): JsonResponse
    {
        $transactions = CreditTransaction::with('user:id,name,email')
            ->latest()
            ->get()
            ->map(fn (CreditTransaction $t) => [
                'id' => $t->id,
                'type' => $t->type,
                'amount' => $t->amount,
                'balance_after' => $t->balance_after,
                'reason' => $t->reason,
                'reference_type' => $t->reference_type,
                'user_name' => $t->user?->name,
                'user_email' => $t->user?->email,
                'created_at' => $t->created_at?->toISOString(),
            ]);

        return response()->json([
            'total' => $transactions->count(),
            'transactions' => $transactions,
        ]);
    }

    /**
     * Daftar dokumen yang dibagikan (shared link).
     */
    public function sharedDocuments(Request $request): JsonResponse
    {
        $documents = SharedDocument::with('user:id,name,email')
            ->latest()
            ->get()
            ->map(fn (SharedDocument $d) => [
                'id' => $d->id,
                'uuid' => $d->uuid,
                'name' => $d->name,
                'state' => $d->state,
                'time_view' => $d->time_view,
                'expires_at' => $d->expires_at?->toISOString(),
                'project_uuid' => $d->project_uuid,
                'user_name' => $d->user?->name,
                'user_email' => $d->user?->email,
                'created_at' => $d->created_at?->toISOString(),
            ]);

        return response()->json([
            'total' => $documents->count(),
            'documents' => $documents,
        ]);
    }

    /**
     * Daftar transaksi / pembayaran.
     */
    public function payments(Request $request): JsonResponse
    {
        $payments = DB::table('payments')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->select('payments.*', 'users.name as user_name', 'users.email as user_email')
            ->latest('payments.created_at')
            ->get();

        return response()->json([
            'total' => $payments->count(),
            'payments' => $payments,
        ]);
    }

    /**
     * Daftar order topup (paket koin).
     */
    public function topupOrders(Request $request): JsonResponse
    {
        $orders = DB::table('topup_orders')
            ->join('users', 'users.id', '=', 'topup_orders.user_id')
            ->select('topup_orders.*', 'users.name as user_name', 'users.email as user_email')
            ->latest('topup_orders.created_at')
            ->get();

        return response()->json([
            'total' => $orders->count(),
            'orders' => $orders,
        ]);
    }

    /**
     * Riwayat ekspor PDF (dari audit log).
     */
    public function exports(Request $request): JsonResponse
    {
        $exports = DB::table('audit_logs')
            ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->where('audit_logs.action', 'export_pdf')
            ->select('audit_logs.*', 'users.name as user_name')
            ->latest('audit_logs.created_at')
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'user_name' => $log->user_name,
                'project' => ($after = json_decode($log->after ?? '{}', true)) ? ($after['project'] ?? null) : null,
                'format' => $after['format'] ?? 'pdf',
                'html_length' => $after['html_length'] ?? null,
                'created_at' => $log->created_at,
            ]);

        return response()->json([
            'total' => $exports->count(),
            'exports' => $exports,
        ]);
    }

    /**
     * Daftar kode referral & komisi affiliate.
     */
    public function affiliates(Request $request): JsonResponse
    {
        $codes = DB::table('referral_codes')
            ->join('users', 'users.id', '=', 'referral_codes.user_id')
            ->select('referral_codes.*', 'users.name as user_name', 'users.email as user_email')
            ->latest('referral_codes.created_at')
            ->get();

        $commissions = DB::table('affiliate_commissions')
            ->join('users', 'users.id', '=', 'affiliate_commissions.affiliate_id')
            ->select('affiliate_commissions.*', 'users.name as affiliate_name')
            ->latest('affiliate_commissions.created_at')
            ->get();

        return response()->json([
            'codes' => $codes,
            'commissions' => $commissions,
        ]);
    }

    /**
     * Setujui / tolak komisi affiliate.
     */
    public function reviewCommission(Request $request, int $id): JsonResponse
    {
        $commission = DB::table('affiliate_commissions')->where('id', $id)->first();

        if (! $commission) {
            return response()->json(['error' => 'Komisi tidak ditemukan.'], 404);
        }

        if ($commission->status !== 'pending') {
            return response()->json(['error' => 'Komisi ini sudah diproses.'], 422);
        }

        $data = $request->validate([
            'decision' => ['required', 'string', 'in:approve,reject'],
        ]);

        DB::table('affiliate_commissions')->where('id', $id)->update([
            'status' => $data['decision'] === 'approve' ? 'approved' : 'rejected',
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => $data['decision'] === 'approve' ? 'Komisi disetujui.' : 'Komisi ditolak.',
        ]);
    }

    /**
     * Daftar referral (pendaftaran via kode afiliasi) untuk verifikasi.
     */
    public function referrals(Request $request): JsonResponse
    {
        $referrals = DB::table('referrals')
            ->join('users as referrer', 'referrer.id', '=', 'referrals.referrer_id')
            ->leftJoin('users as referred', 'referred.id', '=', 'referrals.referred_user_id')
            ->select(
                'referrals.*',
                'referrer.name as referrer_name',
                'referrer.email as referrer_email',
                'referred.name as referred_name',
            )
            ->latest('referrals.created_at')
            ->get();

        return response()->json([
            'total' => $referrals->count(),
            'referrals' => $referrals,
        ]);
    }

    /**
     * Setujui / tolak referral. +20 koin hanya diberikan setelah disetujui.
     */
    public function reviewReferral(Request $request, int $id): JsonResponse
    {
        $referral = DB::table('referrals')->where('id', $id)->first();

        if (! $referral) {
            return response()->json(['error' => 'Referral tidak ditemukan.'], 404);
        }

        if (! in_array($referral->status, ['pending', 'registered'], true)) {
            return response()->json(['error' => 'Referral ini sudah diproses.'], 422);
        }

        $data = $request->validate([
            'decision' => ['required', 'string', 'in:approve,reject'],
        ]);

        if ($data['decision'] === 'approve') {
            $wallet = Wallet::firstOrCreate(['user_id' => $referral->referrer_id]);
            $wallet->credit(Referral::CREDIT_PER_REFERRAL, 'affiliate_referral', 'referral', $referral->id);

            DB::table('referrals')->where('id', $id)->update([
                'status' => 'approved',
                'updated_at' => now(),
            ]);
        } else {
            DB::table('referrals')->where('id', $id)->update([
                'status' => 'rejected',
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'message' => $data['decision'] === 'approve' ? 'Referral disetujui, koin ditambahkan.' : 'Referral ditolak.',
        ]);
    }

    /**
     * Daftar tiket dukungan.
     */
    public function tickets(Request $request): JsonResponse
    {
        $tickets = DB::table('tickets')
            ->join('users', 'users.id', '=', 'tickets.user_id')
            ->leftJoin('users as assignee', 'assignee.id', '=', 'tickets.assigned_to')
            ->select(
                'tickets.*',
                'users.name as user_name',
                'users.email as user_email',
                'assignee.name as assignee_name',
            )
            ->latest('tickets.created_at')
            ->get();

        return response()->json([
            'total' => $tickets->count(),
            'tickets' => $tickets,
        ]);
    }

    /**
     * Perbarui status / penugasan tiket.
     */
    public function updateTicket(Request $request, int $id): JsonResponse
    {
        $ticket = DB::table('tickets')->where('id', $id)->first();

        if (! $ticket) {
            return response()->json(['error' => 'Tiket tidak ditemukan.'], 404);
        }

        $data = $request->validate([
            'status' => ['sometimes', 'string', 'in:open,pending,closed'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        $updates = [];
        if (array_key_exists('status', $data)) {
            $updates['status'] = $data['status'];
        }
        if (array_key_exists('assigned_to', $data)) {
            $updates['assigned_to'] = $data['assigned_to'];
        }
        $updates['updated_at'] = now();

        DB::table('tickets')->where('id', $id)->update($updates);

        return response()->json(['message' => 'Tiket diperbarui.']);
    }

    /**
     * Daftar log audit.
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $logs = DB::table('audit_logs')
            ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->select('audit_logs.*', 'users.name as user_name')
            ->latest('audit_logs.created_at')
            ->get();

        return response()->json(['logs' => $logs]);
    }

    /**
     * Ringkas user agar konsisten dengan payload daftar users.
     *
     * @return array<string, mixed>
     */
    private function userSummary(User $user): array
    {
        return [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'is_super_admin' => $user->isSuperAdmin(),
            'roles' => $user->roles()->pluck('name')->all(),
            'created_at' => $user->created_at?->toISOString(),
        ];
    }
}
