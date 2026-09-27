<script setup>
import { ref, computed, nextTick, watch } from 'vue';
import { Sparkles, X, Send, Loader2, LayoutGrid, Plus, Info, Trash2, MessageSquarePlus, Menu, Link2, ExternalLink, Copy, BookMarked, Lock, Search, FilePlus2, FileText } from 'lucide-vue-next';
import { request, requestAiGenerate } from '../../../utils/http';
import { renderMarkdown } from '../../../utils/markdown';
import { creditPricing } from '../../../utils/creditPricing';

const props = defineProps({
    summary: { type: String, default: '' },
    isEmpty: { type: Boolean, default: false },
    blockCount: { type: Number, default: 0 },
    pageCount: { type: Number, default: 0 },
    projectUuid: { type: String, default: '' },
    blockTypes: { type: Array, default: () => [] },
    hasSelection: { type: Boolean, default: false },
    spendCredits: { type: Function, default: null },
    references: { type: Array, default: () => [] },
    targets: { type: Array, default: () => [] }, // Daftar bagian yang tersedia (abstrak, chapter baru)
    defaultTarget: { type: String, default: '' }, // Target awal dari backend/inisialisasi
    canvasText: { type: String, default: '' }, // Teks isi canvas (untuk saran kata kunci referensi)
});

const open = defineModel('open', { type: Boolean, default: false });
const emit = defineEmits(['close', 'apply', 'reference-saved', 'insert-citations']);

const input = ref('');
const format = ref('');
const messages = ref([]);
const listEl = ref(null);
const sending = ref(false);
const showHelp = ref(false);
const sidebarOpen = ref(false);

// Referensi terverifikasi dari Tulisin Workspace yang dikirim ke AI sebagai
// satu-satunya sumber sitasi, beserta label & tautan yang bisa diklik user.
const verifiedReferences = computed(() =>
    (props.references || [])
        .map((r) => {
            const authors = Array.isArray(r.author)
                ? r.author.map((a) => a?.family).filter(Boolean).join(', ')
                : String(r.author || '');
            const year = r.issued?.['date-parts']?.[0]?.[0] || r.year || '';
            const title = String(r.title || '').trim();
            const label = [authors && `${authors} (${year})`, title].filter(Boolean).join('. ');
            const link = String(r.URL || r.url || (r.DOI || r.doi ? `https://doi.org/${r.DOI || r.doi}` : ''));
            return { label, link: link.trim(), doi: String(r.DOI || r.doi || '').trim() };
        })
        .filter((r) => r.label),
);

// Referensi yang benar-benar dikutip AI pada balasan (untuk panel Sumber).
function citedInMessage(text) {
    const t = String(text || '').toLowerCase();
    return verifiedReferences.value.filter((r) => {
        if (!r.link) return false;
        return t.includes(r.link.toLowerCase()) || (r.doi && t.includes(r.doi.toLowerCase()));
    });
}

// Sesi chat ala ChatGPT.
const sessions = ref([]);
const activeSessionId = ref(null);
const sessionsLoading = ref(false);

const formatOptions = [
    { value: 'skripsi', label: 'Skripsi' },
    { value: 'tesis', label: 'Tesis' },
    { value: 'disertasi', label: 'Disertasi' },
    { value: 'makalah', label: 'Makalah' },
    { value: 'jurnal', label: 'Jurnal' },
    { value: 'laporan', label: 'Laporan' },
    { value: 'proposal', label: 'Proposal' },
    { value: 'esai', label: 'Esai' },
];

const formatLabel = computed(() =>
    (formatOptions.find((o) => o.value === format.value) || {}).label || '',
);

// Mode penyisipan sesi ini: 'pick' (pilih per bagian), 'after', atau 'replace'.
// Default 'replace' sesuai permintaan: tidak menumpuk konten baru ke canvas.
const insertMode = ref('replace');

// Tujuan penulisan: bagian mana yang sedang dituju user (abstrak, bab, atau
// bagian baru). Dikirim ke AI sebagai parameter thinking agar hasilnya tahu
// mau ditaruh di mana, dan dipakai menandai baris di panel struktur.
const target = ref('');

const targetLabel = computed(() =>
    (props.targets.find((t) => t.id === target.value) || {}).label || '',
);

// Ikuti target bawaan dari canvas (posisi blok terpilih) selama user belum
// memilih sendiri; reset bila target tidak ada lagi di daftar.
watch(() => props.defaultTarget, (v) => {
    if (v && !target.value) target.value = v;
}, { immediate: true });

watch(() => props.targets, (list) => {
    if (target.value && !(list || []).some((t) => t.id === target.value)) {
        target.value = '';
    }
});

const starterPrompts = computed(() => {
    // Saran awal mengikuti tujuan: bila user sudah memilih bab/abstrak,
    // tawarkan prompt yang langsung mengerjakan bagian itu.
    const t = (props.targets || []).find((x) => x.id === target.value);
    if (t && t.kind !== 'new') {
        return [
            `Tulis isi untuk ${t.label}`,
            'Lanjutkan paragraf berikutnya',
            'Ringkas isi bagian ini',
        ];
    }
    if (props.isEmpty) {
        return [
            'Buatkan kerangka dokumen lengkap',
            'Tulis paragraf pembuka Bab 1 Pendahuluan',
            'Buat abstrak 200 kata',
        ];
    }
    return [
        'Lanjutkan paragraf berikutnya',
        'Ringkas isi halaman ini',
        'Beri saran judul bab berikutnya',
    ];
});

const activeSession = computed(() =>
    sessions.value.find((s) => s.id === activeSessionId.value) || null,
);

// Format terkunci hanya bila sesi aktif sudah punya format.
// Sesi lama (format kosong) boleh memilih SEKALI, lalu terkunci permanen.
const formatLocked = computed(() => !!activeSessionId.value && !!activeSession.value?.format);

const costPerMessage = computed(() => Number(creditPricing.value.agent_generate) || 1);
const costPerReference = computed(() => Number(creditPricing.value.reference_save) || 5);

// Apakah balasan berisi fenced block ```canvas ... ``` yang siap dimasukkan.
function hasCanvasFence(text) {
    return /```canvas\s*\n[\s\S]*?```/i.test(String(text || ''));
}

function scrollBottom() {
    nextTick(() => {
        const el = listEl.value;
        if (!el) return;
        el.scrollTop = el.scrollHeight;
        requestAnimationFrame(() => {
            if (listEl.value) listEl.value.scrollTop = listEl.value.scrollHeight;
        });
    });
}

function close() {
    emit('close');
    open.value = false;
}

// ---- Sesi chat (persist ke database) ----
async function loadSessions() {
    if (!props.projectUuid) return;
    sessionsLoading.value = true;
    try {
        const res = await request(`/api/projects/${encodeURIComponent(props.projectUuid)}/ai-chats`, { method: 'GET' });
        sessions.value = res.ok ? (res.data?.sessions || []) : [];
    } catch {
        sessions.value = [];
    } finally {
        sessionsLoading.value = false;
    }
}

