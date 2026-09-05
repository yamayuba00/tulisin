<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'super-admin', 'description' => 'Super admin — akses penuh & kelola role'],
            ['name' => 'admin', 'description' => 'Admin operasional'],
            ['name' => 'moderator', 'description' => 'Verifikasi koin & konten'],
            ['name' => 'user', 'description' => 'Mahasiswa / pengguna umum'],
            ['name' => 'writer', 'description' => 'Penulis jasa'],
            ['name' => 'agency', 'description' => 'Agency skripsi / olah data'],
            ['name' => 'affiliate', 'description' => 'Referral / affiliate'],
            ['name' => 'partner', 'description' => 'Kampus / perusahaan (B2B)'],
            ['name' => 'advertiser', 'description' => 'Pengiklan'],
        ];

        foreach ($roles as $r) {
            Role::firstOrCreate(['name' => $r['name']], ['description' => $r['description']]);
        }

        // Katalog permission: {modul} => [permission]
        $modules = [
            'users'        => ['users.view', 'users.manage', 'roles.manage', 'roles.assign'],
            'projects'     => ['projects.create', 'projects.update', 'projects.delete', 'projects.publish', 'projects.view_all'],
            'media'        => ['media.upload', 'media.delete', 'fonts.manage'],
            'credits'      => ['credits.topup', 'credits.view', 'credits.adjust'],
            'payments'     => ['payments.view', 'payments.refund'],
            'submissions'  => ['submissions.create', 'submissions.review'],
            'affiliates'   => ['affiliates.view', 'affiliates.approve', 'affiliates.payout'],
            'services'     => ['services.manage', 'services.order', 'orders.review'],
            'organizations'=> ['organizations.manage', 'subscriptions.manage'],
            'ads'          => ['ads.manage', 'coupons.manage'],
            'support'      => ['tickets.manage', 'audit.view'],
            'monitoring'   => ['analytics.view'],
        ];

        foreach ($modules as $module => $permissions) {
            foreach ($permissions as $permission) {
                Permission::firstOrCreate(['name' => $permission], ['module' => $module]);
            }
        }

        $rolePermissionMap = [
            'super-admin' => '*',
            'admin' => [
                'users.view', 'users.manage', 'roles.assign',
                'projects.view_all', 'projects.publish',
                'credits.view', 'credits.adjust',
                'payments.view', 'payments.refund',
                'submissions.review', 'affiliates.approve', 'affiliates.payout',
                'orders.review', 'tickets.manage', 'audit.view', 'analytics.view', 'notifications.manage',
            ],
            'moderator' => ['projects.view_all', 'projects.publish', 'submissions.review', 'orders.review'],
            'user' => [
                'projects.create', 'projects.update', 'projects.delete',
                'media.upload', 'media.delete',
                'submissions.create', 'credits.topup', 'credits.view', 'services.order',
            ],
            'writer' => ['services.manage', 'projects.create', 'projects.update'],
            'agency' => ['services.manage', 'organizations.manage', 'projects.create', 'projects.update'],
            'affiliate' => ['affiliates.view', 'affiliates.payout'],
            'partner' => ['organizations.manage', 'subscriptions.manage'],
            'advertiser' => ['ads.manage', 'coupons.manage'],
        ];

        foreach ($rolePermissionMap as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->first();
            if (! $role) {
                continue;
            }

            if ($permissions === '*') {
                $role->permissions()->sync(Permission::pluck('id')->all());
            } else {
                $role->permissions()->sync(Permission::whereIn('name', $permissions)->pluck('id')->all());
            }
        }
    }
}
