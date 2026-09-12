<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { RouterLink } from 'vue-router';
import {
    PenLine, Sparkles, LayoutTemplate, FolderOpen, Type, BookOpen,
    Check, ArrowRight, Menu, X, Image, ShieldCheck,
    Zap, ChevronDown, GripVertical, GraduationCap, Building2,
    ScanSearch, ClipboardCheck, Star, Send,
} from 'lucide-vue-next';
import FloatingChat from '../components/FloatingChat.vue';
import { useAuth } from '../utils/auth';
import { getJson } from '../utils/http';
import { formatCurrency } from '../utils/format';
import { appName } from '../utils/appName';

const { currentUser, isAuthenticated } = useAuth();

const dashboardPath = computed(() =>
    currentUser.value?.is_super_admin ? '/apps/u/admin/dashboard' : '/apps/u/dashboard'
);

const menuOpen = ref(false);
const openFaq = ref(0);

// ---- Scroll-spy: sorot menu navbar sesuai section yang sedang terlihat ----
const activeSection = ref('');
const spySectionIds = ['fitur', 'paket', 'untuk-siapa', 'testimoni'];

function updateActiveSection() {
    const offset = 120;
    let current = '';
    for (const id of spySectionIds) {
        const el = document.getElementById(id);
        if (!el) continue;
        if (el.getBoundingClientRect().top <= offset) {
            current = id;
        }
    }
    activeSection.value = current;
}

function onScrollSpy() {
    updateActiveSection();
}

function navLinkClass(id) {
    const base = 'rounded-lg px-3 py-2 transition-colors ';
    return activeSection.value === id
        ? base + 'bg-neutral-100 font-medium text-neutral-900 dark:bg-neutral-900 dark:text-white'
        : base + 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-900 dark:hover:text-white';
}

// ---- Hero mockup: urutan build (drag blok dari sidebar → canvas) ----
const buildBlocks = [
    { id: 'judul', label: 'Judul' },
    { id: 'paragraf', label: 'Paragraf' },
    { id: 'gambar', label: 'Gambar' },
    { id: 'daftar', label: 'Daftar Pustaka' },
    { id: 'tabel', label: 'Tabel' },
];

const activeBlock = ref(-1);       // indeks blok yang sedang di-drag
const placedBlocks = ref([]);      // id blok yang sudah masuk canvas
const saveState = ref('idle');     // idle | saving | saved
const ghostVisible = ref(false);   // apakah blok "ghost" terlihat
const ghostFlying = ref(false);    // apakah blok sedang terbang ke canvas

function isPlaced(id) {
    return placedBlocks.value.includes(id);
}

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

let buildDisposed = false;

async function runBuildLoop() {
    while (!buildDisposed) {
        // Reset dokumen
        placedBlocks.value = [];
        activeBlock.value = -1;
        saveState.value = 'idle';
        ghostVisible.value = false;
        ghostFlying.value = false;
        await sleep(1000);

        for (let i = 0; i < buildBlocks.length; i++) {
            if (buildDisposed) return;
            activeBlock.value = i;
            ghostVisible.value = true;
            ghostFlying.value = false;
            await sleep(380);

            if (buildDisposed) return;
            ghostFlying.value = true;
            await sleep(720);

            if (buildDisposed) return;
            // Mendarat: sembunyikan & reset posisi (tanpa terlihat)
            ghostVisible.value = false;
            ghostFlying.value = false;
            placedBlocks.value = [...placedBlocks.value, buildBlocks[i].id];
            activeBlock.value = -1;
            saveState.value = 'saving';
            await sleep(420);

            if (buildDisposed) return;
            saveState.value = 'saved';
            await sleep(900);

            if (buildDisposed) return;
            saveState.value = 'idle';
        }

        await sleep(2200);
    }
}

// ---- Judul "mengetik" (copywriting) ----
const typedPhrases = [
    'BAB I — Pendahuluan',
    'BAB II — Tinjauan Pustaka',
    'BAB III — Metodologi Penelitian',
];
const typed = ref('');
let phraseIdx = 0;
let typeTimer = null;
let typeTimeout = null;
let typingEnabled = false;

function typePhrase(text, done) {
    typed.value = '';
    let idx = 0;
    typeTimer = setInterval(() => {
        idx += 1;
        typed.value = text.slice(0, idx);
        if (idx >= text.length) {
            clearInterval(typeTimer);
            typeTimeout = setTimeout(done, 2100);
        }
    }, 45);
}

function startTyping() {
    typingEnabled = true;
    typePhrase(typedPhrases[phraseIdx], () => {
        if (!typingEnabled) return;
        phraseIdx = (phraseIdx + 1) % typedPhrases.length;
        startTyping();
    });
}

function stopTyping() {
    typingEnabled = false;
    clearInterval(typeTimer);
    clearTimeout(typeTimeout);
}

// Judul mulai mengetik begitu blok "Judul" sudah di-drop ke canvas.
watch(
    () => placedBlocks.value.includes('judul'),
    (placed) => {
        if (placed) {
            startTyping();
        } else {
            stopTyping();
            typed.value = '';
        }
    }
);

onMounted(runBuildLoop);
onBeforeUnmount(() => {
    buildDisposed = true;
    stopTyping();
});

const categories = ['Skripsi', 'Tesis', 'Disertasi', 'Makalah', 'Jurnal', 'Laporan', 'Proposal', 'Esai'];

const features = [
    { icon: Sparkles, title: 'Asisten AI untuk Tiap Bab', desc: 'Minta AI membuat abstrak, mengembangkan ide, atau merapikan kalimat langsung di dokumenmu.' },
    { icon: ScanSearch, title: 'Tulisan Lolos Deteksi AI', desc: 'Rapikan gaya agar terbaca manusiawi dan tidak terdeteksi Turnitin, tanpa mengubah makna.' },
    { icon: ClipboardCheck, title: 'Kemiripan Turun Otomatis', desc: 'Parafrase kalimat agar skor plagiarisme turun, tetap natural dan enak dibaca.' },
    { icon: LayoutTemplate, title: 'Susun Dokumen Per Blok', desc: 'Atur judul, paragraf, tabel, gambar, dan daftar pustaka sebagai blok yang mudah digeser.' },
    { icon: Type, title: 'Format Sesuai Kampus', desc: 'Atur spasi, font, dan margin per bagian agar sesuai pedoman, tanpa edit manual.' },
    { icon: FolderOpen, title: 'Semua File Satu Tempat', desc: 'Kelola gambar dan file sekali, pakai langsung saat menulis di dokumen mana pun.' },
    { icon: BookOpen, title: 'Sitasi & Daftar Pustaka Otomatis', desc: 'Rujukan tertata rapi sesuai APA, IEEE, atau gaya yang diminta kampus.' },
    { icon: ShieldCheck, title: 'Ekspor Siap Cetak', desc: 'Atur margin & ukuran halaman, lalu unduh PDF yang rapi dalam sekali klik.' },
    { icon: Send, title: 'Publish Jurnal', desc: 'Publikasikan karya ilmiahmu dan kelola prosesnya dalam satu tempat.', comingSoon: true },
];

