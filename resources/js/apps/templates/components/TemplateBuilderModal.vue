<script setup>
import { reactive, ref } from 'vue';
import { X, Plus, Trash2, GripVertical } from 'lucide-vue-next';
import { TEMPLATE_BLOCK_TYPES } from '../../../utils/templates';

const open = defineModel('open', { type: Boolean, default: false });
const emit = defineEmits(['save']);

const form = reactive({
    title: '',
    category: '',
    description: '',
    format: 'A4',
    font: 'Times New Roman',
});

const blocks = ref([{ type: 'cover', content: '' }]);

function addBlock() {
    blocks.value.push({ type: 'paragraph', content: '' });
}

function removeBlock(i) {
    if (blocks.value.length === 1) {
        blocks.value[0] = { type: 'cover', content: '' };
        return;
    }
    blocks.value.splice(i, 1);
}

function moveBlock(i, dir) {
    const j = i + dir;
    if (j < 0 || j >= blocks.value.length) return;
    const arr = blocks.value;
    [arr[i], arr[j]] = [arr[j], arr[i]];
}

function reset() {
    form.title = '';
    form.category = '';
    form.description = '';
    form.format = 'A4';
    form.font = 'Times New Roman';
    blocks.value = [{ type: 'cover', content: '' }];
}

function submit() {
    const title = form.title.trim();
    if (!title) {
        toast('Judul template wajib diisi.', 'warning');
        return;
    }
    const template = {
        id: 'custom-' + crypto.randomUUID(),
        title,
        category: form.category.trim() || 'Custom',
        description: form.description.trim() || 'Template kustom buatan sendiri.',
        format: form.format,
        font: form.font.trim() || 'Times New Roman',
        blocks: blocks.value
            .filter((b) => b.type)
            .map((b) => ({ type: b.type, content: b.content })),
        custom: true,
    };
    emit('save', template);
    reset();
    open.value = false;
}

function close() {
    reset();
    open.value = false;
}
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="close"></div>

        <div class="relative z-10 flex max-h-[85vh] w-full max-w-lg flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-2xl dark:border-neutral-800 dark:bg-neutral-950">
            <div class="flex items-start justify-between border-b border-neutral-200 px-5 py-3 dark:border-neutral-800">
                <div>
                    <h2 class="text-base font-semibold">Buat Template Sendiri</h2>
                    <p class="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">Susun struktur blok sesuai kebutuhanmu.</p>
                </div>
                <button
                    type="button"
                    class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-md text-neutral-500 transition-colors hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white"
                    aria-label="Tutup"
                    @click="close"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">Judul *</span>
                        <input
                            v-model="form.title"
                            type="text"
                            placeholder="cth. Skripsi Informatika"
                            class="w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none focus:border-neutral-500 dark:border-neutral-800 dark:focus:border-neutral-400"
                        />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">Kategori</span>
                        <input
                            v-model="form.category"
                            type="text"
                            placeholder="cth. Skripsi"
                            class="w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none focus:border-neutral-500 dark:border-neutral-800 dark:focus:border-neutral-400"
                        />
                    </label>
                </div>

                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">Deskripsi</span>
                    <input
                        v-model="form.description"
                        type="text"
                        placeholder="Deskripsi singkat template"
                        class="w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none focus:border-neutral-500 dark:border-neutral-800 dark:focus:border-neutral-400"
                    />
                </label>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">Format</span>
                        <select
                            v-model="form.format"
                            class="w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                        >
                            <option value="A4">A4</option>
                            <option value="Letter">Letter</option>
                            <option value="Legal">Legal</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">Font</span>
                        <input
                            v-model="form.font"
                            type="text"
                            placeholder="Times New Roman"
                            class="w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none focus:border-neutral-500 dark:border-neutral-800 dark:focus:border-neutral-400"
                        />
                    </label>
                </div>

                <div>
                    <p class="mb-2 text-xs font-medium text-neutral-500 dark:text-neutral-400">Struktur Blok ({{ blocks.length }})</p>
                    <div class="space-y-2">
                        <div
                            v-for="(b, i) in blocks"
                            :key="i"
                            class="flex items-center gap-2 rounded-lg border border-neutral-200 p-2 dark:border-neutral-800"
                        >
                            <span class="shrink-0 text-neutral-400 dark:text-neutral-600">
                                <GripVertical class="h-4 w-4" />
                            </span>
                            <select
                                v-model="b.type"
                                class="w-36 shrink-0 rounded-lg border border-neutral-200 bg-transparent px-2 py-1.5 text-sm outline-none focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                            >
                                <option v-for="bt in TEMPLATE_BLOCK_TYPES" :key="bt.type" :value="bt.type">{{ bt.label }}</option>
                            </select>
                            <input
                                v-model="b.content"
                                type="text"
                                placeholder="Isi / label (opsional)"
                                class="min-w-0 flex-1 rounded-lg border border-neutral-200 bg-transparent px-2 py-1.5 text-sm outline-none focus:border-neutral-500 dark:border-neutral-800 dark:focus:border-neutral-400"
                            />
                            <button
                                type="button"
                                class="inline-flex h-7 w-7 shrink-0 cursor-pointer items-center justify-center rounded-md text-neutral-400 transition-colors hover:bg-neutral-100 hover:text-red-600 dark:hover:bg-neutral-800 dark:hover:text-red-400"
                                aria-label="Hapus blok"
                                @click="removeBlock(i)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="mt-2 inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-neutral-200 px-3 py-1.5 text-sm text-neutral-600 transition-colors hover:bg-neutral-100 dark:border-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-800"
                        @click="addBlock"
                    >
                        <Plus class="h-4 w-4" /> Tambah Blok
                    </button>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-neutral-200 px-5 py-3 dark:border-neutral-800">
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-neutral-200 px-3 py-2 text-sm text-neutral-600 transition-colors hover:bg-neutral-100 dark:border-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    @click="close"
                >
                    Batal
                </button>
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-neutral-900 px-3 py-2 text-sm font-medium text-neutral-900 transition-colors hover:bg-neutral-900 hover:text-white dark:border-white dark:text-white dark:hover:bg-white dark:hover:text-neutral-950"
                    @click="submit"
                >
                    Simpan Template
                </button>
            </div>
        </div>
    </div>
</template>
