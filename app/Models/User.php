<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Notifications\EmailVerificationNotification;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'uuid', 'phone', 'avatar', 'status', 'last_login_at', 'provider', 'provider_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuid, Notifiable, MustVerifyEmail;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function hasActiveSubscription(): bool
    {
        // Role internal (super-admin & brand ambassador) dianggap selalu punya
        // langganan aktif agar bisa memakai fitur berbayar (AI, PDF, Workspace,
        // Media, Font) tanpa harus melakukan pembelian.
        if ($this->isInternalRole()) {
            return true;
        }

        // Cek keberadaan langganan aktif apa pun, bukan hanya yang terbaru.
        // (Subscription "pending" hasil perpanjangan tidak boleh menutupi
        // langganan aktif yang sudah ada.)
        return Subscription::query()
            ->where('user_id', $this->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->exists();
    }

    /**
     * Beri langganan trial gratis untuk akun baru (sekali saja saat registrasi).
     * Setelah masa trial habis, hasActiveSubscription() otomatis jadi false.
     */
    public function grantTrialSubscription(): void
    {
        $trialDays = (int) config('subscription.trial_days', 14);
        if ($trialDays <= 0) {
            return;
        }

        Subscription::create([
            'user_id' => $this->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays($trialDays),
            'price' => 0,
            'discount_amount' => 0,
            'payment_method' => 'trial',
        ]);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    /**
     * Role internal (super-admin & brand ambassador) mendapat akses gratis
     * ke fitur berbayar tanpa perlu berlangganan ataupun memotong koin.
     */
    public function isInternalRole(): bool
    {
        return $this->isSuperAdmin() || $this->hasRole('brand-ambassador');
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->permissions()->where('name', $permission)->exists()) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($q) => $q->where('name', $permission))
            ->exists();
    }

    /**
     * Kirim notifikasi verifikasi email memakai template Blade kustom.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new EmailVerificationNotification());
    }

    /**
     * Kirim notifikasi reset password memakai tautan ke halaman SPA.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