async function createSession() {
    if (!props.projectUuid) return null;
    if (!format.value) return null;
    try {
        const res = await request(`/api/projects/${encodeURIComponent(props.projectUuid)}/ai-chats`, {
            method: 'POST',
            body: JSON.stringify({ title: 'Chat baru', format: format.value, insert_mode: insertMode.value }),
        });
        if (res.ok && res.data) {
            sessions.value.unshift(res.data);
            return res.data.id;
        }
    } catch {
        // abaikan, fallback ke session lokal
    }
    return null;
}

async function selectSession(id) {
    if (!props.projectUuid || id == null) return;
    activeSessionId.value = id;
    sidebarOpen.value = false;
    messages.value = [];
    sending.value = false;
    try {
        const res = await request(`/api/projects/${encodeURIComponent(props.projectUuid)}/ai-chats/${id}`, { method: 'GET' });
        if (res.ok && Array.isArray(res.data?.messages)) {
            messages.value = res.data.messages.map((m) => ({ id: m.id, role: m.role, text: m.content }));
        }
        // Ikuti format sesi yang dibuka. Sesi lama tanpa format: biarkan user
        // memilih sekali (tidak dikunci sampai user mengunci via tombol).
        const s = res.ok ? res.data?.session : null;
        const idx = sessions.value.findIndex((x) => x.id === id);
        if (idx >= 0 && s) sessions.value[idx] = { ...sessions.value[idx], ...s };
        format.value = s?.format || '';
        insertMode.value = s?.insert_mode || 'replace';
    } catch {
        messages.value = [];
    }
    scrollBottom();
}

// Kunci format pada sesi lama yang belum punya format (hanya bisa sekali).
async function lockSessionFormat() {
    if (!props.projectUuid || !activeSessionId.value || !format.value || formatLocked.value) return;
    try {
        const res = await request(`/api/projects/${encodeURIComponent(props.projectUuid)}/ai-chats/${activeSessionId.value}`, {
            method: 'PATCH',
            body: JSON.stringify({ format: format.value, insert_mode: insertMode.value }),
        });
        if (res.ok && res.data) {
            const idx = sessions.value.findIndex((x) => x.id === activeSessionId.value);
            if (idx >= 0) sessions.value[idx] = { ...sessions.value[idx], ...res.data };
        }
    } catch {
        // abaikan; kunci tetap berlaku lokal via formatLocked saat reload
    }
}

async function newChat() {
    // Chat baru wajib memilih format dulu (mekanisme terkunci per sesi).
    if (!format.value) {
        messages.value = [];
        activeSessionId.value = null;
        input.value = '';
        return;
    }
    const id = await createSession();
    activeSessionId.value = id;
    messages.value = [];
    input.value = '';
    scrollBottom();
    if (id) await selectSession(id);
}

async function deleteSession(id) {
    if (!props.projectUuid || id == null) return;
    try {
        await request(`/api/projects/${encodeURIComponent(props.projectUuid)}/ai-chats/${id}`, { method: 'DELETE' });
    } catch {
        // abaikan
    }
    sessions.value = sessions.value.filter((s) => s.id !== id);
    if (activeSessionId.value === id) {
        activeSessionId.value = null;
        messages.value = [];
    }
}

async function persistMessage(sessionId, role, content) {
    if (!props.projectUuid || !sessionId) return;
    try {
        await request(`/api/projects/${encodeURIComponent(props.projectUuid)}/ai-chats/${sessionId}/messages`, {
            method: 'POST',
            body: JSON.stringify({ role, content }),
        });
    } catch {
        // non-blocking
    }
}

async function send(text) {
    const t = (text ?? input.value).trim();
    if (!t || sending.value) return;

    // Wajib ada format. Sesi lama tanpa format: pakai pilihan saat ini,
    // lalu kunci permanen agar mekanismenya konsisten.
    if (!format.value) return;
    if (activeSessionId.value && !formatLocked.value) {
        await lockSessionFormat();
    }

    // Pastikan ada sesi aktif (buat baru bila belum ada).
    if (!activeSessionId.value) {
        const id = await createSession();
        if (!id) {
            // Tanpa backend tetap bisa chat secara lokal.
            activeSessionId.value = null;
        } else {
            activeSessionId.value = id;
        }
    }

    // Potong koin sesuai tarif admin (agent_generate) sebelum memproses.
    if (props.spendCredits && !(await props.spendCredits('agent_generate'))) {
        messages.value.push({ role: 'assistant', text: 'Saldo koin kamu tidak cukup untuk prompt berikutnya. Silakan top up terlebih dahulu.' });
        scrollBottom();
        return;
    }

    const history = messages.value.map((m) => ({ role: m.role, content: m.text }));
    const userMsg = { role: 'user', text: t };
    messages.value.push(userMsg);
    if (!text) input.value = '';
    sending.value = true;
    scrollBottom();

    if (activeSessionId.value) persistMessage(activeSessionId.value, 'user', t);

    // Tempatkan pesan asisten kosong yang akan diisi bertahap (streaming).
    const assistantIndex = messages.value.length;
    messages.value.push({ role: 'assistant', text: '' });
    scrollBottom();

    try {
        const res = await requestAiGenerate({
            agent: 'canvas',
            message: t,
            context: props.summary,
            uuid: props.projectUuid,
            format: format.value,
            blockTypes: props.blockTypes.map((b) => b.id),
            references: verifiedReferences.value.map((r) => ({ label: r.label, link: r.link })),
            history,
            target: targetLabel.value,
        });
        messages.value[assistantIndex].text = res.ok
            ? (res.data?.reply || '')
            : (res.data?.error || 'Gagal menghubungi AI.');
        scrollBottom();
        if (res.ok && activeSessionId.value) {
            persistMessage(activeSessionId.value, 'assistant', messages.value[assistantIndex].text);
        }
    } catch {
        messages.value[assistantIndex].text = 'Gagal menghubungi AI. Coba lagi.';
    } finally {
        sending.value = false;
        scrollBottom();
        // Perbarui pratinjau judul di sidebar.
        if (activeSessionId.value) refreshSessionTitle();
    }
}

function refreshSessionTitle() {
    const firstUser = messages.value.find((m) => m.role === 'user');
    const s = sessions.value.find((x) => x.id === activeSessionId.value);
    if (s && firstUser) {
        const title = firstUser.text.replace(/\s+/g, ' ').trim().slice(0, 60);
        if (s.title === 'Chat baru') s.title = title || s.title;
    }
}

function onKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
    }
}

