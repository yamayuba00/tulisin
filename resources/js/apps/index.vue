<script setup>
import { computed, ref } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import { Home, FolderKanban, Wallet, Handshake, Menu, LogOut, LayoutTemplate, FileText, FolderOpen, Type, Gift, Users, ShieldCheck, BadgeCheck, ReceiptText, MessageSquare, ScrollText, Sparkles, Library, Coins, ScanSearch, Share2, CreditCard, FileDown, Ticket, Mail } from 'lucide-vue-next';
import SidebarLink from '../components/SidebarLink.vue';
import ThemeToggle from '../components/ThemeToggle.vue';
import { useAuth } from '../utils/auth';

const route = useRoute();
const router = useRouter();
const { currentUser, logout } = useAuth();
const sidebarOpen = ref(false);

const userInitial = computed(() => {
    const name = currentUser.value?.name;
    return (name ? name.trim()[0] : 'U').toUpperCase();
});

const pageTitle = computed(() => {
    const titles = {
        dashboard: 'Dashboard',
        agent: 'Agent AI',
        projects: 'Projects',
        lists: 'Lists Project',
        topup: 'Topup',
        affiliate: 'Affiliate',
        earn: 'Dapatkan Koin',
        templates: 'Template',
        journals: 'Paper / Journal',
        workspace: 'Tulisin Workspace',
        files: 'File Manager',
        fonts: 'File Font',
        'admin-dashboard': 'Dashboard Admin',
        'admin-users': 'Users',
        'admin-roles': 'Roles & Permissions',
        'admin-projects': 'Projects',
        'admin-ai-results': 'Hasil AI',
        'admin-coins': 'Riwayat Koin',
        'admin-shared': 'Dokumen Dibagikan',
        'admin-credits': 'Verifikasi Koin',
        'admin-settings': 'Pengaturan Koin',
        'admin-subscriptions': 'Langganan',
        'admin-coupons': 'Promo',
        'admin-notifications': 'Notifikasi',
        'admin-payments': 'Transaksi',
        'admin-topups': 'Topup Orders',
        'admin-affiliates': 'Affiliate',
        'admin-tickets': 'Tickets',
        'admin-exports': 'Export PDF',
        'admin-audit': 'Audit Log',
    };
    return titles[route.name] ?? 'Dashboard';
});

const isSuperAdmin = computed(() => !!currentUser.value?.is_super_admin);

const userNavGroups = [
    {
        label: 'Umum',
        items: [
            { label: 'Dashboard', to: '/apps/u/dashboard', icon: Home },
            { label: 'Agent AI', to: '/apps/u/agent', icon: Sparkles },
            { label: 'Projects', to: '/apps/u/projects', icon: FolderKanban },
            { label: 'Lists Project', to: '/apps/u/lists', icon: Users },
        ],
    },
    {
        label: 'Konten',
        items: [
            { label: 'Template', to: '/apps/u/templates', icon: LayoutTemplate },
            { label: 'Paper / Journal', to: '/apps/u/journals', icon: FileText },
            { label: 'Tulisin Workspace', to: '/apps/u/workspace', icon: Library },
            { label: 'File Manager', to: '/apps/u/files', icon: FolderOpen },
            { label: 'File Font', to: '/apps/u/fonts', icon: Type },
        ],
    },
    {
        label: 'Akun',
        items: [
            { label: 'Topup', to: '/apps/u/topup', icon: Wallet },
            { label: 'Dapatkan Koin', to: '/apps/u/earn', icon: Gift },
            { label: 'Affiliate', to: '/apps/u/affiliate', icon: Handshake },
        ],
    },
];

