import { createRouter, createWebHistory } from 'vue-router';
import { useAuth } from '../utils/auth';

const routes = [
    {
        path: '/',
        name: 'home',
        component: () => import('../pages/HomePage.vue'),
        meta: {
            title: 'Platform Penulisan Akademik Berbasis AI',
            description: 'Tulisin — platform penulisan akademik berbasis AI. Susun skripsi, tesis, makalah, dan jurnal dengan canvas blok, asisten AI, serta format kampus otomatis.',
        },
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('../pages/LoginPage.vue'),
        meta: { title: 'Masuk', description: 'Masuk ke akun Tulisin dan lanjutkan menyusun dokumen akademikmu.' },
    },
    {
        path: '/register',
        name: 'register',
        component: () => import('../pages/RegisterPage.vue'),
        meta: { title: 'Daftar', description: 'Buat akun Tulisin gratis dan mulai tulis dokumen pertamamu hari ini.' },
    },
    {
        path: '/forgot-password',
        name: 'forgot-password',
        component: () => import('../pages/ForgotPasswordPage.vue'),
        meta: { title: 'Lupa Password', description: 'Reset kata sandi akun Tulisin kamu.' },
    },
    {
        path: '/verify-email',
        name: 'verify-email',
        component: () => import('../pages/VerifyEmailPage.vue'),
        meta: { title: 'Verifikasi Email', description: 'Verifikasi alamat email akun Tulisin kamu.' },
    },
    {
        path: '/reset-password',
        name: 'reset-password',
        component: () => import('../pages/ResetPasswordPage.vue'),
        meta: { title: 'Reset Password', description: 'Buat kata sandi baru untuk akun Tulisin kamu.' },
    },
    {
        path: '/apps/u',
        component: () => import('../apps/index.vue'),
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                redirect: '/apps/u/dashboard',
            },
            {
                path: 'dashboard',
                name: 'dashboard',
                component: () => import('../apps/dashboard/index.vue'),
                meta: { title: 'Dashboard' },
            },
            {
                path: 'agent',
                name: 'agent',
                component: () => import('../apps/agent/index.vue'),
                meta: { title: 'Agent AI' },
            },
            {
                path: 'projects',
                name: 'projects',
                component: () => import('../apps/projects/index.vue'),
                meta: { title: 'Projects' },
            },
            {
                path: 'lists',
                name: 'lists',
                component: () => import('../apps/lists/index.vue'),
                meta: { title: 'Lists Project' },
            },
            {
                path: 'topup',
                name: 'topup',
                component: () => import('../apps/topup/index.vue'),
                meta: { title: 'Topup' },
            },
            {
                path: 'earn',
                name: 'earn',
                component: () => import('../apps/earn/index.vue'),
                meta: { title: 'Dapatkan Koin' },
            },
            {
                path: 'affiliate',
                name: 'affiliate',
                component: () => import('../apps/affiliate/index.vue'),
                meta: { title: 'Affiliate' },
            },
            {
                path: 'templates',
                name: 'templates',
                component: () => import('../apps/templates/index.vue'),
                meta: { title: 'Template' },
            },
            {
                path: 'journals',
                name: 'journals',
                component: () => import('../apps/journals/index.vue'),
                meta: { title: 'Paper / Journal' },
            },
            {
                path: 'workspace',
                name: 'workspace',
                component: () => import('../apps/workspace/index.vue'),
                meta: { title: 'Tulisin Workspace' },
            },
            {
                path: 'files',
                name: 'files',
                component: () => import('../apps/files/index.vue'),
                meta: { title: 'File Manager' },
            },
            {
                path: 'fonts',
                name: 'fonts',
                component: () => import('../apps/fonts/index.vue'),
                meta: { title: 'File Font' },
            },
            {
                path: 'admin/dashboard',
                name: 'admin-dashboard',
                component: () => import('../apps/admin/dashboard/index.vue'),
                meta: { title: 'Dashboard Admin' },
            },
            {
                path: 'admin/users',
                name: 'admin-users',
                component: () => import('../apps/admin/users/index.vue'),
                meta: { title: 'Users' },
            },
            {
                path: 'admin/roles',
                name: 'admin-roles',
                component: () => import('../apps/admin/roles/index.vue'),
                meta: { title: 'Roles & Permissions' },
            },
            {
                path: 'admin/projects',
                name: 'admin-projects',
                component: () => import('../apps/admin/projects/index.vue'),
                meta: { title: 'Projects' },
            },
            {
                path: 'admin/ai-results',
                name: 'admin-ai-results',
                component: () => import('../apps/admin/ai-results/index.vue'),
                meta: { title: 'Hasil AI' },
            },
            {
                path: 'admin/shared',
                name: 'admin-shared',
                component: () => import('../apps/admin/shared/index.vue'),
                meta: { title: 'Dokumen Dibagikan' },
            },
            {
                path: 'admin/credits',
                name: 'admin-credits',
                component: () => import('../apps/admin/credits/index.vue'),
                meta: { title: 'Verifikasi Koin' },
            },
            {
                path: 'admin/settings',
                name: 'admin-settings',
                component: () => import('../apps/admin/settings/index.vue'),
                meta: { title: 'Pengaturan Koin' },
            },
            {
                path: 'admin/subscriptions',
                name: 'admin-subscriptions',
                component: () => import('../apps/admin/subscriptions/index.vue'),
                meta: { title: 'Langganan' },
            },
            {
                path: 'admin/coupons',
                name: 'admin-coupons',
                component: () => import('../apps/admin/coupons/index.vue'),
                meta: { title: 'Promo' },
            },
            {
                path: 'admin/notifications',
                name: 'admin-notifications',
                component: () => import('../apps/admin/notifications/index.vue'),
                meta: { title: 'Notifikasi' },
            },
            {
                path: 'admin/coins',
                name: 'admin-coins',
                component: () => import('../apps/admin/coins/index.vue'),
                meta: { title: 'Riwayat Koin' },
            },
            {
                path: 'admin/payments',
                name: 'admin-payments',
                component: () => import('../apps/admin/payments/index.vue'),
                meta: { title: 'Transaksi' },
            },
            {
                path: 'admin/topups',
                name: 'admin-topups',
                component: () => import('../apps/admin/topups/index.vue'),
                meta: { title: 'Topup Orders' },
            },
            {
                path: 'admin/affiliates',
                name: 'admin-affiliates',
                component: () => import('../apps/admin/affiliates/index.vue'),
                meta: { title: 'Affiliate' },
            },
            {
                path: 'admin/tickets',
                name: 'admin-tickets',
                component: () => import('../apps/admin/tickets/index.vue'),
                meta: { title: 'Tickets' },
            },
            {
                path: 'admin/exports',
                name: 'admin-exports',
                component: () => import('../apps/admin/exports/index.vue'),
                meta: { title: 'Export PDF' },
            },
            {
                path: 'admin/audit',
                name: 'admin-audit',
                component: () => import('../apps/admin/audit/index.vue'),
                meta: { title: 'Audit Log' },
            },
        ],
    },
    {
        path: '/apps/u/project',
        name: 'builder',
        component: () => import('../apps/project/index.vue'),
        meta: { requiresAuth: true, title: 'Editor Project' },
    },
    {
        path: '/share',
        name: 'share',
        component: () => import('../pages/SharedViewerPage.vue'),
        meta: { title: 'Dokumen Dibagikan', description: 'Lihat dokumen yang dibagikan tanpa perlu login.' },
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/',
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

const APP_NAME = 'Tulisin';
const DEFAULT_DESCRIPTION = 'Tulisin — platform penulisan akademik berbasis AI. Susun skripsi, tesis, makalah, dan jurnal dengan canvas blok, asisten AI, serta format kampus otomatis.';

// Guard: lindungi halaman aplikasi, dan arahkan user yang sudah login.
router.beforeEach(async (to) => {
    const { isAuthenticated, currentUser, init } = useAuth();
    await init();

    if (to.meta.requiresAuth && !isAuthenticated.value) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (isAuthenticated.value && ['login', 'register', 'forgot-password'].includes(to.name)) {
        const home = currentUser.value?.is_super_admin ? 'admin-dashboard' : 'dashboard';
        return { name: home };
    }
});

// Buat/perbarui tag <meta> tertentu secara dinamis (untuk SEO SPA).
function upsertMeta(attr, key, content) {
    const selector = `meta[${attr}="${key}"]`;
    let el = document.head.querySelector(selector);
    if (!el) {
        el = document.createElement('meta');
        el.setAttribute(attr, key);
        document.head.appendChild(el);
    }
    el.setAttribute('content', content);
}

// Perbarui judul, deskripsi, canonical, serta Open Graph / Twitter per halaman.
router.afterEach((to) => {
    const title = to.meta.title ? `${to.meta.title} — ${APP_NAME}` : APP_NAME;
    const description = to.meta.description || DEFAULT_DESCRIPTION;
    const url = window.location.origin + to.path;

    document.title = title;

    upsertMeta('name', 'description', description);
    upsertMeta('property', 'og:title', title);
    upsertMeta('property', 'og:description', description);
    upsertMeta('property', 'og:url', url);
    upsertMeta('name', 'twitter:title', title);
    upsertMeta('name', 'twitter:description', description);

    let canonical = document.head.querySelector('link[rel="canonical"]');
    if (!canonical) {
        canonical = document.createElement('link');
        canonical.rel = 'canonical';
        document.head.appendChild(canonical);
    }
    canonical.href = url;
});

export default router;
