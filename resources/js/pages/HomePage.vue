<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { RouterLink } from 'vue-router';
import {
    PenLine, Sparkles, LayoutTemplate, FolderOpen, Type, BookOpen,
    Check, ArrowRight, Menu, X, Image, ShieldCheck,
    Zap, ChevronDown, GripVertical, GraduationCap, Users, Building2,
    ScanSearch, ClipboardCheck, Star,
} from 'lucide-vue-next';
import FloatingChat from '../components/FloatingChat.vue';

const menuOpen = ref(false);
const openFaq = ref(0);

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
    { icon: Sparkles, title: 'Asisten AI Kontekstual', desc: 'Minta AI membuat abstrak, merapikan paragraf, atau menyesuaikan gaya langsung pada canvas dokumenmu.' },
    { icon: ScanSearch, title: 'Turnitin AI Optimizer', desc: 'Sesuaikan gaya penulisan agar lolos deteksi AI (Turnitin) tanpa mengubah makna tulisanmu.' },
    { icon: ClipboardCheck, title: 'Plagiarism Optimizer', desc: 'Parafrase dan rapikan kalimat agar skor kemiripan turun, tetap natural dan mudah dibaca.' },
    { icon: LayoutTemplate, title: 'Block Canvas', desc: 'Susun dokumen per blok — judul, paragraf, gambar, daftar, tabel, hingga blok kode.' },
    { icon: Type, title: 'Gaya Per Blok', desc: 'Atur spasi baris, warna teks, dan font custom (TTF/OTF) per bagian, bukan sekadar global.' },
    { icon: FolderOpen, title: 'File Manager', desc: 'Kelola gambar dan file dalam satu tempat, lalu pakai langsung saat sedang menulis.' },
    { icon: BookOpen, title: 'Sitasi & Daftar Pustaka', desc: 'Daftar pustaka dan rujukan akademik tertata otomatis sesuai gaya sitasi.' },
    { icon: ShieldCheck, title: 'Siap Cetak & Ekspor', desc: 'Atur margin, orientasi, dan ukuran halaman lalu ekspor ke format siap cetak.' },
];

const steps = [
    { no: '01', title: 'Buat Project', desc: 'Tentukan judul, kategori, format, dan orientasi halaman dokumen.' },
    { no: '02', title: 'Susun & Tulis', desc: 'Atur blok dokumen, minta bantuan AI, dan rapikan gaya per bagian.' },
    { no: '03', title: 'Ekspor & Selesai', desc: 'Unduh dokumen yang rapi dan sesuai standar kampusmu.' },
];

const plans = [
    { name: 'Gratis', price: 'Rp 0', desc: 'Untuk mencoba dan menulis ringan.', features: ['Block canvas dasar', 'Ekspor dokumen', 'Template dasar', 'Penyimpanan lokal'] },
    { name: 'Koin', price: 'Fleksibel', desc: 'Topup koin untuk fitur AI & premium.', features: ['Asisten AI penuh', 'Semua template', 'File manager', 'Dukungan prioritas'], highlight: true },
    { name: 'Kampus', price: 'Custom', desc: 'Untuk institusi & agensi (B2B).', features: ['Manajemen seat anggota', 'Template kampus', 'Integrasi & API', 'Dukungan khusus'] },
];

const faqs = [
    { q: 'Apakah Tulisin cocok untuk selain skripsi?', a: 'Ya. Tulisin dirancang untuk beragam dokumen: skripsi, tesis, makalah, jurnal, laporan, proposal, hingga esai.' },
    { q: 'Bagaimana cara kerja asisten AI-nya?', a: 'AI bekerja langsung pada canvas dokumen. Kamu bisa memintanya membuat abstrak, merapikan paragraf, atau menyesuaikan gaya sesuai blok yang dipilih.' },
    { q: 'Apakah dokumen saya aman?', a: 'Dokumen hanya bisa diakses oleh akunmu. Fitur berbagi publik (Lists Project) bersifat opsional dan hanya lihat (read-only).' },
    { q: 'Bisakah memakai font kampus sendiri?', a: 'Bisa. Kamu dapat mengunggah font custom (TTF/OTF/WOFF) dan menerapkannya ke bagian dokumen tertentu.' },
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
    { icon: BookOpen, title: 'Dosen & Peneliti', desc: 'Susun jurnal, makalah, dan laporan penelitian dengan sitasi serta gaya penulisan yang konsisten.' },
    { icon: Users, title: 'Agency Penulisan', desc: 'Kelola banyak proyek klien, template, dan tim penulis dalam satu alur kerja yang teratur.' },
    { icon: Building2, title: 'Kampus & Institusi', desc: 'Standarisasi format dokumen, kelola seat anggota, hingga integrasi sistem (B2B).' },
];