const steps = [
    {
        no: '01',
        title: 'Pilih Jenis Dokumen',
        icon: FolderOpen,
        desc: 'Tentukan skripsi, makalah, atau jurnal — format kampus langsung terpasang otomatis.',
        points: ['Pilih skripsi, tesis, atau jurnal', 'Format kampus terpasang otomatis', 'Mulai dari template yang sudah rapi'],
    },
    {
        no: '02',
        title: 'Tulis dengan Bantuan AI',
        icon: Sparkles,
        desc: 'Susun bab, minta AI mengembangkan ide, dan rapikan paragraf tanpa mulai dari kosong.',
        points: ['Susun kerangka bab otomatis', 'AI bantu kembangkan isi', 'Sitasi & daftar pustaka beres sendiri'],
    },
    {
        no: '03',
        title: 'Unduh Siap Cetak',
        icon: Send,
        desc: 'Ekspor ke PDF yang rapi dan langsung kirim ke dosen — selesai.',
        points: ['Cek format akhir', 'Ekspor PDF sekali klik', 'Langsung kumpulkan'],
    },
];

// Data publik homepage: harga langganan & daftar mesin AI (tenaga agent).
const landing = ref({ monthly_price: 30000, ai_engines: ['DeepSeek'] });

const plans = computed(() => [
    {
        name: 'Langganan Bulanan',
        price: formatCurrency(landing.value.monthly_price),
        period: '/ 30 hari',
        desc: 'Akses penuh semua fitur AI, optimasi, dan ekspor.',
        features: ['Agent Canvas & Asisten AI penuh', 'Turnitin & Plagiarism Optimizer', 'Ekspor PDF siap cetak', 'Semua template & file manager'],
        highlight: true,
        cta: 'Berlangganan',
    },
    {
        name: 'Koin',
        price: 'Fleksibel',
        period: '',
        desc: 'Beli koin sesuai kebutuhan untuk fitur AI.',
        features: ['Topup koin custom', 'Pakai fitur AI per pemakaian', 'Tanpa langganan bulanan', 'Saldo tidak hangus'],
        highlight: false,
        cta: 'Beli Koin',
    },
]);

// Tabel perbandingan paket untuk section "Paket".
const comparisonPlans = [
    { key: 'gratis', label: 'Gratis' },
    { key: 'bulanan', label: 'Bulanan', highlight: true },
    { key: 'koin', label: 'Koin' },
];

const comparisonRows = [
    { feature: 'Canvas dokumen (blok)', gratis: true, bulanan: true, koin: true },
    { feature: 'Asisten AI Kontekstual', gratis: false, bulanan: true, koin: true },
    { feature: 'Turnitin AI Optimizer', gratis: false, bulanan: true, koin: true },
    { feature: 'Plagiarism Optimizer', gratis: false, bulanan: true, koin: true },
    { feature: 'Ekspor PDF siap cetak', gratis: false, bulanan: true, koin: false },
    { feature: 'Template & File Manager', gratis: true, bulanan: true, koin: false },
];

const faqs = [
    { q: 'Apakah hasil tulisan saya terdeteksi AI atau plagiarisme?', a: `Tidak. ${appName} punya AI Optimizer dan Plagiarism Optimizer untuk membuat gaya tulisan lebih manusiawi serta menurunkan skor kemiripan sebelum kamu kirim ke Turnitin.` },
    { q: 'Apakah formatnya sesuai standar kampus (margin, APA, IEEE, dll.)?', a: 'Ya. Margin, spasi, font, hingga gaya sitasi (APA, IEEE, Harvard) diatur otomatis dan bisa disesuaikan dengan pedoman kampusmu.' },
    { q: 'Apakah ide & data penelitian saya aman?', a: 'Aman. Dokumen hanya bisa diakses akunmu dan tidak dipublikasikan tanpa izin. Berbagi publik (Lists Project) bersifat opsional dan read-only.' },
    { q: 'Apakah ada opsi uji coba gratis?', a: 'Ada. Kamu bisa mulai menulis dengan paket gratis tanpa kartu kredit, lalu upgrade hanya saat butuh fitur AI & ekspor.' },
];

// Perbandingan: cara manual vs Tulisin.
const comparison = [
    { aspect: 'Sitasi & daftar pustaka', old: 'Ketik ulang satu per satu, rawan keliru.', new: 'Tertata otomatis (APA, IEEE, dll).' },
    { aspect: 'Susun bab', old: 'Maju-mundur antar file, mudah kehilangan jejak.', new: 'Semua bab dalam satu kanvas blok.' },
    { aspect: 'Cek plagiarisme & deteksi AI', old: 'Cek manual di banyak tempat, hasil tidak pasti.', new: 'Diturunkan otomatis sampai aman untuk Turnitin.' },
    { aspect: 'Waktu pengerjaan', old: 'Berbulan-bulan habis untuk urusan teknis.', new: 'Fokus isi, urusan teknis beres otomatis.' },
];

// Animasi chat pada section Asisten AI.
const chatStep = ref(0); // 0 = user, 1 = ai, 2 = typing
let chatTimer = null;

function startChatLoop() {
    chatStep.value = 0;
    chatTimer = setInterval(() => {
        chatStep.value = (chatStep.value + 1) % 3;
    }, 2200);
}

onMounted(startChatLoop);
onBeforeUnmount(() => {
    clearInterval(chatTimer);
});

// Segmen pengguna "Untuk Siapa".
const audiences = [
    { icon: GraduationCap, title: 'Mahasiswa', desc: 'Selesaikan skripsi, tesis, hingga disertasi dengan format kampus yang rapi dan bantuan AI.' },
    { icon: BookOpen, title: 'Dosen & Peneliti', desc: 'Susun jurnal, makalah, dan laporan penelitian dengan sitasi serta gaya penulisan yang konsisten.', comingSoon: true },
    { icon: Building2, title: 'Kampus & Institusi', desc: 'Standarisasi format dokumen, kelola seat anggota, hingga integrasi sistem (B2B).', comingSoon: true },
];

// Nama kampus (placeholder) untuk section "Mahasiswa universitas".
const universities = [
    'Universitas Indonesia', 'Universitas Gadjah Mada', 'Institut Teknologi Bandung',
    'Universitas Airlangga', 'Universitas Diponegoro', 'Universitas Padjadjaran',
    'Institut Teknologi Sepuluh Nopember', 'Universitas Brawijaya', 'Universitas Sebelas Maret',
    'Universitas Hasanuddin', 'Universitas Sumatera Utara', 'Universitas Andalas',
];

// Review pengguna (dari API, tampil maksimal 9 di homepage).
const reviews = ref([]);
const reviewsLoading = ref(true);

async function loadLanding() {
    try {
        const data = await getJson('/api/landing-settings');
        landing.value = {
            monthly_price: Number(data.monthly_price) || 30000,
            ai_engines: Array.isArray(data.ai_engines) && data.ai_engines.length ? data.ai_engines : ['DeepSeek'],
        };
    } catch {
        // pakai nilai default bila API gagal dimuat
    }
}

async function loadReviews() {
    try {
        const data = await getJson('/api/reviews/published?per_page=9');
        reviews.value = data.reviews || [];
    } catch {
        reviews.value = [];
    } finally {
        reviewsLoading.value = false;
    }
}

onMounted(loadLanding);
onMounted(loadReviews);

// ---- Information banner (di atas header, konten dinamis & bisa diaktifkan admin) ----
const infoBanner = ref(null);
const infoBannerDismissed = ref(false);

