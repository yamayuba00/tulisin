<script setup>
import { Send } from 'lucide-vue-next';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Publish Jurnal" description="Publikasikan karya ilmiahmu langsung ke jurnal dalam satu alur kerja." />

        <EmptyState
            :icon="Send"
            title="Segera Hadir"
            description="Fitur Publish Jurnal sedang dalam pengembangan. Nantikan pembaruan berikutnya."
        />
    </div>
</template>