// Nama kampus (placeholder) untuk section "Mahasiswa universitas".
const universities = [
    'Universitas Indonesia', 'Universitas Gadjah Mada', 'Institut Teknologi Bandung',
    'Universitas Airlangga', 'Universitas Diponegoro', 'Universitas Padjadjaran',
    'Institut Teknologi Sepuluh Nopember', 'Universitas Brawijaya', 'Universitas Sebelas Maret',
    'Universitas Hasanuddin', 'Universitas Sumatera Utara', 'Universitas Andalas',
];

// Testimoni / review pengguna.
const testimonials = [
    { name: 'Rani Puspita', role: 'Mahasiswa S1 · Universitas Indonesia', rating: 5, quote: 'Skripsi saya selesai 2 minggu lebih cepat. Turnitin AI Optimizer-nya bikin saya tenang soal deteksi AI.' },
    { name: 'Bagas Pratama', role: 'Mahasiswa S2 · ITB', rating: 5, quote: 'Format otomatisnya persis standar kampus. Tinggal fokus nulis, sisanya Tulisin yang urus.' },
    { name: 'Dinda Ayu', role: 'Mahasiswa S1 · UGM', rating: 5, quote: 'Asisten AI-nya paham konteks. Abstrak dan daftar pustaka beres dalam hitungan menit.' },
    { name: 'Andi Saputra', role: 'Penulis · Agency Skripsi', rating: 4, quote: 'Kelola banyak proyek klien jadi lebih rapi. Template per klien benar-benar menghemat waktu.' },
    { name: 'Maya Lestari', role: 'Mahasiswa S2 · Universitas Airlangga', rating: 5, quote: 'Plagiarism Optimizer-nya natural, paragraf tetap enak dibaca tapi skor kemiripan turun.' },
    { name: 'Rizky Ramadhan', role: 'Dosen · Universitas Diponegoro', rating: 5, quote: 'Untuk menyusun modul dan jurnal, gaya per blok dan sitasi otomatisnya sangat membantu.' },
];

const chatSuggestions = [
    'Bagaimana cara kerja Tulisin?',
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
});

onBeforeUnmount(() => {
    cancelAnimationFrame(optimizerRaf);
    window.removeEventListener('pointermove', onOptimizerDrag);
    window.removeEventListener('pointerup', endOptimizerDrag);
});
</script>