// Salin sitasi (Penulis, tahun) ke clipboard agar bisa langsung ditempel ke dokumen.
async function copyCitation(r) {
    const authors = String(r.label || '').split(' (')[0];
    const year = (String(r.label || '').match(/\((\d{4})\)/) || [])[1] || '';
    const text = year ? `(${authors}, ${year})` : r.label;
    try {
        await navigator.clipboard.writeText(text);
    } catch {
        // Clipboard tidak tersedia (izin ditolak); abaikan.
    }
}

// ---- Pencarian referensi (OpenAlex) ----
// Dua mode: manual (user mengetik kata kunci sendiri) dan otomatis
// ("Cari dari isi dokumen": kata kunci diekstrak dari seluruh teks canvas
// — judul, bab, abstrak, paragraf — lalu pencarian berjalan otomatis).
const refPanelOpen = ref(false);
const refMode = ref('manual'); // 'manual' | 'auto'
const refQuery = ref('');
const refYearFrom = ref('2019');
const refYearTo = ref(String(new Date().getFullYear()));
const refResults = ref([]);
const refSearching = ref(false);
const refError = ref('');
const refSavingKey = ref('');
const refSavedKeys = ref([]);
// Pagination RAG: 10 per halaman, maksimal 50 data (5 halaman).
const refPage = ref(1);
const refTotalCount = ref(0);
const refHasMore = ref(false);
// Petakan hasil OpenAlex yang sudah disimpan ke data Workspace-nya.
const savedWorkMap = ref({});

// Stopword Indonesia + Inggris untuk ekstraksi kata kunci. Kata generik
// template (penelitian, latar, dll) ikut dibuang agar kata kunci yang tersisa
// adalah topik dokumen (mis. obat, ispa, anak, iot, robot, restoran).
const REF_STOPWORDS = new Set([
    'yang', 'dan', 'dengan', 'untuk', 'dalam', 'pada', 'dari', 'ini', 'itu', 'adalah',
    'sebagai', 'serta', 'oleh', 'karena', 'atau', 'tidak', 'akan', 'telah', 'sudah',
    'dapat', 'bisa', 'agar', 'juga', 'sangat', 'lebih', 'kurang', 'yaitu', 'yakni',
    'antara', 'setelah', 'sebelum', 'selama', 'tentang', 'terhadap', 'berdasarkan',
    'melalui', 'tanpa', 'maupun', 'namun', 'tetapi', 'sehingga', 'apabila', 'jika',
    'kamu', 'kami', 'kita', 'mereka', 'dia', 'saya', 'anda', 'nya', 'para', 'satu',
    'dua', 'tiga', 'hal', 'bab', 'penelitian', 'penulisan', 'dokumen', 'bagian',
    'judul', 'abstrak', 'tujuan', 'metode', 'hasil', 'kesimpulan', 'saran',
    'latar', 'belakang', 'rumusan', 'masalah', 'kajian', 'pustaka', 'teori',
    'data', 'tersebut', 'kata', 'kunci', 'halaman', 'paragraf', 'pendahuluan',
    'pembahasan', 'daftar', 'cover', 'blok', 'isi', 'tulis', 'tulisan', 'jelaskan',
    'uraikan', 'sebutkan', 'paparkan', 'analisis', 'rangkum', 'berikan', 'buat',
    'the', 'and', 'with', 'for', 'from', 'that', 'this',
    'are', 'was', 'were', 'has', 'have', 'had', 'which', 'their', 'they', 'them',
    'then', 'than', 'also', 'into', 'such', 'using', 'used', 'use', 'between',
]);

// Ekstrak hingga 6 kata kunci bermakna dari teks canvas (kata >=3 huruf
// agar akronim seperti iot/ispa ikut, bukan stopword, diurutkan dari yang
// paling sering muncul). Fallback: bila semua kata tersaring stopword tapi
// dokumen tidak kosong, pakai kata terpanjang sebagai topik agar pencarian
// otomatis tetap jalan (tidak mentok "dokumen kosong").
function extractRefKeywords(text, max = 6) {
    const clean = String(text || '').toLowerCase();
    const freq = new Map();
    for (const raw of clean.match(/[a-z\u00e0-\u00ff]{3,}/g) || []) {
        if (REF_STOPWORDS.has(raw)) continue;
        freq.set(raw, (freq.get(raw) || 0) + 1);
    }
    let out = [...freq.entries()]
        .sort((a, b) => b[1] - a[1])
        .slice(0, max)
        .map(([w]) => w);
    if (!out.length && clean.replace(/\s+/g, ' ').trim().length >= 10) {
        const pool = [...new Set(clean.match(/[a-z\u00e0-\u00ff]{4,}/g) || [])]
            .sort((a, b) => b.length - a.length)
            .slice(0, Math.min(4, max));
        out = pool;
    }
    return out;
}

const suggestedRefKeywords = computed(() => extractRefKeywords(props.canvasText));
const canvasHasText = computed(() => String(props.canvasText || '').replace(/\s+/g, ' ').trim().length >= 10);

async function searchReferencesAuto() {
    const keywords = suggestedRefKeywords.value;
    if (!keywords.length) {
        refMode.value = 'manual';
        refError.value = canvasHasText.value
            ? 'Topik dokumen belum terbaca, tulis manual kata kunci di bawah.'
            : 'Isi dokumen masih kosong, tulis dulu judul/bab/paragraf agar kata kunci bisa diekstrak.';
        return;
    }
    refMode.value = 'auto';
    refQuery.value = keywords.join(' ');
    await searchReferences();
}

// Panel referensi dibuka: langsung sesuaikan otomatis dengan isi dokumen
// bila ada teks (tidak menunggu user klik tab), agar tidak mentok manual.
watch(refPanelOpen, (v) => {
    if (v && !refResults.value.length && !refSearching.value) {
        if (suggestedRefKeywords.value.length) searchReferencesAuto();
        else refMode.value = 'manual';
    }
});

async function searchReferences(append = false) {
    const q = refQuery.value.trim();
    if (q.length < 3) {
        refError.value = 'Tulis minimal 3 karakter kata kunci.';
        return;
    }
    // RAG bertahap: 10 hasil per halaman, maksimal 50 data (5 halaman).
    // Halaman berikut dimuat via "Muat 10 lagi" dan hasilnya ditambahkan
    // (append) agar user bisa generate ulang bila belum ada yang cocok.
    const nextPage = append ? refPage.value + 1 : 1;
    if (nextPage > 5) {
        refError.value = 'Sudah mencapai batas 50 data. Persempit kata kunci untuk hasil lain.';
        return;
    }
    refSearching.value = true;
    refError.value = '';
    if (!append) refSelectedKeys.value = [];
    try {
        const params = new URLSearchParams({ q, per_page: '10', page: String(nextPage) });
        if (refYearFrom.value) params.set('year_from', refYearFrom.value);
        if (refYearTo.value) params.set('year_to', refYearTo.value);
        const res = await request(`/api/workspace/references/search?${params.toString()}`, { method: 'GET' });
        if (res.ok) {
            const list = Array.isArray(res.data?.results) ? res.data.results : [];
            refPage.value = Number(res.data?.page) || nextPage;
            refTotalCount.value = Number(res.data?.total_count) || 0;
            refHasMore.value = !!res.data?.has_more && (refPage.value * 10 + (append ? refResults.value.length : 0)) < 50;
            refResults.value = append ? [...refResults.value, ...list] : list;
            if (!refResults.value.length) refError.value = 'Belum ada hasil. Ubah kata kunci lalu cari lagi.';
        } else {
            if (!append) refResults.value = [];
            refError.value = res.data?.error || 'Pencarian gagal.';
        }
    } catch (e) {
        if (!append) refResults.value = [];
        refError.value = e.message || 'Pencarian gagal.';
    } finally {
        refSearching.value = false;
    }
}

