<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed aplikasi dalam keadaan bersih:
     * - Hapus seluruh data (kecuali tabel migrasi/framework).
     * - Buat super-admin & brand ambassador.
     * - Seed pengaturan koin + pembayaran/langganan agar aplikasi tetap jalan.
     *
     * Jalankan dengan: php artisan db:seed
     * (atau dari nol: php artisan migrate:fresh --seed)
     */
    public function run(): void
    {
        $this->truncateAllTables();

        $this->call(RolePermissionSeeder::class);

        $this->seedSettings();

        $admin = $this->createAdmin();
        $ba = $this->createBrandAmbassador();

        $this->seedCoins($admin, 1000);
        $this->seedCoins($ba, 250);

        $this->seedSubscription($ba, 30000);

        $this->command->info('Super Admin      : admin@tulissin.com / password');
        $this->command->info('Brand Ambassador : ba@tulissin.com / password');
    }

    /**
     * Kosongkan semua tabel aplikasi, kecuali tabel migrasi & framework.
     */
    private function truncateAllTables(): void
    {
        $skip = ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->truncatePostgres($skip);

            return;
        }

        // Fallback MySQL/MariaDB.
        Schema::disableForeignKeyConstraints();

        foreach (Schema::getAllTables() as $table) {
            $name = array_values((array) $table)[0] ?? null;

            if (! $name || in_array($name, $skip, true)) {
                continue;
            }

            DB::table($name)->truncate();
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Truncate semua tabel PostgreSQL sekaligus (CASCADE mengikuti relasi FK).
     */
    private function truncatePostgres(array $skip): void
    {
        $rows = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");

        $names = [];
        foreach ($rows as $row) {
            $name = $row->tablename;

            if (! in_array($name, $skip, true)) {
                $names[] = $name;
            }
        }

        if (empty($names)) {
            return;
        }

        $quoted = array_map(fn ($n) => '"' . $n . '"', $names);
        DB::statement('TRUNCATE ' . implode(', ', $quoted) . ' RESTART IDENTITY CASCADE');
    }

    /**
     * Seed pengaturan dinamis agar tarif koin & harga langganan selalu tersedia.
     */
    private function seedSettings(): void
    {
        Setting::updateOrCreate(
            ['key' => 'credit_pricing'],
            ['value' => config('credits.pricing', [])],
        );

        Setting::updateOrCreate(
            ['key' => 'subscription'],
            ['value' => ['monthly_price' => 30000]],
        );
    }

    private function createAdmin(): User
    {
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@tulissin.com',
            'password' => 'password',
            'phone' => '081234567890',
            'status' => 'active',
        ]);

        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->roles()->sync([Role::where('name', 'super-admin')->value('id')]);

        return $admin;
    }

    private function createBrandAmbassador(): User
    {
        $ba = User::create([
            'name' => 'Brand Ambassador',
            'email' => 'ba@tulissin.com',
            'password' => 'password',
            'phone' => '081298765432',
            'status' => 'active',
        ]);

        $ba->forceFill(['email_verified_at' => now()])->save();
        $ba->roles()->sync([Role::where('name', 'brand-ambassador')->value('id')]);

        UserProfile::create([
            'user_id' => $ba->id,
            'university' => 'Universitas Contoh',
            'major' => 'Skripsi, Tesis, Jurnal',
        ]);

        return $ba;
    }

    /**
     * Seed saldo koin (wallet + transaksi awal).
     */
    private function seedCoins(User $user, int $amount): void
    {
        $wallet = Wallet::firstOrCreate(['user_id' => $user->id]);
        $wallet->credit($amount, 'seed_saldo_awal');
    }

    /**
     * Seed pembayaran langganan yang sudah lunas + langganan aktif.
     */
    private function seedSubscription(User $user, int $price): void
    {
        $fee = 2000;

        $payment = Payment::create([
            'user_id' => $user->id,
            'invoice_number' => 'INV-SEED-' . strtoupper(explode('@', $user->email)[0]) . '-001',
            'amount' => $price + $fee,
            'fee' => $fee,
            'method' => 'QRIS',
            'provider' => 'sumopod',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        Subscription::create([
            'user_id' => $user->id,
            'payment_id' => $payment->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(Subscription::PERIOD_DAYS),
            'price' => $price,
            'payment_method' => 'QRIS',
        ]);
    }
}