<template>
    <div class="min-h-screen bg-white text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
        <!-- Navbar -->
        <header class="sticky top-0 z-30 px-4 pt-4 lg:px-6">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between rounded-2xl border border-neutral-200 bg-white/80 px-4 shadow-sm backdrop-blur dark:border-neutral-800 dark:bg-neutral-950/80 lg:px-5">
                <RouterLink to="/" class="inline-flex items-center gap-2 text-lg font-bold tracking-tight">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-neutral-900 text-white dark:bg-white dark:text-neutral-950">
                        <PenLine class="h-4 w-4" />
                    </span>
                    Tulisin
                </RouterLink>

                <nav class="hidden items-center gap-1 text-sm text-neutral-600 dark:text-neutral-300 md:flex">
                    <a href="#fitur" class="rounded-lg px-3 py-2 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:hover:bg-neutral-900 dark:hover:text-white">Fitur</a>
                    <a href="#untuk-siapa" class="rounded-lg px-3 py-2 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:hover:bg-neutral-900 dark:hover:text-white">Untuk Siapa</a>
                    <a href="#testimoni" class="rounded-lg px-3 py-2 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:hover:bg-neutral-900 dark:hover:text-white">Testimoni</a>
                    <a href="#paket" class="rounded-lg px-3 py-2 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:hover:bg-neutral-900 dark:hover:text-white">Paket</a>
                </nav>

                <div class="hidden items-center gap-2 md:flex">
                    <RouterLink to="/login" class="rounded-lg px-3 py-2 text-sm font-medium transition-colors hover:bg-neutral-100 dark:hover:bg-neutral-900">Masuk</RouterLink>
                    <RouterLink to="/register" class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-900 bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-neutral-700 dark:border-white dark:bg-white dark:text-neutral-950 dark:hover:bg-neutral-200">
                        Mulai Menulis
                        <ArrowRight class="h-4 w-4" />
                    </RouterLink>
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

            <div v-if="menuOpen" class="mx-auto mt-2 max-w-6xl rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-950 md:hidden">
                <nav class="flex flex-col gap-1 text-sm">
                    <a href="#fitur" class="rounded-lg px-3 py-2 text-neutral-600 dark:text-neutral-300" @click="menuOpen = false">Fitur</a>
                    <a href="#untuk-siapa" class="rounded-lg px-3 py-2 text-neutral-600 dark:text-neutral-300" @click="menuOpen = false">Untuk Siapa</a>
                    <a href="#testimoni" class="rounded-lg px-3 py-2 text-neutral-600 dark:text-neutral-300" @click="menuOpen = false">Testimoni</a>
                    <a href="#paket" class="rounded-lg px-3 py-2 text-neutral-600 dark:text-neutral-300" @click="menuOpen = false">Paket</a>
                    <div class="mt-2 flex flex-col gap-2 border-t border-neutral-200 pt-3 dark:border-neutral-800">
                        <RouterLink to="/login" class="rounded-lg border border-neutral-200 px-4 py-2 text-center font-medium dark:border-neutral-800" @click="menuOpen = false">Masuk</RouterLink>
                        <RouterLink to="/register" class="rounded-lg border border-neutral-900 bg-neutral-900 px-4 py-2 text-center font-medium text-white dark:border-white dark:bg-white dark:text-neutral-950" @click="menuOpen = false">Mulai Menulis</RouterLink>
                    </div>
                </nav>
            </div>
        </header>

        <!-- Hero -->
        <section class="relative overflow-hidden">
            <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 py-16 lg:grid-cols-2 lg:px-6 lg:py-24">
                <div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-neutral-200 bg-neutral-50 px-3 py-1 text-xs font-medium text-neutral-600 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300">
                        <Sparkles class="h-3.5 w-3.5" />
                        Platform penulisan akademik berbasis AI
                    </span>
                    <h1 class="mt-6 font-serif text-4xl font-bold leading-[1.1] tracking-tight lg:text-5xl">
                        Tulis karya ilmiah yang rapi, lebih cepat.
                    </h1>
                    <p class="mt-4 max-w-lg text-lg text-neutral-500 dark:text-neutral-400">
                        Tulisin menyatukan canvas dokumen, asisten AI, dan format akademik dalam satu tempat — dari judul sampai daftar pustaka.
                    </p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <RouterLink to="/register" class="inline-flex items-center justify-center gap-2 rounded-lg border border-neutral-900 bg-neutral-900 px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-neutral-700 dark:border-white dark:bg-white dark:text-neutral-950 dark:hover:bg-neutral-200">
                            Mulai Gratis
                            <ArrowRight class="h-4 w-4" />
                        </RouterLink>
                        <a href="#fitur" class="inline-flex items-center justify-center gap-2 rounded-lg border border-neutral-200 px-6 py-3 text-sm font-medium transition-colors hover:bg-neutral-100 dark:border-neutral-800 dark:hover:bg-neutral-900">
                            Lihat Fitur
                        </a>
                    </div>
                    <p class="mt-4 text-xs text-neutral-400 dark:text-neutral-500">Tanpa kartu kredit · Bisa ekspor siap cetak</p>
                </div>

                <!-- Product mockup -->
                <div class="relative animate-float">
                    <div class="absolute -inset-6 rounded-3xl bg-gradient-to-tr from-neutral-200/60 to-transparent blur-2xl dark:from-neutral-800/60"></div>
                    <div class="relative overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-2xl dark:border-neutral-800 dark:bg-neutral-900">
                        <!-- title bar -->
                        <div class="flex items-center gap-2 border-b border-neutral-200 px-4 py-2.5 dark:border-neutral-800">
                            <span class="h-2.5 w-2.5 rounded-full bg-red-400"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-yellow-400"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-green-400"></span>
                            <span class="ml-3 flex-1 truncate rounded-md bg-neutral-100 px-3 py-1 text-xs text-neutral-500 dark:bg-neutral-800">tulisin.app/project/skripsi</span>
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
                                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-400"></span>
                                            Menyimpan…
                                        </span>
                                        <span v-else-if="saveState === 'saved'" class="inline-flex items-center gap-1 text-emerald-500">
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
                    class="rounded-xl border border-neutral-200 p-6 transition-all duration-200 hover:-translate-y-1 hover:border-neutral-300 dark:border-neutral-800 dark:hover:border-neutral-700"
                >
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
                    <ul class="mt-6 space-y-3">
                        <li v-for="t in ['Buat abstrak & ringkasan otomatis', 'Kembangkan ide dari poin yang dipilih', 'Sesuaikan gaya sesuai format kampus']" :key="t" class="flex items-center gap-3 text-sm">
                            <Check class="h-4 w-4 shrink-0 text-emerald-500" />
                            {{ t }}
                        </li>
                    </ul>
                </div>
                <!-- AI chat mockup -->
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950">
                    <div class="flex items-center gap-2 border-b border-neutral-100 pb-3 dark:border-neutral-800">
                        <Sparkles class="h-4 w-4 text-neutral-400" />
                        <span class="text-sm font-medium">Asisten Tulisin</span>
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
                            :class="optimizerTab === t.id ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-950' : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'"
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
                    <div class="relative bg-red-50/50 px-8 py-10 dark:bg-red-950/10">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-2.5 py-1 text-xs font-medium text-red-600 dark:bg-red-900/40 dark:text-red-300">Sebelum</span>
                        <p class="mt-4 text-base leading-relaxed text-neutral-700 dark:text-neutral-200">{{ sample.before }}</p>
                        <p class="mt-4 text-sm font-medium text-red-500 dark:text-red-400">{{ sample.metric.before }}</p>
                    </div>

                    <!-- Sesudah (ter-reveal dari kiri ke kanan) -->
                    <div
                        class="absolute inset-0 bg-emerald-50/70 px-8 py-10 dark:bg-emerald-950/20"
                        :style="{ clipPath: 'inset(0 0 0 ' + optimizerPos + '%)' }"
                    >
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Sesudah</span>
                        <p class="mt-4 text-base leading-relaxed text-neutral-800 dark:text-neutral-100">{{ sample.after }}</p>
                        <p class="mt-4 text-sm font-medium text-emerald-600 dark:text-emerald-400">{{ sample.metric.after }}</p>
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

        <!-- Untuk Siapa -->
        <section id="untuk-siapa" class="mx-auto max-w-6xl scroll-mt-20 px-4 py-20 lg:px-6">
            <div class="text-center">
                <span class="text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">Untuk Siapa</span>
                <h2 class="mt-3 font-serif text-3xl font-bold tracking-tight">Dibuat untuk setiap penulis karya ilmiah</h2>
                <p class="mx-auto mt-3 max-w-xl text-neutral-500 dark:text-neutral-400">Dari mahasiswa hingga institusi — Tulisin menyesuaikan kebutuhan setiap jenis pengguna.</p>
            </div>
            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="a in audiences" :key="a.title" class="rounded-xl border border-neutral-200 p-6 transition-all duration-200 hover:-translate-y-1 hover:border-neutral-300 dark:border-neutral-800 dark:hover:border-neutral-700">
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

        <!-- Testimoni -->
        <section id="testimoni" class="mx-auto max-w-6xl scroll-mt-20 px-4 py-20 lg:px-6">
            <div class="text-center">
                <span class="text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">Testimoni</span>
                <h2 class="mt-3 font-serif text-3xl font-bold tracking-tight">Kata mereka yang sudah menulis bersama Tulisin</h2>
                <p class="mx-auto mt-3 max-w-xl text-neutral-500 dark:text-neutral-400">Dari mahasiswa hingga penulis profesional — begini pengalaman mereka.</p>
            </div>
            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <figure
                    v-for="t in testimonials"
                    :key="t.name"
                    class="flex flex-col rounded-xl border border-neutral-200 p-6 transition-all duration-200 hover:-translate-y-1 hover:border-neutral-300 dark:border-neutral-800 dark:hover:border-neutral-700"
                >
                    <div class="flex gap-0.5 text-amber-400">
                        <Star v-for="i in 5" :key="i" class="h-4 w-4" :class="i <= t.rating ? 'fill-current' : 'fill-transparent text-neutral-300 dark:text-neutral-600'" />
                    </div>
                    <blockquote class="mt-4 flex-1 text-sm leading-relaxed text-neutral-700 dark:text-neutral-300">
                        "{{ t.quote }}"
                    </blockquote>
                    <figcaption class="mt-5 flex items-center gap-3 border-t border-neutral-100 pt-4 dark:border-neutral-800">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-sm font-semibold text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                            {{ t.name[0] }}
                        </span>
                        <div>
                            <p class="text-sm font-medium">{{ t.name }}</p>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ t.role }}</p>
                        </div>
                    </figcaption>
                </figure>
            </div>
        </section>

        <!-- Cara kerja -->
        <section id="cara-kerja" class="mx-auto max-w-6xl scroll-mt-20 px-4 py-20 lg:px-6">
            <div class="text-center">
                <span class="text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">Cara Kerja</span>
                <h2 class="mt-3 font-serif text-3xl font-bold tracking-tight">Tiga langkah mulai menulis</h2>
            </div>
            <div class="mt-12 grid gap-6 md:grid-cols-3">
                <div v-for="s in steps" :key="s.no" class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-800">
                    <span class="font-mono text-3xl font-bold text-neutral-200 dark:text-neutral-700">{{ s.no }}</span>
                    <h3 class="mt-3 font-semibold">{{ s.title }}</h3>
                    <p class="mt-1.5 text-sm text-neutral-500 dark:text-neutral-400">{{ s.desc }}</p>
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
                <div class="mt-12 grid gap-6 md:grid-cols-3">
                    <div
                        v-for="p in plans"
                        :key="p.name"
                        class="relative flex flex-col rounded-xl border p-6 transition-colors"
                        :class="p.highlight ? 'border-neutral-900 dark:border-white' : 'border-neutral-200 dark:border-neutral-800'"
                    >
                        <h3 class="font-semibold">{{ p.name }}</h3>
                        <p class="mt-2 text-2xl font-bold">{{ p.price }}</p>
                        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{{ p.desc }}</p>
                        <ul class="mt-6 flex-1 space-y-3">
                            <li v-for="f in p.features" :key="f" class="flex items-center gap-3 text-sm">
                                <Check class="h-4 w-4 shrink-0 text-emerald-500" />
                                {{ f }}
                            </li>
                        </ul>
                        <RouterLink
                            to="/register"
                            class="mt-6 inline-flex items-center justify-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-medium transition-colors"
                            :class="p.highlight ? 'border-neutral-900 bg-neutral-900 text-white hover:bg-neutral-700 dark:border-white dark:bg-white dark:text-neutral-950 dark:hover:bg-neutral-200' : 'border-neutral-200 hover:bg-neutral-100 dark:border-neutral-800 dark:hover:bg-neutral-900'"
                        >
                            Mulai
                        </RouterLink>
                    </div>
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

        <!-- CTA -->
        <section class="border-t border-neutral-200 py-20 dark:border-neutral-800">
            <div class="mx-auto max-w-3xl px-4 text-center lg:px-6">
                <h2 class="font-serif text-3xl font-bold tracking-tight">Siap menyusun karya terbaikmu?</h2>
                <p class="mx-auto mt-3 max-w-md text-neutral-500 dark:text-neutral-400">Buat akun gratis dan mulai tulis dokumen pertamamu sekarang.</p>
                <div class="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
                    <RouterLink to="/register" class="inline-flex items-center justify-center gap-2 rounded-lg border border-neutral-900 bg-neutral-900 px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-neutral-700 dark:border-white dark:bg-white dark:text-neutral-950 dark:hover:bg-neutral-200">
                        Buat Akun Gratis
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
                    Tulisin
                </div>
                <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2">
                    <a href="#fitur" class="hover:text-neutral-900 dark:hover:text-white">Fitur</a>
                    <a href="#paket" class="hover:text-neutral-900 dark:hover:text-white">Paket</a>
                    <a href="#faq" class="hover:text-neutral-900 dark:hover:text-white">FAQ</a>
                </div>
                <p>&copy; 2026 Tulisin. Semua hak dilindungi.</p>
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