async function loadInfoBanner() {
    try {
        const data = await getJson('/api/information-banner');
        infoBanner.value = data.banner || null;
    } catch {
        infoBanner.value = null;
    }
}

onMounted(loadInfoBanner);

const chatSuggestions = [
    `Bagaimana cara kerja ${appName}?`,
    'Apakah gratis untuk mencoba?',
    'Bisakah memakai format kampus saya?',
];

// ---- Before / After: Optimizer (Turnitin & Plagiarism) ----
const optimizerTab = ref('turnitin');
const optimizerPos = ref(50); // posisi handle (%) — dianimasikan bolak-balik
const compareEl = ref(null);

const optimizerTabs = [
    { id: 'turnitin', label: 'Turnitin AI Optimizer' },
    { id: 'plagiarism', label: 'Plagiarism Optimizer' },
];

const optimizerSamples = {
    turnitin: {
        before: 'Perlu ditekankan bahwa perkembangan kecerdasan buatan telah memberikan dampak yang sangat signifikan terhadap berbagai aspek kehidupan manusia modern, termasuk dunia pendidikan.',
        after: 'Perkembangan kecerdasan buatan berdampak besar pada dunia pendidikan. Perubahan ini mendorong metode belajar yang lebih adaptif dan personal.',
        metric: { before: 'Deteksi AI: Tinggi', after: 'Deteksi AI: Rendah' },
    },
    plagiarism: {
        before: 'Menurut penelitian yang dilakukan oleh para ahli, kecerdasan buatan dapat digunakan untuk meningkatkan produktivitas dalam proses pembelajaran di berbagai institusi pendidikan.',
        after: 'Studi para ahli menunjukkan AI mampu menaikkan produktivitas pembelajaran di beragam institusi pendidikan.',
        metric: { before: 'Kemiripan: 42%', after: 'Kemiripan: 8%' },
    },
};

const sample = computed(() => optimizerSamples[optimizerTab.value]);

let optimizerRaf = null;
let optimizerDir = 1;
let optimizerDragging = false;

function optimizerLoop() {
    if (!optimizerDragging) {
        optimizerPos.value += optimizerDir * 0.25;
        if (optimizerPos.value >= 78) {
            optimizerPos.value = 78;
            optimizerDir = -1;
        } else if (optimizerPos.value <= 22) {
            optimizerPos.value = 22;
            optimizerDir = 1;
        }
    }
    optimizerRaf = requestAnimationFrame(optimizerLoop);
}

function updatePosFromEvent(e) {
    const el = compareEl.value;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    const x = ((e.clientX - rect.left) / rect.width) * 100;
    optimizerPos.value = Math.min(100, Math.max(0, x));
}

function onOptimizerDrag(e) {
    updatePosFromEvent(e);
}

function endOptimizerDrag() {
    optimizerDragging = false;
    window.removeEventListener('pointermove', onOptimizerDrag);
    window.removeEventListener('pointerup', endOptimizerDrag);
}

function startDrag(e) {
    optimizerDragging = true;
    updatePosFromEvent(e);
    window.addEventListener('pointermove', onOptimizerDrag);
    window.addEventListener('pointerup', endOptimizerDrag);
}

onMounted(() => {
    optimizerRaf = requestAnimationFrame(optimizerLoop);
    window.addEventListener('scroll', onScrollSpy, { passive: true });
    updateActiveSection();
});

onBeforeUnmount(() => {
    cancelAnimationFrame(optimizerRaf);
    window.removeEventListener('pointermove', onOptimizerDrag);
    window.removeEventListener('pointerup', endOptimizerDrag);
    window.removeEventListener('scroll', onScrollSpy);
});
</script>

