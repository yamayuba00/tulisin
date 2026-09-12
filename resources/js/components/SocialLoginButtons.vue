<script setup>
// Tombol login Google. Berupa tautan ke route redirect OAuth Laravel.
</script>

<template>
    <div>
        <div class="my-4 flex items-center gap-3 text-xs text-neutral-400 dark:text-neutral-500">
            <span class="h-px flex-1 bg-neutral-200 dark:bg-neutral-800"></span>
            atau lanjutkan dengan
            <span class="h-px flex-1 bg-neutral-200 dark:bg-neutral-800"></span>
        </div>

        <a
            href="/auth/google/redirect"
            class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-neutral-200 bg-transparent px-4 py-2.5 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:border-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-900"
        >
            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor" aria-hidden="true">
                <path d="M12.48 10.92v3.28h7.84c-.24 1.84-.853 3.187-1.787 4.133-1.147 1.147-2.933 2.4-6.053 2.4-4.827 0-8.6-3.893-8.6-8.72s3.773-8.72 8.6-8.72c2.6 0 4.507 1.027 5.907 2.347l2.307-2.307C18.747 1.44 16.133 0 12.48 0 5.867 0 .307 5.387.307 12s5.56 12 12.173 12c3.573 0 6.267-1.173 8.373-3.36 2.16-2.16 2.84-5.213 2.84-7.667 0-.76-.053-1.467-.173-2.053H12.48z" />
            </svg>
            Lanjutkan dengan Google
        </a>
    </div>
</template>
