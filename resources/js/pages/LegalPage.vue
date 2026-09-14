<script setup>
import { computed } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { PenLine, ArrowLeft, Mail, Building2, Clock } from 'lucide-vue-next';
import appName from '../utils/appName';

const route = useRoute();

const COMPANY = 'PT Kerja Tanpa Batas';

const PAGES = {
    terms: {
        title: 'Syarat & Ketentuan',
        updated: 'Terakhir diperbarui: 1 Januari 2026',
        intro: `Selamat datang di ${appName}. Dengan mengakses atau menggunakan layanan kami, kamu menyetujui syarat dan ketentuan berikut. Mohon baca dengan saksama.`,
        sections: [
            { heading: 'Layanan', body: `${appName} menyediakan platform penulisan akademik berbasis kecerdasan buatan untuk membantu menyusun skripsi, tesis, disertasi, makalah, dan karya ilmiah lainnya. Layanan disediakan "sebagaimana adanya" dan dapat berubah sewaktu-waktu.` },
            { heading: 'Akun Pengguna', body: 'Kamu bertanggung jawab menjaga kerahasiaan kredensial akun dan semua aktivitas yang terjadi pada akunmu. Data yang kamu berikan saat pendaftaran harus benar dan akurat.' },
            { heading: 'Langganan & Pembayaran', body: 'Fitur berbayar seperti unduhan PDF, Agent Canvas, Turnitin, dan Plagiarism memerlukan langganan aktif. Pembayaran dilakukan melalui penyedia pihak ketiga (QRIS) dan diproses sesuai ketentuan penyedia pembayaran.' },
            { heading: 'Koin & Saldo', body: 'Koin digunakan untuk mengakses fitur tertentu. Saldo koin tidak dapat diuangkan kembali kecuali dinyatakan lain dalam ketentuan khusus. Koin yang kedaluwarsa tidak dapat dikembalikan.' },
            { heading: 'Program Afiliasi', body: 'Komisi afiliasi diberikan saat referral melakukan pembelian langganan pertama kali. Penarikan komisi dapat dilakukan dalam bentuk koin atau transfer bank dengan ketentuan yang berlaku.' },
            { heading: 'Larangan', body: 'Kamu tidak diperkenankan menyalahgunakan layanan, menyalin atau mendistribusikan konten secara tidak sah, atau menggunakan layanan untuk kegiatan yang melanggar hukum.' },
            { heading: 'Kekayaan Intelektual', body: `Seluruh merek, desain, dan teknologi ${appName} merupakan milik ${COMPANY}. Kamu tetap memegang hak atas dokumen yang kamu buat menggunakan layanan kami.` },
            { heading: 'Batasan Tanggung Jawab', body: `${appName} tidak bertanggung jawab atas kerugian tidak langsung yang timbul dari penggunaan layanan. Hasil AI bersifat alat bantu dan bukan pengganti bimbingan akademik resmi.` },
            { heading: 'Perubahan Ketentuan', body: 'Kami dapat memperbarui syarat & ketentuan ini dari waktu ke waktu. Perubahan akan diberitahukan melalui platform dan berlaku setelah dipublikasikan.' },
        ],
    },
    privacy: {
        title: 'Kebijakan Privasi',
        updated: 'Terakhir diperbarui: 1 Januari 2026',
        intro: `Kebijakan ini menjelaskan bagaimana ${appName} mengumpulkan, menggunakan, dan melindungi data pribadi kamu.`,
        sections: [
            { heading: 'Data yang Dikumpulkan', body: 'Kami mengumpulkan nama, alamat email, nomor telepon, dan data akademik yang kamu berikan saat pendaftaran dan onboarding. Kami juga mencatat aktivitas penggunaan layanan.' },
            { heading: 'Penggunaan Data', body: 'Data digunakan untuk menyediakan dan meningkatkan layanan, memproses pembayaran, mengirim notifikasi penting, serta memberikan pengalaman yang lebih personal.' },
            { heading: 'Cookie', body: 'Kami menggunakan cookie untuk menjaga sesi login, menganalisis lalu lintas, dan mengingat preferensi kamu.' },
            { heading: 'Keamanan Data', body: 'Kami menerapkan langkah keamanan yang wajar untuk melindungi data dari akses, perubahan, atau pengungkapan yang tidak sah.' },
            { heading: 'Pihak Ketiga', body: 'Data dapat dibagikan kepada penyedia layanan pembayaran dan analitik sebatas yang diperlukan untuk menjalankan layanan. Kami tidak menjual data pribadi kamu.' },
            { heading: 'Hak Pengguna', body: 'Kamu berhak mengakses, memperbarui, atau menghapus data pribadi kamu dengan menghubungi kami melalui kontak yang tersedia.' },
            { heading: 'Retensi Data', body: 'Kami menyimpan data selama akun kamu aktif atau selama diperlukan untuk memenuhi kewajiban hukum.' },
        ],
    },
    contact: {
        title: 'Kontak Kami',
        updated: null,
        intro: `Punya pertanyaan atau butuh bantuan? Tim ${appName} siap membantu.`,
        sections: [],
        contact: true,
    },
};