async function loadMoreReferences() {
    await searchReferences(true);
}

// ---- Pilih massal + simpan massal (RAG): user mencentang beberapa hasil,
// membayar 5 koin per referensi sekaligus, lalu bisa ditempel bareng ke
// canvas dengan posisi sitasi yang diatur builder (blok terpilih/kursor).
const refSelectedKeys = ref([]);
const refBulkSaving = ref(false);

function toggleSelectReference(work) {
    const i = refSelectedKeys.value.indexOf(work.key);
    if (i >= 0) refSelectedKeys.value.splice(i, 1);
    else refSelectedKeys.value.push(work.key);
}

function toggleSelectAllVisible() {
    const keys = refResults.value.map((w) => w.key);
    const all = keys.length && keys.every((k) => refSelectedKeys.value.includes(k));
    refSelectedKeys.value = all ? [] : [...new Set([...refSelectedKeys.value, ...keys])];
}

const selectedWorks = computed(() =>
    refResults.value.filter((w) => refSelectedKeys.value.includes(w.key)),
);

const unsavedSelectedWorks = computed(() =>
    selectedWorks.value.filter((w) => !refSavedKeys.value.includes(w.key)),
);

async function saveSelectedReferences() {
    const targets = unsavedSelectedWorks.value;
    if (!targets.length || refBulkSaving.value) return;
    // Satu potongan koin untuk N referensi (5 koin x jumlah).
    if (props.spendCredits && !(await props.spendCredits('reference_save', { quantity: targets.length }))) return;
    refBulkSaving.value = true;
    try {
        for (const work of targets) {
            try {
                const res = await request('/api/workspace/references/save', {
                    method: 'POST',
                    body: JSON.stringify({ work }),
                });
                if (res.ok) {
                    if (!refSavedKeys.value.includes(work.key)) refSavedKeys.value.push(work.key);
                    savedWorkMap.value[work.key] = res.data || null;
                    emit('reference-saved', res.data);
                }
            } catch {
                // Lanjut ke item berikutnya; satu gagal tidak menggagalkan semua.
            }
        }
    } finally {
        refBulkSaving.value = false;
    }
}

// Tempel sitasi bareng ke canvas: builder menaruhnya di posisi blok
// terpilih/kursor dan mendaftarkannya ke Daftar Pustaka otomatis.
function insertSelectedCitations() {
    const works = selectedWorks.value;
    if (!works.length) return;
    emit('insert-citations', {
        refs: works.map((w) => {
            const saved = savedWorkMap.value[w.key];
            const authors = (w.authors || []).map((a) => a.name).filter(Boolean);
            const firstAuthor = authors.length
                ? authors[0].split(' ').slice(-1)[0]
                : 'Anonim';
            const etal = authors.length > 1 ? ' et al.' : '';
            return {
                key: w.key,
                title: w.title,
                year: w.year,
                // Label sitasi ringkas (Penulis, Tahun) untuk dipetakan builder
                // ke referensi Workspace yang sudah disimpan.
                label: `${firstAuthor}${etal}${w.year ? `, ${w.year}` : ''}`,
                doi: w.doi || '',
                link: w.landing_url || '',
                workspaceId: saved?.id || null,
            };
        }),
    });
}

async function saveReference(work) {
    if (refSavingKey.value) return;
    // Setiap referensi yang disimpan memotong koin sesuai tarif admin
    // (reference_save), default 5 koin.
    if (props.spendCredits && !(await props.spendCredits('reference_save'))) return;
    refSavingKey.value = work.key;
    try {
        const res = await request('/api/workspace/references/save', {
            method: 'POST',
            body: JSON.stringify({ work }),
        });
        if (res.ok) {
            if (!refSavedKeys.value.includes(work.key)) refSavedKeys.value.push(work.key);
            savedWorkMap.value[work.key] = res.data || null;
            emit('reference-saved', res.data);
        } else {
            refError.value = res.data?.error || 'Gagal menyimpan referensi.';
        }
    } catch (e) {
        refError.value = e.message || 'Gagal menyimpan referensi.';
    } finally {
        refSavingKey.value = '';
    }
}

function referenceAuthorsText(work) {
    const names = (work.authors || []).map((a) => a.name).filter(Boolean);
    if (!names.length) return 'Tanpa penulis';
    return names.length > 3 ? `${names.slice(0, 3).join(', ')} et al.` : names.join(', ');
}

watch(open, (v) => {
    if (v && !sessions.value.length) loadSessions();
});
</script>