const adminNavGroups = [
    {
        label: 'Admin',
        items: [
            { label: 'Dashboard', to: '/apps/u/admin/dashboard', icon: Home },
            { label: 'Users', to: '/apps/u/admin/users', icon: Users },
            { label: 'Roles & Permissions', to: '/apps/u/admin/roles', icon: ShieldCheck },
        ],
    },
    {
        label: 'Konten & AI',
        items: [
            { label: 'Projects', to: '/apps/u/admin/projects', icon: FolderKanban },
            { label: 'Hasil AI', to: '/apps/u/admin/ai-results', icon: ScanSearch },
            { label: 'Dokumen Dibagikan', to: '/apps/u/admin/shared', icon: Share2 },
        ],
    },
    {
        label: 'Keuangan',
        items: [
            { label: 'Verifikasi Koin', to: '/apps/u/admin/credits', icon: BadgeCheck },
            { label: 'Pengaturan Koin', to: '/apps/u/admin/settings', icon: Coins },
            { label: 'Langganan', to: '/apps/u/admin/subscriptions', icon: BadgeCheck },
            { label: 'Promo', to: '/apps/u/admin/coupons', icon: Ticket },
            { label: 'Riwayat Koin', to: '/apps/u/admin/coins', icon: Wallet },
            { label: 'Transaksi', to: '/apps/u/admin/payments', icon: ReceiptText },
            { label: 'Topup Orders', to: '/apps/u/admin/topups', icon: CreditCard },
            { label: 'Affiliate', to: '/apps/u/admin/affiliates', icon: Handshake },
        ],
    },
    {
        label: 'Dukungan & Log',
        items: [
            { label: 'Tickets', to: '/apps/u/admin/tickets', icon: MessageSquare },
            { label: 'Export PDF', to: '/apps/u/admin/exports', icon: FileDown },
            { label: 'Audit Log', to: '/apps/u/admin/audit', icon: ScrollText },
            { label: 'Notifikasi', to: '/apps/u/admin/notifications', icon: Mail },
        ],
    },
];

const navGroups = computed(() => (isSuperAdmin.value ? adminNavGroups : userNavGroups));

async function handleLogout() {
    await logout();
    router.push('/login');
}
</script>

<template>
    <div class="flex min-h-screen bg-white text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
        <!-- Overlay (mobile) -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-30 bg-black/50 lg:hidden"
            @click="sidebarOpen = false"
        ></div>

        <!-- Sidebar: sticky di desktop, drawer di mobile -->
        <aside
            class="fixed bottom-0 left-0 top-0 z-40 flex w-64 flex-col border-r border-neutral-200 bg-white transition-transform duration-200 dark:border-neutral-800 dark:bg-neutral-950 lg:bottom-auto lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex h-16 items-center border-b border-neutral-200 px-4 dark:border-neutral-800">
                <RouterLink to="/apps/u/dashboard" class="text-lg font-bold tracking-tight">
                    Tulisin
                </RouterLink>
            </div>

            <nav class="flex-1 overflow-y-auto py-4" @click="sidebarOpen = false">
                <template v-for="group in navGroups" :key="group.label">
                    <p class="px-4 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">
                        {{ group.label }}
                    </p>
                    <SidebarLink
                        v-for="item in group.items"
                        :key="item.to"
                        :to="item.to"
                        :label="item.label"
                        :icon="item.icon"
                    />
                </template>
            </nav>

            <div class="border-t border-neutral-200 px-4 py-3 dark:border-neutral-800">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-neutral-200 text-xs font-semibold dark:border-neutral-800">
                        {{ userInitial }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ currentUser?.name || 'User' }}</p>
                        <p class="truncate text-xs text-neutral-500 dark:text-neutral-400">{{ currentUser?.email || '' }}</p>
                    </div>
                    <button
                        type="button"
                        title="Keluar"
                        aria-label="Keluar"
                        class="flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 transition-colors hover:text-neutral-900 dark:border-neutral-800 dark:text-neutral-400 dark:hover:text-white"
                        @click="handleLogout"
                    >
                        <LogOut class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </aside>

        <!-- Kolom utama -->
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-neutral-200 bg-white px-4 dark:border-neutral-800 dark:bg-neutral-950 lg:px-6">
                <button
                    type="button"
                    class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 text-neutral-600 transition-colors hover:text-neutral-900 dark:border-neutral-800 dark:text-neutral-300 dark:hover:text-white lg:hidden"
                    aria-label="Buka menu"
                    @click="sidebarOpen = true"
                >
                    <Menu class="h-5 w-5" />
                </button>

                <span class="text-sm font-semibold">{{ pageTitle }}</span>

                <div class="ml-auto">
                    <ThemeToggle />
                </div>
            </header>

            <main class="flex-1 p-6 lg:p-8">
                <RouterView />
            </main>
        </div>
    </div>
</template>
