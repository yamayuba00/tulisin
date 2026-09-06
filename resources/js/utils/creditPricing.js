// ---- Tarif kredit terpusat (reactive) ----
// Nilai default dipakai sebagai fallback sebelum tarif dari server dimuat.
// Super-admin bisa mengubah tarif lewat halaman "Pengaturan Kredit"; nilai
// aktual disimpan di tabel `settings` dan dibaca lewat GET /api/credit-pricing.

import { ref } from 'vue';
import { request } from './http';

export const DEFAULT_CREDIT_PRICING = {
    ai_generate: 5,
    agent_generate: 1,
    ai_plagiarism: 1,
    ai_turnitin: 20,
    template: 5,
    font: 4,
    image_package_size: 1,
    image_package_credits: 1,
    download_base: 4,
    download_per_10_pages: 1,
};

// Singleton reactive agar semua halaman/komponen memakai nilai yang sama.
export const creditPricing = ref({ ...DEFAULT_CREDIT_PRICING });

// Muat tarif dari server; bila gagal tetap memakai default.
export async function loadCreditPricing() {
    try {
        const res = await request('/api/credit-pricing', { method: 'GET' });
        if (res.ok && res.data?.pricing) {
            creditPricing.value = { ...DEFAULT_CREDIT_PRICING, ...res.data.pricing };
        }
    } catch {
        // biarkan default
    }
    return creditPricing.value;
}

// Biaya gambar per item (paket dibagi jumlah gambar).
export function imageCostPerItem() {
    const p = creditPricing.value;
    const size = Number(p.image_package_size) || 1;
    return (Number(p.image_package_credits) || 0) / size;
}