<template>
    <div class="min-h-screen text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
        <!-- Information banner (di atas header) -->
        <div
            v-if="infoBanner && infoBanner.enabled && infoBanner.text && !infoBannerDismissed"
            class="relative z-50 border-b border-neutral-200 bg-neutral-900 text-neutral-100 dark:border-neutral-800 dark:bg-white dark:text-neutral-900"
        >
            <div class="mx-auto flex max-w-6xl items-center justify-center gap-2 px-10 py-2 text-center text-sm lg:px-6">
                <Sparkles class="h-4 w-4 shrink-0 opacity-70" />
                <p class="min-w-0">
                    {{ infoBanner.text }}
                    <a
                        v-if="infoBanner.link_text && infoBanner.link_url"
                        :href="infoBanner.link_url"
                        class="ml-1 font-semibold underline underline-offset-4 hover:opacity-80"
                    >{{ infoBanner.link_text }}</a>
                </p>
                <button
                    type="button"
                    class="absolute right-3 top-1/2 inline-flex h-7 w-7 -translate-y-1/2 cursor-pointer items-center justify-center rounded-md opacity-70 hover:opacity-100"
                    aria-label="Tutup"
                    @click="infoBannerDismissed = true"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>
        </div>

        <!-- Navbar -->
        <header class="sticky top-0 z-50 border-b border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-950">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 lg:px-6">
                <RouterLink to="/" class="inline-flex items-center gap-2 text-lg font-bold tracking-tight">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-neutral-900 text-white dark:bg-white dark:text-neutral-900">
                        <PenLine class="h-4 w-4" />
                    </span>
                    {{ appName }}
                </RouterLink>

                <nav class="hidden items-center gap-1 text-sm text-neutral-600 dark:text-neutral-300 md:flex">
                    <a href="#fitur" :class="navLinkClass('fitur')">Fitur</a>
                    <a href="#untuk-siapa" :class="navLinkClass('untuk-siapa')">Untuk Siapa</a>
                    <a href="#paket" :class="navLinkClass('paket')">Paket</a>
                    <a href="#testimoni" :class="navLinkClass('testimoni')">Testimoni</a>
                </nav>

                <div class="hidden items-center gap-2 md:flex">
                    <template v-if="isAuthenticated">
                        <RouterLink :to="dashboardPath" class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-900 bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-neutral-700 dark:border-white dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200">
                            Dashboard
                            <ArrowRight class="h-4 w-4" />
                        </RouterLink>
                    </template>
                    <template v-else>
                        <RouterLink to="/login" class="rounded-lg px-3 py-2 text-sm font-medium transition-colors hover:bg-neutral-100 dark:hover:bg-neutral-900">Masuk</RouterLink>
                        <RouterLink to="/register" class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-900 bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-neutral-700 dark:border-white dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200">
                            Mulai Menulis
                            <ArrowRight class="h-4 w-4" />
                        </RouterLink>
                    </template>
                </div>

                <button
                    type="button"
                    class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 text-neutral-600 dark:border-neutral-800 dark:text-neutral-300 md:hidden"
                    aria-label="Menu"
                    @click="menuOpen = !menuOpen"
                >
                    <X v-if="menuOpen" class="h-5 w-5" />
                    <Menu v-else class="h-5 w-5" />
                </button>
            </div>

            <div v-if="menuOpen" class="border-b border-neutral-200 bg-white px-4 py-4 dark:border-neutral-800 dark:bg-neutral-950 md:hidden">
                <nav class="mx-auto flex max-w-6xl flex-col gap-1 text-sm">
                    <a href="#fitur" :class="navLinkClass('fitur')" @click="menuOpen = false">Fitur</a>
                    <a href="#paket" :class="navLinkClass('paket')" @click="menuOpen = false">Paket</a>
                    <a href="#untuk-siapa" :class="navLinkClass('untuk-siapa')" @click="menuOpen = false">Untuk Siapa</a>
                    <a href="#testimoni" :class="navLinkClass('testimoni')" @click="menuOpen = false">Testimoni</a>
                    <div class="mt-2 flex flex-col gap-2 border-t border-neutral-200 pt-3 dark:border-neutral-800">
                        <template v-if="isAuthenticated">
                            <RouterLink :to="dashboardPath" class="rounded-lg border border-neutral-900 bg-neutral-900 px-4 py-2 text-center font-medium text-white dark:border-white dark:bg-white dark:text-neutral-900" @click="menuOpen = false">Dashboard</RouterLink>
                        </template>
                        <template v-else>
                            <RouterLink to="/login" class="rounded-lg border border-neutral-200 px-4 py-2 text-center font-medium dark:border-neutral-800" @click="menuOpen = false">Masuk</RouterLink>
                            <RouterLink to="/register" class="rounded-lg border border-neutral-900 bg-neutral-900 px-4 py-2 text-center font-medium text-white dark:border-white dark:bg-white dark:text-neutral-900" @click="menuOpen = false">Mulai Menulis</RouterLink>
                        </template>
                    </div>
                </nav>
            </div>
        </header>

        <!-- Hero -->
        <section class="relative overflow-hidden bg-gradient-to-b from-neutral-100/60 via-transparent to-transparent dark:from-neutral-900/20">
            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                <div class="absolute -top-28 left-1/2 h-[480px] w-[820px] -translate-x-1/2 rounded-full bg-neutral-400/15 blur-3xl dark:bg-neutral-500/10"></div>
                <div class="absolute right-0 top-24 h-72 w-72 rounded-full bg-neutral-300/20 blur-3xl dark:bg-neutral-500/10"></div>
            </div>
            <div class="relative mx-auto grid max-w-6xl items-center gap-12 px-4 py-16 lg:grid-cols-2 lg:px-6 lg:py-24">
                <div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-neutral-200 bg-neutral-50 px-3 py-1 text-xs font-medium text-neutral-600 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300">
                        <Sparkles class="h-3.5 w-3.5" />
                        Tulis skripsi, tesis, makalah, jurnal & proposal — selesai otomatis
                    </span>
                    <h1 class="mt-6 font-serif text-4xl font-bold leading-[1.1] tracking-tight lg:text-5xl">
                        Selesaikan karya ilmiah tanpa pusing format & daftar pustaka.
                    </h1>
                    <p class="mt-4 text-base font-medium text-neutral-700 dark:text-neutral-200">
                        Untuk mahasiswa, dosen, peneliti, hingga kampus.
                    </p>
                    <p class="mt-2 max-w-lg text-lg text-neutral-500 dark:text-neutral-400">
                        Susun bab, tulis dengan bantuan AI, dan biarkan format beres otomatis — dari margin, sitasi, sampai daftar pustaka. Semua dalam satu kanvas, tanpa pindah-pindah aplikasi.
                    </p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <RouterLink to="/register" class="inline-flex items-center justify-center gap-2 rounded-lg border border-neutral-900 bg-neutral-900 px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-neutral-700 dark:border-white dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200">
                            Mulai Menulis Gratis
                            <ArrowRight class="h-4 w-4" />
                        </RouterLink>
                        <a href="#cara-kerja" class="inline-flex items-center justify-center gap-2 rounded-lg border border-neutral-200 px-6 py-3 text-sm font-medium transition-colors hover:bg-neutral-100 dark:border-neutral-800 dark:hover:bg-neutral-900">
                            Lihat Cara Kerja
                        </a>
                    </div>
                    <p class="mt-4 text-xs text-neutral-400 dark:text-neutral-500">Tanpa kartu kredit · Format APA/IEEE siap pakai</p>
                </div>

                <!-- Product mockup -->
                <div class="relative animate-float">
                    <div class="absolute -inset-6 rounded-3xl bg-gradient-to-tr from-neutral-200/40 to-transparent blur-xl dark:from-neutral-800/40"></div>
                    <div class="relative overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-lg dark:border-neutral-800 dark:bg-neutral-900">
                        <!-- title bar -->
                        <div class="flex items-center gap-2 border-b border-neutral-200 px-4 py-2.5 dark:border-neutral-800">
                            <span class="h-2.5 w-2.5 rounded-full bg-neutral-300"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-neutral-400"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-neutral-500"></span>
                            <span class="ml-3 flex-1 truncate rounded-md bg-neutral-100 px-3 py-1 text-xs text-neutral-500 dark:bg-neutral-800">tulissin.com/project?builder=</span>
                        </div>
                        <div class="flex">
                            <!-- mini sidebar: blok yang bisa di-drag -->
                            <div class="hidden w-32 shrink-0 space-y-1.5 border-r border-neutral-100 p-3 dark:border-neutral-800 sm:block">
                                <p class="pb-1 text-[9px] font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Blok</p>
                                <div
                                    v-for="(b, i) in buildBlocks"
                                    :key="b.id"
                                    class="flex items-center gap-1 rounded-md border px-2 py-1.5 text-[10px] transition-all duration-300"
                                    :class="i === activeBlock
                                        ? 'border-neutral-300 bg-neutral-100 text-neutral-900 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-100'
                                        : isPlaced(b.id)
                                            ? 'border-neutral-200 bg-white text-neutral-300 line-through dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-600'
                                            : 'border-neutral-200 bg-white text-neutral-600 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300'"
                                >
                                    <GripVertical class="h-3 w-3 shrink-0 text-neutral-400" />
                                    {{ b.label }}
                                </div>
                            </div>
                            <!-- document page -->
                            <div class="flex-1 bg-neutral-50 p-4 dark:bg-neutral-950">
                                <div class="rounded-lg bg-white p-5 shadow ring-1 ring-neutral-100 dark:bg-neutral-900 dark:ring-neutral-800">
                                    <!-- Status auto-save -->
                                    <div class="mb-3 flex items-center justify-end gap-1.5 text-[10px]">
                                        <span v-if="saveState === 'saving'" class="inline-flex items-center gap-1.5 text-neutral-400">
                                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-neutral-400"></span>
                                            Menyimpan…
                                        </span>
                                        <span v-else-if="saveState === 'saved'" class="inline-flex items-center gap-1 text-neutral-700 dark:text-neutral-300">
                                            <Check class="h-3 w-3" />
                                            Tersimpan
                                        </span>
                                        <span v-else class="text-neutral-300 dark:text-neutral-600">Auto-save aktif</span>
                                    </div>

                                    <!-- Judul -->
                                    <div v-if="isPlaced('judul')" class="mx-auto w-2/3 text-center text-sm font-semibold tracking-tight">
                                        {{ typed }}<span class="animate-blink text-neutral-400">|</span>
                                    </div>
                                    <div v-else class="mx-auto h-4 w-2/3 animate-pulse rounded bg-neutral-100 dark:bg-neutral-800"></div>

                                    <!-- Paragraf -->
                                    <div v-if="isPlaced('paragraf')" class="mt-3 space-y-2">
                                        <div class="h-2 rounded bg-neutral-100 dark:bg-neutral-800"></div>
                                        <div class="h-2 rounded bg-neutral-100 dark:bg-neutral-800"></div>
                                        <div class="h-2 w-5/6 rounded bg-neutral-100 dark:bg-neutral-800"></div>
                                    </div>
                                    <div v-else class="mt-3 space-y-2">
                                        <div class="h-2 animate-pulse rounded bg-neutral-100 dark:bg-neutral-800"></div>
                                        <div class="h-2 w-4/5 animate-pulse rounded bg-neutral-100 dark:bg-neutral-800"></div>
                                    </div>

                                    <!-- Gambar -->
                                    <div v-if="isPlaced('gambar')" class="mt-3 flex h-14 items-center justify-center gap-2 rounded border border-dashed border-neutral-200 text-neutral-400 dark:border-neutral-700">
                                        <Image class="h-4 w-4" />
                                        <span class="text-xs">Gambar</span>
                                    </div>
                                    <div v-else class="mt-3 h-14 animate-pulse rounded bg-neutral-100 dark:bg-neutral-800"></div>

                                    <!-- Daftar Pustaka -->
                                    <div v-if="isPlaced('daftar')" class="mt-3 space-y-1.5">
                                        <div v-for="i in 3" :key="i" class="flex items-center gap-2">
                                            <span class="h-1.5 w-1.5 rounded-full bg-neutral-300 dark:bg-neutral-600"></span>
                                            <div class="h-2 flex-1 rounded bg-neutral-100 dark:bg-neutral-800"></div>
                                        </div>
                                    </div>
                                    <div v-else class="mt-3 h-8 animate-pulse rounded bg-neutral-100 dark:bg-neutral-800"></div>

                                    <!-- Tabel -->
                                    <div v-if="isPlaced('tabel')" class="mt-3 grid grid-cols-3 gap-1 rounded border border-neutral-200 p-1.5 dark:border-neutral-700">
                                        <div v-for="i in 6" :key="i" class="h-4 rounded bg-neutral-100 dark:bg-neutral-800"></div>
                                    </div>
                                    <div v-else class="mt-3 h-12 animate-pulse rounded bg-neutral-100 dark:bg-neutral-800"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Blok terbang dari sidebar ke canvas -->
                        <div
                            class="ghost-block pointer-events-none absolute left-3 top-24 z-10"
                            :style="{
                                transform: ghostFlying ? 'translate(13rem, 5rem)' : 'translate(0, 0)',
                                opacity: ghostVisible ? 1 : 0,
                            }"
                        >
                            <div class="flex items-center gap-1.5 rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[11px] font-medium shadow-lg dark:border-neutral-600 dark:bg-neutral-900">
                                <GripVertical class="h-3 w-3 text-neutral-400" />
                                <LayoutTemplate class="h-3 w-3 text-neutral-500" />
                                {{ buildBlocks[activeBlock]?.label ?? 'Blok' }}
                            </div>
                        </div>
                    </div>

                    <p class="mt-4 text-center text-xs text-neutral-400 dark:text-neutral-500">Seret blok ke kanvas — dokumen tersusun, terformat, dan siap ekspor otomatis.</p>
                </div>
            </div>
        </section>

        <!-- Kategori -->
        <section class="border-y border-neutral-200 bg-neutral-50 py-10 dark:border-neutral-800 dark:bg-neutral-900/40">
            <div class="mx-auto max-w-6xl px-4 lg:px-6">
                <p class="text-center text-sm font-medium text-neutral-500 dark:text-neutral-400">Dibuat untuk beragam dokumen akademik</p>
                <div class="mt-5 flex flex-wrap justify-center gap-2">
                    <span
                        v-for="c in categories"
                        :key="c"
                        class="rounded-full border border-neutral-200 bg-white px-4 py-1.5 text-sm text-neutral-700 dark:border-neutral-800 dark:bg-neutral-950 dark:text-neutral-300"
                    >
                        {{ c }}
                    </span>
                </div>
            </div>
        </section>

        <!-- Fitur -->
        <section id="fitur" class="mx-auto max-w-6xl scroll-mt-20 px-4 py-20 lg:px-6">
            <div class="text-center">
                <span class="text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">Fitur</span>
                <h2 class="mt-3 font-serif text-3xl font-bold tracking-tight">Semua yang kamu butuhkan untuk menulis</h2>
                <p class="mx-auto mt-3 max-w-xl text-neutral-500 dark:text-neutral-400">Dari menyusun kerangka hingga ekspor siap cetak — dalam satu alur kerja yang rapi.</p>
            </div>
            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="f in features"
                    :key="f.title"
                    class="relative rounded-xl border p-6 transition-all duration-200"
                    :class="f.comingSoon
                        ? 'border-dashed border-neutral-200 opacity-70 hover:opacity-90 dark:border-neutral-700'
                        : 'border-neutral-200 hover:-translate-y-1 hover:border-neutral-300 dark:border-neutral-800 dark:hover:border-neutral-700'"
                >
                    <span
                        v-if="f.comingSoon"
                        class="absolute right-4 top-4 rounded-full border border-neutral-300 bg-neutral-100 px-2.5 py-0.5 text-[11px] font-semibold text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300"
                    >
                        Segera Hadir
                    </span>
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg border border-neutral-200 text-neutral-700 dark:border-neutral-800 dark:text-neutral-300">
                        <component :is="f.icon" class="h-5 w-5" />
                    </div>
                    <h3 class="mt-4 font-semibold">{{ f.title }}</h3>
                    <p class="mt-1.5 text-sm text-neutral-500 dark:text-neutral-400">{{ f.desc }}</p>
                </div>
            </div>
        </section>

        <!-- Spotlight: AI -->
        <section class="border-t border-neutral-200 bg-neutral-50 py-20 dark:border-neutral-800 dark:bg-neutral-900/40">
            <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 lg:grid-cols-2 lg:px-6">
                <div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-neutral-200 bg-white px-3 py-1 text-xs font-medium text-neutral-600 dark:border-neutral-800 dark:bg-neutral-950 dark:text-neutral-300">
                        <Zap class="h-3.5 w-3.5" />
                        Asisten AI
                    </span>
                    <h2 class="mt-5 font-serif text-3xl font-bold tracking-tight">AI yang bekerja langsung di dokumenmu</h2>
                    <p class="mt-4 text-neutral-500 dark:text-neutral-400">
                        Tidak sekadar chatbot. AI memahami konteks canvas — kamu bisa memintanya membuat abstrak, mengembangkan poin, atau merapikan gaya per blok yang dipilih.
                    </p>
                    <div class="mt-5 flex flex-wrap items-center gap-2">
                        <span class="text-xs font-medium uppercase tracking-wide text-neutral-400 dark:text-neutral-500">Ditenagai oleh</span>
                        <span
                            v-for="engine in landing.ai_engines"
                            :key="engine"
                            class="inline-flex items-center gap-1.5 rounded-full border border-neutral-200 bg-white px-3 py-1 text-xs font-semibold text-neutral-700 dark:border-neutral-800 dark:bg-neutral-950 dark:text-neutral-200"
                        >
                            <Sparkles class="h-3.5 w-3.5 text-neutral-400" />
                            {{ engine }}
                        </span>
                    </div>
                    <ul class="mt-6 space-y-3">
                        <li v-for="t in ['Buat abstrak & ringkasan otomatis', 'Kembangkan ide dari poin yang dipilih', 'Sesuaikan gaya sesuai format kampus']" :key="t" class="flex items-center gap-3 text-sm">
                            <Check class="h-4 w-4 shrink-0 text-neutral-900 dark:text-white" />
                            {{ t }}
                        </li>
                    </ul>
                </div>
                <!-- AI chat mockup -->
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950">
                    <div class="flex items-center gap-2 border-b border-neutral-100 pb-3 dark:border-neutral-800">
                        <Sparkles class="h-4 w-4 text-neutral-400" />
                        <span class="text-sm font-medium">Asisten {{ appName }}</span>
                    </div>
                    <div class="mt-4 space-y-3 text-sm">
                        <transition name="chat">
                            <div v-if="chatStep >= 0" class="ml-auto max-w-[85%] rounded-xl rounded-tr-sm border border-neutral-200 bg-neutral-50 px-3 py-2 dark:border-neutral-800 dark:bg-neutral-900">
                                Buatkan abstrak untuk Bab 1 tentang pengaruh AI pada produktivitas.
                            </div>
                        </transition>
                        <transition name="chat">
                            <div v-if="chatStep >= 1" class="max-w-[85%] rounded-xl rounded-tl-sm bg-neutral-900 px-3 py-2 text-neutral-100 dark:bg-white dark:text-neutral-900">
                                Tentu. Berikut draf abstrak yang bisa kamu sesuaikan...
                            </div>
                        </transition>
                        <transition name="chat">
                            <div v-if="chatStep === 2" class="inline-flex items-center gap-1.5 rounded-xl rounded-tl-sm bg-neutral-900 px-3 py-2.5 dark:bg-white">
                                <span class="h-1.5 w-1.5 animate-typing rounded-full bg-neutral-400"></span>
                                <span class="h-1.5 w-1.5 animate-typing rounded-full bg-neutral-400" style="animation-delay: 0.15s"></span>
                                <span class="h-1.5 w-1.5 animate-typing rounded-full bg-neutral-400" style="animation-delay: 0.3s"></span>
                            </div>
                        </transition>
                    </div>
                </div>
            </div>
        </section>

        <!-- Before / After: Optimizer -->
        <section id="optimizer" class="border-t border-neutral-200 py-20 dark:border-neutral-800">
            <div class="mx-auto max-w-6xl px-4 lg:px-6">
                <div class="text-center">
                    <span class="text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">Optimizer</span>
                    <h2 class="mt-3 font-serif text-3xl font-bold tracking-tight">Tulisanmu jadi lebih manusiawi & orisinal</h2>
                    <p class="mx-auto mt-3 max-w-xl text-neutral-500 dark:text-neutral-400">Geser untuk melihat perubahan sebelum dan sesudah dioptimasi AI.</p>
                </div>

                <div class="mt-10 flex justify-center">
                    <div class="inline-flex rounded-xl border border-neutral-200 p-1 dark:border-neutral-800">
                        <button
                            v-for="t in optimizerTabs"
                            :key="t.id"
                            type="button"
                            class="cursor-pointer rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                            :class="optimizerTab === t.id ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'"
                            @click="optimizerTab = t.id"
                        >
                            {{ t.label }}
                        </button>
                    </div>
                </div>

                <div
                    ref="compareEl"
                    class="relative mx-auto mt-8 max-w-3xl cursor-ew-resize touch-none select-none overflow-hidden rounded-2xl border border-neutral-200 shadow-sm dark:border-neutral-800"
                    @pointerdown.prevent="startDrag"
                >
                    <!-- Sebelum (lapisan dasar) -->
                    <div class="relative bg-neutral-100 px-8 py-10 dark:bg-neutral-800/40">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-neutral-200 px-2.5 py-1 text-xs font-medium text-neutral-600 dark:bg-neutral-700 dark:text-neutral-200">Sebelum</span>
                        <p class="mt-4 text-base leading-relaxed text-neutral-700 dark:text-neutral-200">{{ sample.before }}</p>
                        <p class="mt-4 text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ sample.metric.before }}</p>
                    </div>

                    <!-- Sesudah (ter-reveal dari kiri ke kanan) -->
                    <div
                        class="absolute inset-0 bg-white px-8 py-10 dark:bg-neutral-900"
                        :style="{ clipPath: 'inset(0 0 0 ' + optimizerPos + '%)' }"
                    >
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-neutral-900 px-2.5 py-1 text-xs font-medium text-white dark:bg-white dark:text-neutral-900">Sesudah</span>
                        <p class="mt-4 text-base leading-relaxed text-neutral-800 dark:text-neutral-100">{{ sample.after }}</p>
                        <p class="mt-4 text-sm font-medium text-neutral-900 dark:text-white">{{ sample.metric.after }}</p>
                    </div>

                    <!-- Handle -->
                    <div class="absolute inset-y-0 z-10 w-0.5 -translate-x-1/2 bg-neutral-900/70 dark:bg-white/70" :style="{ left: optimizerPos + '%' }">
                        <div class="absolute left-1/2 top-1/2 flex h-9 w-9 -translate-x-1/2 -translate-y-1/2 items-center justify-center gap-0.5 rounded-full border border-neutral-200 bg-white shadow-lg dark:border-neutral-700 dark:bg-neutral-900">
                            <ChevronDown class="h-4 w-4 -rotate-90 text-neutral-500 dark:text-neutral-400" />
                            <ChevronDown class="h-4 w-4 rotate-90 text-neutral-500 dark:text-neutral-400" />
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Paket -->
        <section id="paket" class="border-t border-neutral-200 bg-neutral-50 py-20 dark:border-neutral-800 dark:bg-neutral-900/40">
            <div class="mx-auto max-w-6xl scroll-mt-20 px-4 lg:px-6">
                <div class="text-center">
                    <span class="text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">Paket</span>
                    <h2 class="mt-3 font-serif text-3xl font-bold tracking-tight">Pilih yang sesuai kebutuhanmu</h2>
                </div>
                <div class="mx-auto mt-12 grid max-w-3xl gap-6 md:grid-cols-2">
                    <div
                        v-for="p in plans"
                        :key="p.name"
                        class="relative flex flex-col rounded-xl border p-6 transition-colors"
                        :class="p.highlight ? 'border-neutral-900 dark:border-white' : 'border-neutral-200 dark:border-neutral-800'"
                    >
                        <h3 class="font-semibold">{{ p.name }}</h3>
                        <p class="mt-2 text-2xl font-bold">
                            {{ p.price }}<span v-if="p.period" class="text-sm font-normal text-neutral-400 dark:text-neutral-500">{{ p.period }}</span>
                        </p>
                        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{{ p.desc }}</p>
                        <ul class="mt-6 flex-1 space-y-3">
                            <li v-for="f in p.features" :key="f" class="flex items-center gap-3 text-sm">
                                <Check class="h-4 w-4 shrink-0 text-neutral-900 dark:text-white" />
                                {{ f }}
                            </li>
                        </ul>
                        <RouterLink
                            to="/register"
                            class="mt-6 inline-flex items-center justify-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-medium transition-colors"
                            :class="p.highlight ? 'border-neutral-900 bg-neutral-900 text-white hover:bg-neutral-700 dark:border-white dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200' : 'border-neutral-200 hover:bg-neutral-100 dark:border-neutral-800 dark:hover:bg-neutral-900'"
                        >
                            {{ p.cta }}
                        </RouterLink>
                    </div>
                </div>

                <!-- Tabel perbandingan paket -->
                <div class="mt-14">
                    <h3 class="text-center text-lg font-semibold">Perbandingan Paket</h3>
                    <div class="mt-6 overflow-x-auto rounded-xl border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-950">
                        <table class="w-full min-w-[560px] border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-neutral-200 dark:border-neutral-800">
                                    <th class="px-4 py-3 text-left font-medium text-neutral-500 dark:text-neutral-400">Fitur</th>
                                    <th
                                        v-for="c in comparisonPlans"
                                        :key="c.key"
                                        class="px-4 py-3 text-center font-semibold"
                                        :class="c.highlight ? 'text-neutral-900 dark:text-white' : 'text-neutral-700 dark:text-neutral-300'"
                                    >
                                        {{ c.label }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(r, i) in comparisonRows"
                                    :key="r.feature"
                                    :class="i % 2 ? 'bg-neutral-50 dark:bg-neutral-900/40' : ''"
                                >
                                    <td class="px-4 py-3 text-neutral-700 dark:text-neutral-300">{{ r.feature }}</td>
                                    <td v-for="c in comparisonPlans" :key="c.key" class="px-4 py-3 text-center">
                                        <Check v-if="r[c.key]" class="mx-auto h-4 w-4 text-neutral-900 dark:text-white" />
                                        <X v-else class="mx-auto h-4 w-4 text-neutral-300 dark:text-neutral-600" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Untuk Siapa -->
        <section id="untuk-siapa" class="mx-auto max-w-6xl scroll-mt-20 px-4 py-20 lg:px-6">
            <div class="text-center">
                <span class="text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">Untuk Siapa</span>
                <h2 class="mt-3 font-serif text-3xl font-bold tracking-tight">Dibuat untuk setiap penulis karya ilmiah</h2>
                <p class="mx-auto mt-3 max-w-xl text-neutral-500 dark:text-neutral-400">Dari mahasiswa hingga institusi — {{ appName }} menyesuaikan kebutuhan setiap jenis pengguna.</p>
            </div>
            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="a in audiences"
                    :key="a.title"
                    class="relative rounded-xl border p-6 transition-all duration-200"
                    :class="a.comingSoon
                        ? 'border-dashed border-neutral-200 opacity-70 hover:opacity-90 dark:border-neutral-700'
                        : 'border-neutral-200 hover:-translate-y-1 hover:border-neutral-300 dark:border-neutral-800 dark:hover:border-neutral-700'"
                >
                    <span
                        v-if="a.comingSoon"
                        class="absolute right-4 top-4 rounded-full border border-neutral-300 bg-neutral-100 px-2.5 py-0.5 text-[11px] font-semibold text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300"
                    >
                        Segera Hadir
                    </span>
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg border border-neutral-200 text-neutral-700 dark:border-neutral-800 dark:text-neutral-300">
                        <component :is="a.icon" class="h-5 w-5" />
                    </div>
                    <h3 class="mt-4 font-semibold">{{ a.title }}</h3>
                    <p class="mt-1.5 text-sm text-neutral-500 dark:text-neutral-400">{{ a.desc }}</p>
                </div>
            </div>
        </section>

        <!-- Mahasiswa universitas -->
        <section class="border-t border-neutral-200 py-12 dark:border-neutral-800">
            <div class="mx-auto max-w-6xl px-4 lg:px-6">
                <p class="text-center text-sm font-medium text-neutral-500 dark:text-neutral-400">Dipercaya mahasiswa dari berbagai universitas</p>
                <div class="relative mt-6 overflow-hidden">
                    <div class="pointer-events-none absolute inset-y-0 left-0 z-10 w-16 bg-gradient-to-r from-white to-transparent dark:from-neutral-950"></div>
                    <div class="pointer-events-none absolute inset-y-0 right-0 z-10 w-16 bg-gradient-to-l from-white to-transparent dark:from-neutral-950"></div>
                    <div class="flex w-max animate-marquee">
                        <div v-for="n in 2" :key="n" class="flex shrink-0 items-center gap-3 pr-3">
                            <span
                                v-for="u in universities"
                                :key="u + n"
                                class="whitespace-nowrap rounded-full border border-neutral-200 bg-neutral-50 px-5 py-2 text-sm text-neutral-600 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300"
                            >
                                {{ u }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Cara kerja -->
        <section id="cara-kerja" class="mx-auto max-w-6xl scroll-mt-20 px-4 py-20 lg:px-6">
            <div class="text-center">
                <span class="text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">Cara Kerja</span>
                <h2 class="mt-3 font-serif text-3xl font-bold tracking-tight">Tiga langkah mulai menulis</h2>
                <p class="mx-auto mt-3 max-w-xl text-neutral-500 dark:text-neutral-400">Dari nol sampai dokumen jadi — cukup tiga langkah sederhana.</p>
            </div>
            <div class="mt-12 grid gap-6 md:grid-cols-3">
                <div v-for="s in steps" :key="s.no" class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-800">
                    <div class="flex items-center justify-between">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg border border-neutral-200 text-neutral-700 dark:border-neutral-800 dark:text-neutral-300">
                            <component :is="s.icon" class="h-5 w-5" />
                        </div>
                        <span class="font-mono text-sm font-semibold text-neutral-400 dark:text-neutral-500">{{ s.no }}</span>
                    </div>
                    <h3 class="mt-4 font-semibold">{{ s.title }}</h3>
                    <p class="mt-1.5 text-sm text-neutral-500 dark:text-neutral-400">{{ s.desc }}</p>
                    <ul class="mt-4 space-y-2">
                        <li v-for="p in s.points" :key="p" class="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-300">
                            <Check class="h-4 w-4 shrink-0 text-neutral-400 dark:text-neutral-500" />
                            {{ p }}
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Perbandingan -->
        <section id="perbandingan" class="mx-auto max-w-6xl scroll-mt-20 px-4 py-20 lg:px-6">
            <div class="text-center">
                <span class="text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">Perbandingan</span>
                <h2 class="mt-3 font-serif text-3xl font-bold tracking-tight">Berhenti berjuang dengan format, mulai menulis</h2>
                <p class="mx-auto mt-3 max-w-xl text-neutral-500 dark:text-neutral-400">Cara lama vs cara {{ appName }} — lihat apa yang berubah.</p>
            </div>
            <div class="mt-12 grid gap-6 lg:grid-cols-2">
                <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-800">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">Cara Manual</h3>
                    <ul class="mt-5 space-y-4">
                        <li v-for="c in comparison" :key="'old-' + c.aspect" class="flex gap-3 text-sm">
                            <X class="mt-0.5 h-4 w-4 shrink-0 text-neutral-300 dark:text-neutral-600" />
                            <div>
                                <p class="font-medium text-neutral-700 dark:text-neutral-300">{{ c.aspect }}</p>
                                <p class="text-neutral-500 dark:text-neutral-400">{{ c.old }}</p>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="rounded-xl border border-neutral-900 bg-neutral-900 p-6 text-white dark:border-white dark:bg-white dark:text-neutral-900">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">Dengan {{ appName }}</h3>
                    <ul class="mt-5 space-y-4">
                        <li v-for="c in comparison" :key="'new-' + c.aspect" class="flex gap-3 text-sm">
                            <Check class="mt-0.5 h-4 w-4 shrink-0 text-neutral-200 dark:text-neutral-400" />
                            <div>
                                <p class="font-medium">{{ c.aspect }}</p>
                                <p class="text-neutral-300 dark:text-neutral-500">{{ c.new }}</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section id="faq" class="mx-auto max-w-3xl scroll-mt-20 px-4 py-20 lg:px-6">
            <div class="text-center">
                <span class="text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">FAQ</span>
                <h2 class="mt-3 font-serif text-3xl font-bold tracking-tight">Pertanyaan umum</h2>
            </div>
            <div class="mt-10 space-y-3">
                <div v-for="(f, i) in faqs" :key="f.q" class="rounded-lg border border-neutral-200 dark:border-neutral-800">
                    <button
                        type="button"
                        class="flex w-full cursor-pointer items-center justify-between gap-4 px-5 py-4 text-left"
                        @click="openFaq = openFaq === i ? -1 : i"
                    >
                        <span class="font-medium">{{ f.q }}</span>
                        <ChevronDown class="h-4 w-4 shrink-0 text-neutral-400 transition-transform" :class="openFaq === i ? 'rotate-180' : ''" />
                    </button>
                    <div v-if="openFaq === i" class="border-t border-neutral-200 px-5 py-4 text-sm text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                        {{ f.a }}
                    </div>
                </div>
            </div>
        </section>

        <!-- Testimoni -->
        <section id="testimoni" class="mx-auto max-w-6xl scroll-mt-20 px-4 py-20 lg:px-6">
            <div class="text-center">
                <span class="text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">Testimoni</span>
                <h2 class="mt-3 font-serif text-3xl font-bold tracking-tight">Kata mereka yang sudah menulis bersama {{ appName }}</h2>
                <p class="mx-auto mt-3 max-w-xl text-neutral-500 dark:text-neutral-400">Dari mahasiswa hingga penulis profesional — begini pengalaman mereka.</p>
            </div>
            <div v-if="reviewsLoading" class="mt-12 text-center text-sm text-neutral-400 dark:text-neutral-500">
                Memuat ulasan…
            </div>

            <div v-else-if="reviews.length === 0" class="mt-12 rounded-xl border border-dashed border-neutral-300 px-6 py-12 text-center dark:border-neutral-700">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Belum ada ulasan. Jadilah yang pertama berbagi pengalaman.</p>
            </div>

            <div v-else class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <figure
                    v-for="t in reviews"
                    :key="t.id"
                    class="flex flex-col rounded-xl border border-neutral-200 p-6 transition-all duration-200 hover:-translate-y-1 hover:border-neutral-300 dark:border-neutral-800 dark:hover:border-neutral-700"
                >
                    <div class="flex gap-0.5 text-neutral-900 dark:text-white">
                        <Star v-for="i in 5" :key="i" class="h-4 w-4" :class="i <= t.rating ? 'fill-current' : 'fill-transparent text-neutral-300 dark:text-neutral-600'" />
                    </div>
                    <blockquote class="mt-4 flex-1 text-sm leading-relaxed text-neutral-700 dark:text-neutral-300">
                        "{{ t.text }}"
                    </blockquote>
                    <figcaption class="mt-5 flex items-center gap-3 border-t border-neutral-100 pt-4 dark:border-neutral-800">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-sm font-semibold text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                            {{ t.initial }}
                        </span>
                        <div>
                            <p class="text-sm font-medium">{{ t.name }}</p>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">Pengguna {{ appName }}</p>
                        </div>
                    </figcaption>
                </figure>
            </div>

            <div class="mt-10 text-center">
                <RouterLink to="/reviews" class="inline-flex items-center gap-1.5 text-sm font-medium text-neutral-700 underline-offset-4 hover:underline dark:text-neutral-200">
                    Lihat semua rating
                    <ArrowRight class="h-4 w-4" />
                </RouterLink>
            </div>
        </section>

        <!-- CTA -->
        <section class="border-t border-neutral-200 py-20 dark:border-neutral-800">
            <div class="mx-auto max-w-3xl px-4 text-center lg:px-6">
                <h2 class="font-serif text-3xl font-bold tracking-tight">Jangan biarkan format & sitasi menghabiskan waktumu.</h2>
                <p class="mx-auto mt-3 max-w-md text-neutral-500 dark:text-neutral-400">Mulai sekarang dan selesaikan karya ilmiah pertamamu dengan format yang sudah beres otomatis.</p>
                <div class="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
                    <RouterLink to="/register" class="inline-flex items-center justify-center gap-2 rounded-lg border border-neutral-900 bg-neutral-900 px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-neutral-700 dark:border-white dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200">
                        Mulai Menulis Gratis
                        <ArrowRight class="h-4 w-4" />
                    </RouterLink>
                    <RouterLink to="/login" class="inline-flex items-center justify-center gap-2 rounded-lg border border-neutral-200 px-6 py-3 text-sm font-medium transition-colors hover:bg-neutral-100 dark:border-neutral-800 dark:hover:bg-neutral-900">
                        Masuk
                    </RouterLink>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-neutral-200 py-10 dark:border-neutral-800">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-4 text-sm text-neutral-500 dark:text-neutral-400 md:flex-row lg:px-6">
                <div class="inline-flex items-center gap-2 font-semibold text-neutral-900 dark:text-white">
                    <PenLine class="h-4 w-4" />
                    {{ appName }}
                </div>
                <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2">
                    <a href="#fitur" class="hover:text-neutral-900 dark:hover:text-white">Fitur</a>
                    <a href="#paket" class="hover:text-neutral-900 dark:hover:text-white">Paket</a>
                    <a href="#faq" class="hover:text-neutral-900 dark:hover:text-white">FAQ</a>
                </div>
                <p>&copy; 2026 {{ appName }}. Semua hak dilindungi.</p>
            </div>
        </footer>

        <!-- Chatbox mengambang -->
        <FloatingChat :context="'landing'" :suggestions="chatSuggestions" />
    </div>
</template>

<style scoped>
@keyframes float {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-8px);
    }
}
.animate-float {
    animation: float 6s ease-in-out infinite;
}

@keyframes blink {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0;
    }
}
.animate-blink {
    animation: blink 1s step-end infinite;
}

.ghost-block {
    transition: transform 0.7s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.25s ease;
}

@keyframes typing {
    0%,
    60%,
    100% {
        opacity: 0.3;
        transform: translateY(0);
    }
    30% {
        opacity: 1;
        transform: translateY(-3px);
    }
}
.animate-typing {
    animation: typing 1.2s ease-in-out infinite;
}

@keyframes marquee {
    from {
        transform: translateX(0);
    }
    to {
        transform: translateX(-50%);
    }
}
.animate-marquee {
    animation: marquee 30s linear infinite;
}

.chat-enter-active,
.chat-leave-active {
    transition: all 0.3s ease;
}
.chat-enter-from,
.chat-leave-to {
    opacity: 0;
    transform: translateY(8px);
}
</style>
