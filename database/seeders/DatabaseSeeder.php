<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        // Super-admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@tulisin.id'],
            ['name' => 'Super Admin', 'password' => 'password', 'status' => 'active'],
        );
        $admin->roles()->sync([Role::where('name', 'super-admin')->value('id')]);

        // User biasa
        $user = User::firstOrCreate(
            ['email' => 'user@tulisin.id'],
            ['name' => 'Mahasiswa Demo', 'password' => 'password', 'status' => 'active'],
        );
        $user->roles()->sync([Role::where('name', 'user')->value('id')]);

        $this->seedDemoData($admin->id, $user->id);

        $this->command->info('Super admin : admin@tulisin.id / password');
        $this->command->info('User biasa  : user@tulisin.id / password');
    }

    /**
     * Isi data contoh (hanya jika tabel masih kosong) agar panel admin terlihat.
     */
    private function seedDemoData(int $adminId, int $userId): void
    {
        $now = now();

        if (DB::table('page_views')->count() === 0) {
            $paths = [
                ['/', 'desktop'], ['/login', 'mobile'], ['/pricing', 'desktop'],
                ['/', 'mobile'], ['/login', 'desktop'], ['/features', 'desktop'],
            ];
            foreach ($paths as $i => [$path, $device]) {
                DB::table('page_views')->insert([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $i % 2 === 0 ? $userId : null,
                    'session_id' => 'demo-session-'.($i % 3),
                    'path' => $path,
                    'referrer' => null,
                    'user_agent' => 'DemoSeeder',
                    'ip' => '127.0.0.1',
                    'device' => $device,
                    'created_at' => $now->copy()->subMinutes($i * 5),
                    'updated_at' => $now,
                ]);
            }
        }

        if (DB::table('payments')->count() === 0) {
            $payments = [
                ['invoice_number' => 'INV-2026-001', 'amount' => 150000, 'method' => 'qris', 'status' => 'paid'],
                ['invoice_number' => 'INV-2026-002', 'amount' => 50000, 'method' => 'bank_transfer', 'status' => 'pending'],
                ['invoice_number' => 'INV-2026-003', 'amount' => 200000, 'method' => 'ewallet', 'status' => 'paid'],
            ];
            foreach ($payments as $i => $p) {
                DB::table('payments')->insert([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $i % 2 === 0 ? $userId : $adminId,
                    'invoice_number' => $p['invoice_number'],
                    'amount' => $p['amount'],
                    'method' => $p['method'],
                    'provider' => null,
                    'provider_ref' => null,
                    'status' => $p['status'],
                    'paid_at' => $p['status'] === 'paid' ? $now : null,
                    'created_at' => $now->copy()->subDays($i),
                    'updated_at' => $now,
                ]);
            }
        }

        if (DB::table('credit_submissions')->count() === 0) {
            $subs = [
                ['url' => 'https://jurnal.uns.ac.id/artikel/contoh', 'status' => 'pending', 'credits_awarded' => 0],
                ['url' => 'https://medium.com/@user/artikel-riset', 'status' => 'approved', 'credits_awarded' => 25],
            ];
            foreach ($subs as $i => $s) {
                DB::table('credit_submissions')->insert([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $userId,
                    'url' => $s['url'],
                    'notes' => null,
                    'status' => $s['status'],
                    'credits_awarded' => $s['credits_awarded'],
                    'reviewed_by' => $s['status'] === 'approved' ? $adminId : null,
                    'review_note' => null,
                    'reviewed_at' => $s['status'] === 'approved' ? $now : null,
                    'created_at' => $now->copy()->subDays($i + 1),
                    'updated_at' => $now,
                ]);
            }
        }

        if (DB::table('tickets')->count() === 0) {
            $tickets = [
                ['subject' => 'Gagal upload file', 'status' => 'open', 'priority' => 'high'],
                ['subject' => 'Pertanyaan paket koin', 'status' => 'closed', 'priority' => 'normal'],
            ];
            foreach ($tickets as $i => $t) {
                DB::table('tickets')->insert([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $userId,
                    'subject' => $t['subject'],
                    'status' => $t['status'],
                    'priority' => $t['priority'],
                    'assigned_to' => $adminId,
                    'created_at' => $now->copy()->subDays($i),
                    'updated_at' => $now,
                ]);
            }
        }

        if (DB::table('audit_logs')->count() === 0) {
            $logs = [
                ['action' => 'user.login', 'model_type' => 'User'],
                ['action' => 'project.create', 'model_type' => 'Project'],
                ['action' => 'credit.topup', 'model_type' => 'Payment'],
            ];
            foreach ($logs as $i => $l) {
                DB::table('audit_logs')->insert([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $i === 0 ? $userId : $adminId,
                    'action' => $l['action'],
                    'model_type' => $l['model_type'],
                    'model_id' => null,
                    'before' => null,
                    'after' => null,
                    'created_at' => $now->copy()->subHours($i),
                    'updated_at' => $now,
                ]);
            }
        }

        if (DB::table('referral_codes')->count() === 0) {
            DB::table('referral_codes')->insert([
                'uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'code' => 'TULISIN10',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (DB::table('affiliate_commissions')->count() === 0) {
            DB::table('affiliate_commissions')->insert([
                'uuid' => (string) Str::uuid(),
                'affiliate_id' => $userId,
                'referral_id' => null,
                'reference_type' => null,
                'reference_id' => null,
                'amount' => 15000,
                'rate' => 10,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (DB::table('projects')->count() === 0) {
            $projects = [
                ['title' => 'Analisis Sentimen Ulasan Aplikasi', 'category' => 'Skripsi'],
                ['title' => 'Model Prediksi Curah Hujan', 'category' => 'Jurnal'],
            ];
            foreach ($projects as $p) {
                DB::table('projects')->insert([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $userId,
                    'title' => $p['title'],
                    'category' => $p['category'],
                    'description' => null,
                    'format' => 'A4',
                    'orientation' => 'portrait',
                    'status' => 'draft',
                    'is_public' => false,
                    'published_at' => null,
                    'deleted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