const page = computed(() => PAGES[route.name] || PAGES.terms);
</script>

<template>
    <div class="min-h-screen bg-white text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
        <!-- Navbar -->
        <header class="sticky top-0 z-30 px-4 pt-4 lg:px-6">
            <div class="mx-auto flex h-16 max-w-4xl items-center justify-between rounded-2xl border border-neutral-200 bg-white/80 px-4 shadow-sm backdrop-blur dark:border-neutral-800 dark:bg-neutral-950/80 lg:px-5">
                <RouterLink to="/" class="inline-flex items-center gap-2 text-lg font-bold tracking-tight">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-neutral-900 text-white dark:bg-white dark:text-neutral-950">
                        <PenLine class="h-4 w-4" />
                    </span>
                    {{ appName }}
                </RouterLink>
                <RouterLink to="/" class="inline-flex items-center gap-1.5 text-sm font-medium text-neutral-600 transition-colors hover:text-neutral-900 dark:text-neutral-300 dark:hover:text-white">
                    <ArrowLeft class="h-4 w-4" />
                    Kembali ke Beranda
                </RouterLink>
            </div>
        </header>

        <main class="mx-auto max-w-4xl px-4 py-16 lg:px-6">
            <h1 class="text-3xl font-bold tracking-tight">{{ page.title }}</h1>
            <p v-if="page.updated" class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">{{ page.updated }}</p>
            <p class="mt-6 text-neutral-600 dark:text-neutral-300">{{ page.intro }}</p>

            <!-- Kontak -->
            <div v-if="page.contact" class="mt-8 grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                    <Mail class="h-5 w-5 text-neutral-500 dark:text-neutral-400" />
                    <p class="mt-3 text-sm font-semibold">Email</p>
                    <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">halo@tulissin.com</p>
                </div>
                <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                    <Building2 class="h-5 w-5 text-neutral-500 dark:text-neutral-400" />
                    <p class="mt-3 text-sm font-semibold">Perusahaan</p>
                    <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{{ COMPANY }}</p>
                </div>
                <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                    <Clock class="h-5 w-5 text-neutral-500 dark:text-neutral-400" />
                    <p class="mt-3 text-sm font-semibold">Jam Operasional</p>
                    <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Senin–Jumat, 09.00–17.00 WIB</p>
                </div>
            </div>

            <!-- Isi halaman -->
            <div v-else class="mt-10 space-y-8">
                <section v-for="s in page.sections" :key="s.heading">
                    <h2 class="text-xl font-semibold">{{ s.heading }}</h2>
                    <p class="mt-2 leading-relaxed text-neutral-600 dark:text-neutral-300">{{ s.body }}</p>
                </section>
            </div>
        </main>

        <footer class="border-t border-neutral-200 py-8 dark:border-neutral-800">
            <div class="mx-auto flex max-w-4xl flex-col items-center justify-between gap-3 px-4 text-sm text-neutral-500 dark:text-neutral-400 sm:flex-row lg:px-6">
                <span>&copy; 2026 {{ COMPANY }}. Semua hak dilindungi.</span>
                <RouterLink to="/" class="hover:text-neutral-900 dark:hover:text-white">{{ appName }}</RouterLink>
            </div>
        </footer>
    </div>
</template>