<template>
    <Transition name="modal-fade">
        <div
            v-if="open"
            class="fixed inset-0 z-[80] flex items-center justify-center p-4 print:hidden"
            role="dialog"
            aria-modal="true"
        >
            <div class="absolute inset-0 bg-black/50" @click="close"></div>

            <div class="relative z-10 flex h-[90vh] max-h-[920px] w-full max-w-5xl overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-2xl dark:border-neutral-800 dark:bg-neutral-950">
                <!-- Sidebar sesi chat -->
                <aside
                    class="absolute inset-y-0 left-0 z-20 flex w-64 shrink-0 flex-col border-r border-neutral-200 bg-neutral-50 transition-transform duration-200 dark:border-neutral-800 dark:bg-neutral-900/60 md:static md:translate-x-0"
                    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
                >
                    <div class="flex items-center justify-between gap-2 border-b border-neutral-200 px-3 py-2.5 dark:border-neutral-800">
                        <span class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Chat</span>
                        <button
                            type="button"
                            class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-neutral-500 transition-colors hover:bg-neutral-200 hover:text-neutral-900 dark:hover:bg-neutral-800 dark:hover:text-white md:hidden"
                            aria-label="Tutup sidebar"
                            @click="sidebarOpen = false"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="p-2">
                        <button
                            type="button"
                            class="flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm font-medium text-neutral-800 transition-colors hover:border-neutral-400 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:border-neutral-500"
                            :disabled="!format"
                            :title="format ? 'Mulai chat baru' : 'Pilih format dokumen terlebih dahulu'"
                            @click="newChat"
                        >
                            <MessageSquarePlus class="h-4 w-4" />
                            Chat baru
                        </button>
                        <p v-if="!format" class="mt-1.5 text-center text-[11px] text-amber-600 dark:text-amber-400">
                            Pilih format dulu sebelum chat baru.
                        </p>
                    </div>

                    <div class="flex-1 space-y-1 overflow-y-auto px-2 pb-2">
                        <div v-if="sessionsLoading" class="space-y-1 py-1">
                            <div v-for="i in 4" :key="i" class="rounded-lg border border-neutral-200 p-2.5 dark:border-neutral-800">
                                <Skeleton class="h-3.5 w-2/3" />
                                <Skeleton class="mt-2 h-3 w-1/2" />
                            </div>
                        </div>
                        <p v-else-if="!sessions.length" class="px-2 py-3 text-center text-xs text-neutral-400 dark:text-neutral-500">
                            Belum ada chat. Mulai chat baru untuk bertanya.
                        </p>
                        <button
                            v-for="s in sessions"
                            :key="s.id"
                            type="button"
                            class="group flex w-full cursor-pointer items-start gap-2 rounded-lg border px-2.5 py-2 text-left transition-colors"
                            :class="s.id === activeSessionId
                                ? 'border-neutral-300 bg-white dark:border-neutral-700 dark:bg-neutral-950'
                                : 'border-transparent hover:bg-neutral-100 dark:hover:bg-neutral-900'"
                            @click="selectSession(s.id)"
                        >
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[13px] font-medium text-neutral-800 dark:text-neutral-200">{{ s.title }}</span>
                                <span v-if="s.preview" class="block truncate text-[11px] text-neutral-400 dark:text-neutral-500">{{ s.preview }}</span>
                            </span>
                            <span
                                class="inline-flex h-6 w-6 shrink-0 cursor-pointer items-center justify-center rounded text-neutral-400 opacity-0 transition-opacity hover:text-red-600 group-hover:opacity-100 dark:hover:text-red-400"
                                @click.stop="deleteSession(s.id)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </span>
                        </button>
                    </div>
                </aside>

                <!-- Area chat utama -->
                <div class="flex min-w-0 flex-1 flex-col">
                    <!-- Header -->
                    <div class="flex items-start justify-between border-b border-neutral-200 px-4 py-3 dark:border-neutral-800">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-neutral-500 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:hover:bg-neutral-800 dark:hover:text-white md:hidden"
                                aria-label="Buka daftar chat"
                                @click="sidebarOpen = true"
                            >
                                <Menu class="h-5 w-5" />
                            </button>
                            <Sparkles class="h-5 w-5 text-neutral-500 dark:text-neutral-400" />
                            <div>
                                <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">{{ activeSession?.title || 'Agent AI Canvas' }}</h3>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400">Membaca seluruh canvas &amp; menjawab pertanyaan kamu.</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-neutral-500 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:hover:bg-neutral-800 dark:hover:text-white"
                                aria-label="Cara kerja"
                                @click="showHelp = !showHelp"
                            >
                                <Info class="h-5 w-5" />
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-neutral-500 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:hover:bg-neutral-800 dark:hover:text-white"
                                aria-label="Tutup"
                                @click="close"
                            >
                                <X class="h-5 w-5" />
                            </button>
                        </div>
                    </div>

                    <!-- Konteks canvas -->
                    <div class="flex items-center gap-2 border-b border-neutral-200 bg-neutral-50 px-4 py-2 text-xs text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900/40 dark:text-neutral-400">
                        <LayoutGrid class="h-3.5 w-3.5 shrink-0" />
                        <span>
                            {{ isEmpty
                                ? 'Canvas kosong — mulai dari trigger di bawah.'
                                : `${blockCount} blok · ${pageCount} halaman` }}
                        </span>
                        <span class="ml-auto flex items-center gap-1.5">
                            <BookMarked class="h-3.5 w-3.5 shrink-0" />
                            {{ verifiedReferences.length }} sumber
                        </span>
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-neutral-200 px-2 py-0.5 text-[11px] font-medium text-neutral-600 transition-colors hover:border-neutral-400 hover:text-neutral-900 dark:border-neutral-700 dark:text-neutral-300 dark:hover:border-neutral-500 dark:hover:text-white"
                            @click="refPanelOpen = !refPanelOpen"
                        >
                            <Search class="h-3 w-3" />
                            Cari referensi
                        </button>
                        <span class="text-[11px] text-neutral-400 dark:text-neutral-500">{{ costPerMessage }} koin / pesan</span>
                    </div>

                    <!-- Panel pencarian referensi ilmiah (OpenAlex) -->
                    <div v-if="refPanelOpen" class="border-b border-neutral-200 bg-white p-3 dark:border-neutral-800 dark:bg-neutral-950">
                        <!-- Mode pencarian: manual vs otomatis dari isi dokumen -->
                        <div class="mb-2 flex items-center gap-1 rounded-lg bg-neutral-100 p-0.5 text-xs font-medium dark:bg-neutral-900">
                            <button
                                type="button"
                                class="flex-1 cursor-pointer rounded-md px-2 py-1 transition-colors"
                                :class="refMode === 'manual' ? 'bg-white text-neutral-900 shadow-sm dark:bg-neutral-800 dark:text-white' : 'text-neutral-500 dark:text-neutral-400'"
                                @click="refMode = 'manual'"
                            >
                                Cari manual
                            </button>
                            <button
                                type="button"
                                class="flex-1 cursor-pointer rounded-md px-2 py-1 transition-colors"
                                :class="refMode === 'auto' ? 'bg-white text-neutral-900 shadow-sm dark:bg-neutral-800 dark:text-white' : 'text-neutral-500 dark:text-neutral-400'"
                                @click="searchReferencesAuto"
                            >
                                Cari dari isi dokumen
                            </button>
                        </div>

                        <p v-if="refMode === 'auto' && suggestedRefKeywords.length" class="mb-2 text-[11px] text-neutral-500 dark:text-neutral-400">
                            Kata kunci dari dokumen:
                            <span class="font-semibold text-neutral-700 dark:text-neutral-200">{{ suggestedRefKeywords.join(', ') }}</span>
                        </p>
                        <div v-if="refMode === 'manual'" class="flex flex-wrap items-center gap-2">
                            <input
                                v-model="refQuery"
                                type="text"
                                placeholder="Kata kunci, mis. ketepatan penggunaan obat ISPA anak"
                                class="min-w-[180px] flex-1 rounded-lg border border-neutral-200 bg-transparent px-2.5 py-1.5 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                                @keydown.enter="searchReferences(false)"
                            />
                            <input
                                v-model="refYearFrom"
                                type="number"
                                placeholder="Dari"
                                class="w-20 rounded-lg border border-neutral-200 bg-transparent px-2 py-1.5 text-sm outline-none focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950"
                            />
                            <input
                                v-model="refYearTo"
                                type="number"
                                placeholder="Sampai"
                                class="w-20 rounded-lg border border-neutral-200 bg-transparent px-2 py-1.5 text-sm outline-none focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950"
                            />
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-neutral-900 px-3 py-1.5 text-sm font-medium text-neutral-900 transition-colors hover:bg-neutral-900 hover:text-white disabled:opacity-50 dark:border-white dark:text-white dark:hover:bg-white dark:hover:text-neutral-950"
                                :disabled="refSearching"
                                @click="searchReferences(false)"
                            >
                                <Search class="h-3.5 w-3.5" />
                                {{ refSearching ? 'Mencari…' : 'Cari' }}
                            </button>
                        </div>

                        <p v-if="refError" class="mt-2 text-xs text-red-600 dark:text-red-400">{{ refError }}</p>

                        <!-- Hasil otomatis: tampilkan banyak kandidat yang terisi dari isi dokumen -->
                        <div v-if="refMode === 'auto' && !refResults.length && !refSearching && !refError" class="mt-2 space-y-1.5">
                            <p class="text-[11px] text-neutral-400 dark:text-neutral-500">
                                {{ suggestedRefKeywords.length
                                    ? 'Klik tombol di bawah untuk mencari banyak referensi yang relevan dengan isi dokumenmu sekaligus.'
                                    : 'Isi dokumen masih kosong. Tulis dulu judul, bab, abstrak, atau paragraf agar kata kunci bisa diekstrak.' }}
                            </p>
                            <button
                                v-if="suggestedRefKeywords.length"
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-neutral-900 px-3 py-1.5 text-sm font-medium text-neutral-900 transition-colors hover:bg-neutral-900 hover:text-white disabled:opacity-50 dark:border-white dark:text-white dark:hover:bg-white dark:hover:text-neutral-950"
                                :disabled="refSearching"
                                @click="searchReferencesAuto"
                            >
                                <Search class="h-3.5 w-3.5" />
                                {{ refSearching ? 'Mencari…' : 'Cari banyak referensi dari dokumen' }}
                            </button>
                        </div>

                        <p v-if="refMode === 'manual' && !refSearching && !refResults.length && !refError" class="mt-2 text-[11px] text-neutral-400 dark:text-neutral-500">
                            Hasil berasal dari OpenAlex (jurnal, DOI, dan tautan asli). Simpan yang relevan agar otomatis menjadi referensi terverifikasi untuk AI.
                        </p>

                        <div v-if="refResults.length" class="mt-2 max-h-64 space-y-2 overflow-y-auto pr-1">
                            <!-- Info pagination RAG: 10 per halaman, maksimal 50 data -->
                            <div class="flex flex-wrap items-center gap-2 text-[11px] text-neutral-500 dark:text-neutral-400">
                                <span>Menampilkan {{ refResults.length }}{{ refTotalCount ? ` dari ${Math.min(refTotalCount, 50)} hasil` : '' }} (10 per halaman, maks 50)</span>
                                <button
                                    v-if="refResults.length"
                                    type="button"
                                    class="cursor-pointer font-medium underline underline-offset-2 hover:text-neutral-900 dark:hover:text-white"
                                    @click="toggleSelectAllVisible"
                                >
                                    {{ refResults.every((w) => refSelectedKeys.includes(w.key)) ? 'Batal pilih semua' : 'Pilih semua' }}
                                </button>
                                <span v-if="refSelectedKeys.length" class="font-semibold text-neutral-700 dark:text-neutral-200">
                                    {{ refSelectedKeys.length }} dipilih
                                </span>
                            </div>
                            <!-- Bar massal: simpan sekaligus + tempel bareng ke canvas -->
                            <div v-if="refSelectedKeys.length" class="flex flex-wrap items-center gap-1.5 rounded-lg border border-neutral-900/20 bg-neutral-50 p-2 dark:border-white/20 dark:bg-neutral-900/60">
                                <button
                                    type="button"
                                    class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-neutral-900 px-2 py-1 text-[11px] font-medium text-neutral-900 transition-colors hover:bg-neutral-900 hover:text-white disabled:cursor-not-allowed disabled:opacity-60 dark:border-white dark:text-white dark:hover:bg-white dark:hover:text-neutral-950"
                                    :disabled="refBulkSaving || !unsavedSelectedWorks.length"
                                    @click="saveSelectedReferences"
                                >
                                    <FilePlus2 class="h-3 w-3" />
                                    {{ refBulkSaving ? 'Menyimpan…' : (unsavedSelectedWorks.length ? `Simpan ${unsavedSelectedWorks.length} · ${unsavedSelectedWorks.length * costPerReference} koin` : 'Semua sudah tersimpan') }}
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-neutral-200 px-2 py-1 text-[11px] font-medium text-neutral-600 transition-colors hover:border-neutral-400 dark:border-neutral-700 dark:text-neutral-300"
                                    @click="insertSelectedCitations"
                                >
                                    <Copy class="h-3 w-3" />
                                    Tempel {{ selectedWorks.length }} sitasi ke canvas
                                </button>
                            </div>
                            <div
                                v-for="w in refResults"
                                :key="w.key"
                                class="rounded-lg border border-neutral-200 p-2.5 dark:border-neutral-800"
                                :class="refSelectedKeys.includes(w.key) ? 'border-neutral-900 dark:border-white' : ''"
                            >
                                <label class="flex cursor-pointer items-start gap-2">
                                    <input
                                        type="checkbox"
                                        class="mt-0.5 h-3.5 w-3.5 shrink-0 accent-neutral-900 dark:accent-white"
                                        :checked="refSelectedKeys.includes(w.key)"
                                        @change="toggleSelectReference(w)"
                                    />
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-xs font-semibold text-neutral-800 dark:text-neutral-100">{{ w.title }}</span>
                                        <span class="mt-0.5 block text-[11px] text-neutral-500 dark:text-neutral-400">
                                            {{ referenceAuthorsText(w) }} · {{ w.year || '—' }}
                                            <template v-if="w.journal"> · {{ w.journal }}</template>
                                        </span>
                                    </span>
                                </label>
                                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                    <a
                                        v-if="w.landing_url"
                                        :href="w.landing_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1 rounded-md border border-neutral-200 px-2 py-0.5 text-[11px] text-neutral-600 transition-colors hover:border-neutral-400 dark:border-neutral-700 dark:text-neutral-300"
                                    >
                                        <ExternalLink class="h-3 w-3" />
                                        Sumber
                                    </a>
                                    <a
                                        v-if="w.pdf_url"
                                        :href="w.pdf_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1 rounded-md border border-neutral-200 px-2 py-0.5 text-[11px] text-neutral-600 transition-colors hover:border-neutral-400 dark:border-neutral-700 dark:text-neutral-300"
                                    >
                                        <FileText class="h-3 w-3" />
                                        PDF
                                    </a>
                                    <span v-if="w.is_oa" class="rounded-md bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">Open Access</span>
                                    <span v-if="w.cited_by_count" class="text-[10px] text-neutral-400 dark:text-neutral-500">Disitasi {{ w.cited_by_count }}×</span>
                                    <button
                                        type="button"
                                        class="ml-auto inline-flex cursor-pointer items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-60"
                                        :class="refSavedKeys.includes(w.key)
                                            ? 'border-emerald-300 text-emerald-700 dark:border-emerald-800 dark:text-emerald-300'
                                            : 'border-neutral-900 text-neutral-900 hover:bg-neutral-900 hover:text-white dark:border-white dark:text-white dark:hover:bg-white dark:hover:text-neutral-950'"
                                        :disabled="refSavingKey === w.key"
                                        @click="saveReference(w)"
                                    >
                                        <FilePlus2 class="h-3 w-3" />
                                        {{ refSavedKeys.includes(w.key) ? 'Tersimpan' : (refSavingKey === w.key ? 'Menyimpan…' : `Simpan · ${costPerReference} koin`) }}
                                    </button>
                                </div>
                            </div>
                            <!-- Generate ulang: muat 10 berikutnya (maks 50) bila belum cocok -->
                            <button
                                v-if="refHasMore"
                                type="button"
                                class="w-full cursor-pointer rounded-lg border border-dashed border-neutral-300 px-3 py-2 text-xs font-medium text-neutral-600 transition-colors hover:border-neutral-500 hover:text-neutral-900 disabled:opacity-50 dark:border-neutral-700 dark:text-neutral-300 dark:hover:border-neutral-500 dark:hover:text-white"
                                :disabled="refSearching"
                                @click="loadMoreReferences"
                            >
                                {{ refSearching ? 'Memuat…' : `Muat 10 lagi (halaman ${refPage + 1}, maks 50)` }}
                            </button>
                            <p v-else-if="refResults.length >= 50" class="text-center text-[11px] text-neutral-400 dark:text-neutral-500">
                                Sudah 50 data. Persempit kata kunci untuk hasil lain.
                            </p>
                        </div>
                    </div>

                    <!-- Peringatan bila belum ada referensi terverifikasi -->
                    <div
                        v-if="!verifiedReferences.length"
                        class="border-b border-amber-200 bg-amber-50/70 px-4 py-2.5 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200"
                    >
                        Belum ada referensi di Tulisin Workspace. Tanpa referensi, AI tidak akan menyitasi apa pun (agar tidak mengarang sumber). Unggah PDF di menu
                        <a href="/apps/u/workspace" class="font-semibold underline underline-offset-2">Workspace</a>
                        terlebih dahulu.
                    </div>

                    <!-- Cara kerja -->
                    <div v-if="showHelp" class="border-b border-neutral-200 bg-blue-50/70 px-4 py-3 text-xs text-neutral-600 dark:border-neutral-800 dark:bg-blue-950/20 dark:text-neutral-300">
                        <p class="font-semibold">Cara kerja Agent AI Canvas:</p>
                        <ul class="mt-1 list-disc space-y-0.5 pl-4">
                            <li>Pilih dulu <strong>Tujuan</strong>: abstrak, bab yang sudah ada, atau bagian baru. Pilihan ini otomatis mengikuti blok yang kamu klik di canvas.</li>
                            <li>Buat beberapa chat untuk topik berbeda, mis. "Judul", "Bab 1", "Bab 2".</li>
                            <li>Agent membaca seluruh isi canvas + daftar jenis blok yang tersedia.</li>
                            <li>Referensi dari Tulisin Workspace dikirim sebagai satu-satunya sumber sitasi. Agent hanya menyitasi dari daftar itu, jadi nama penulis, tahun, dan tautannya bisa diverifikasi.</li>
                            <li>Sitasi dipasang OTOMATIS di akhir kalimat yang didukung (format (Penulis, Tahun)), dan hasilnya ditulis ulang orisinal dengan target kemiripan di bawah 30 persen.</li>
                            <li>Tulis permintaan; agent menjawab dengan blok <code class="rounded bg-neutral-200/60 px-1 py-0.5 dark:bg-neutral-800">canvas</code> bila bisa langsung dimasukkan.</li>
                            <li>Klik "Generate ke Canvas" untuk menyisipkan ke dokumen.</li>
                            <li>Panel "Sumber" di bawah balasan menyediakan tautan sumber dan tombol salin sitasi.</li>
                            <li>Seluruh riwayat chat tersimpan dan bisa dibuka kembali.</li>
                        </ul>
                    </div>

                    <!-- Pesan -->
                    <div ref="listEl" class="flex-1 space-y-3 overflow-y-auto p-4">
                        <template v-if="messages.length === 0">
                            <div class="flex h-full flex-col items-center justify-center gap-3 text-center">
                                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-neutral-900 text-white dark:bg-white dark:text-neutral-950">
                                    <Sparkles class="h-6 w-6" />
                                </span>
                                <p class="max-w-sm text-sm text-neutral-600 dark:text-neutral-300">
                                    Halo! Tanyakan apa saja tentang dokumen kamu, atau minta agent menyusun isinya.
                                </p>
                                <div class="mt-1 flex max-w-sm flex-wrap justify-center gap-1.5">
                                    <button
                                        v-for="p in starterPrompts"
                                        :key="p"
                                        type="button"
                                        class="cursor-pointer rounded-full border border-neutral-200 px-3 py-1.5 text-left text-xs text-neutral-600 transition-colors hover:border-neutral-400 hover:text-neutral-900 dark:border-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100"
                                        @click="send(p)"
                                    >{{ p }}</button>
                                </div>
                            </div>
                        </template>

                        <template v-else>
                            <div
                                v-for="(m, i) in messages"
                                :key="m.id || i"
                                class="text-sm"
                                :class="m.role === 'user' ? 'text-right' : 'text-left'"
                            >
                                <span
                                    class="inline-block max-w-full whitespace-pre-wrap rounded-lg px-3 py-2 text-left"
                                    :class="m.role === 'user'
                                        ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-950'
                                        : 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200'"
                                    v-html="renderMarkdown(m.text)"
                                ></span>

                                <div v-if="m.role === 'assistant' && hasCanvasFence(m.text)" class="mt-1.5">
                                    <button
                                        type="button"
                                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-neutral-300 px-2.5 py-1 text-xs font-medium text-neutral-700 transition-colors hover:bg-neutral-900 hover:text-white dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-white dark:hover:text-neutral-950"
                                        @click="emit('apply', { text: m.text, mode: insertMode === 'pick' ? 'pick' : insertMode, target: target.value })"
                                    >
                                        <Plus class="h-3.5 w-3.5" />
                                        {{ insertMode === 'pick' ? 'Terapkan per bagian' : 'Generate ke Canvas' }}
                                    </button>
                                </div>

                                <!-- Sumber terverifikasi yang dipakai balasan ini -->
                                <div
                                    v-if="m.role === 'assistant' && citedInMessage(m.text).length"
                                    class="mt-2 rounded-lg border border-neutral-200 bg-neutral-50 p-2.5 dark:border-neutral-800 dark:bg-neutral-900/40"
                                >
                                    <p class="flex items-center gap-1.5 text-[11px] font-semibold text-neutral-500 dark:text-neutral-400">
                                        <Link2 class="h-3.5 w-3.5" />
                                        Sumber
                                    </p>
                                    <ul class="mt-1.5 space-y-1">
                                        <li
                                            v-for="(r, ri) in citedInMessage(m.text)"
                                            :key="ri"
                                            class="flex items-start gap-1.5 text-[11px] text-neutral-600 dark:text-neutral-300"
                                        >
                                            <span class="min-w-0 flex-1">{{ r.label }}</span>
                                            <a
                                                v-if="r.link"
                                                :href="r.link"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="shrink-0 font-medium text-blue-600 underline underline-offset-2 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                                title="Buka sumber"
                                            >
                                                <ExternalLink class="h-3.5 w-3.5" />
                                            </a>
                                            <button
                                                type="button"
                                                class="shrink-0 cursor-pointer text-neutral-400 transition-colors hover:text-neutral-900 dark:hover:text-white"
                                                title="Salin sitasi"
                                                @click="copyCitation(r)"
                                            >
                                                <Copy class="h-3.5 w-3.5" />
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div v-if="sending" class="flex items-center gap-2 text-sm text-neutral-400 dark:text-neutral-500">
                                <Loader2 class="h-4 w-4 animate-spin" />
                                Agent sedang membaca canvas…
                            </div>
                        </template>
                    </div>

                    <!-- Input -->
                    <div class="border-t border-neutral-200 p-3 dark:border-neutral-800">
                        <div class="mb-2 flex items-center gap-2">
                            <span class="shrink-0 text-xs font-medium text-neutral-500 dark:text-neutral-400">Tujuan:</span>
                            <select
                                v-model="target"
                                title="Bagian mana yang sedang kamu kerjakan (abstrak, bab, atau bagian baru)"
                                class="w-full rounded-lg border border-neutral-200 bg-transparent px-2.5 py-1.5 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                            >
                                <option value="">Seluruh dokumen</option>
                                <option v-for="t in targets" :key="t.id" :value="t.id">{{ t.label }}</option>
                            </select>
                        </div>
                        <p v-if="targetLabel" class="mb-2 text-[11px] text-neutral-400 dark:text-neutral-500">
                            Menulis untuk: <span class="font-medium text-neutral-600 dark:text-neutral-300">{{ targetLabel }}</span>. Hasilnya dipetakan ke bagian ini saat Generate ke Canvas.
                        </p>
                        <div class="mb-2 flex items-center gap-2">
                            <span class="shrink-0 text-xs font-medium text-neutral-500 dark:text-neutral-400">Format:</span>
                            <select
                                v-model="format"
                                :disabled="formatLocked"
                                :title="formatLocked ? `Format ${formatLabel} terkunci untuk chat ini` : 'Pilih format dokumen (skripsi, tesis, dll)'"
                                class="w-full rounded-lg border border-neutral-200 bg-transparent px-2.5 py-1.5 text-sm outline-none transition-colors focus:border-neutral-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                            >
                                <option value="" disabled>Pilih format…</option>
                                <option v-for="o in formatOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                            </select>
                        </div>
                        <p v-if="formatLocked" class="mb-2 flex items-center gap-1.5 text-[11px] text-neutral-400 dark:text-neutral-500">
                            <Lock class="h-3 w-3 shrink-0" />
                            Format {{ formatLabel }} terkunci untuk chat ini agar mekanismenya konsisten.
                        </p>
                        <p v-else-if="activeSessionId" class="mb-2 flex items-center gap-1.5 text-[11px] text-amber-600 dark:text-amber-400">
                            <Lock class="h-3 w-3 shrink-0" />
                            Chat lama ini belum punya format. Pilih format, lalu terkunci permanen saat pesan pertama dikirim.
                        </p>

                        <div class="mb-2 flex items-center gap-2">
                            <span class="shrink-0 text-xs font-medium text-neutral-500 dark:text-neutral-400">Sisipkan:</span>
                            <div class="flex flex-1 gap-1 rounded-lg border border-neutral-200 p-0.5 dark:border-neutral-800">
                                <button
                                    type="button"
                                    class="flex-1 cursor-pointer rounded-md px-2 py-1 text-xs font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                                    :class="insertMode === 'pick'
                                        ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-950'
                                        : 'text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-200'"
                                    :disabled="formatLocked"
                                    :title="formatLocked ? 'Gaya sisip terkunci untuk chat ini' : 'Terapkan per bagian: bab yang sudah ada digantikan, sisanya ditambahkan'"
                                    @click="insertMode = 'pick'"
                                >Per bagian</button>
                                <button
                                    type="button"
                                    class="flex-1 cursor-pointer rounded-md px-2 py-1 text-xs font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                                    :class="insertMode === 'after'
                                        ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-950'
                                        : 'text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-200'"
                                    :disabled="formatLocked"
                                    :title="formatLocked ? 'Gaya sisip terkunci untuk chat ini' : 'Sisipkan seluruh hasil setelah blok terpilih'"
                                    @click="insertMode = 'after'"
                                >Setelah blok</button>
                                <button
                                    type="button"
                                    class="flex-1 cursor-pointer rounded-md px-2 py-1 text-xs font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                                    :class="insertMode === 'replace'
                                        ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-950'
                                        : 'text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-200'"
                                    :disabled="!hasSelection || formatLocked"
                                    :title="formatLocked ? 'Gaya sisip terkunci untuk chat ini' : (hasSelection ? 'Ganti blok yang sedang dipilih' : 'Pilih blok di canvas terlebih dahulu')"
                                    @click="insertMode = 'replace'"
                                >Ganti blok</button>
                            </div>
                        </div>

                        <div class="flex items-end gap-2">
                            <textarea
                                v-model="input"
                                rows="2"
                                :disabled="!format"
                                :placeholder="format ? 'Tanyakan atau minta agent mengerjakan sesuatu di canvas…' : 'Pilih format dokumen dulu…'"
                                class="min-h-0 flex-1 resize-none rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                                @keydown="onKeydown"
                            ></textarea>
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-lg border border-neutral-900 text-neutral-900 transition-colors hover:bg-neutral-900 hover:text-white disabled:cursor-not-allowed disabled:opacity-50 dark:border-white dark:text-white dark:hover:bg-white dark:hover:text-neutral-950"
                                aria-label="Kirim"
                                :disabled="sending || !format"
                                :title="!format ? 'Pilih format dokumen terlebih dahulu' : 'Kirim'"
                                @click="send()"
                            >
                                <Send class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>
