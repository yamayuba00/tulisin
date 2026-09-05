<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PromoEmailNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class NotificationController extends Controller
{
    /**
     * Ambil pengaturan notifikasi admin.
     */
    public function settings(Request $request): JsonResponse
    {
        $setting = Setting::where('key', 'notifications')->first();
        $value = $setting ? ($setting->value ?? []) : [];

        return response()->json([
            'admin_email' => $value['admin_email'] ?? '',
            'notify_payment' => (bool) ($value['notify_payment'] ?? true),
            'promo_enabled' => (bool) ($value['promo_enabled'] ?? true),
        ]);
    }

    /**
     * Simpan pengaturan notifikasi admin.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'admin_email' => ['nullable', 'email'],
            'notify_payment' => ['required', 'boolean'],
            'promo_enabled' => ['required', 'boolean'],
        ]);

        Setting::updateOrCreate(
            ['key' => 'notifications'],
            ['value' => [
                'admin_email' => $data['admin_email'] ?? null,
                'notify_payment' => (bool) $data['notify_payment'],
                'promo_enabled' => (bool) $data['promo_enabled'],
            ]],
        );

        return response()->json([
            'message' => 'Pengaturan notifikasi berhasil disimpan.',
            'settings' => $data,
        ]);
    }

    /**
     * Kirim email blast promo ke seluruh pengguna.
     */
    public function emailBlast(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $users = User::whereNotNull('email')->get();

        Notification::send($users, new PromoEmailNotification(
            (string) $data['subject'],
            (string) $data['message'],
        ));

        return response()->json([
            'message' => 'Email promo sedang dikirim.',
            'recipients' => $users->count(),
        ]);
    }
}
