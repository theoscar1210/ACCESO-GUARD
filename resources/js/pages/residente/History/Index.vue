<script setup lang="ts">
import type { Paginator } from '@/components/Pagination.vue';
import VisitHistory from '@/components/VisitHistory.vue';
import type { HistoryEntry, HistoryFilters } from '@/components/VisitHistory.vue';

const props = defineProps<{
    entries: Paginator & { data: HistoryEntry[] };
    filters: HistoryFilters;
    apartment: string | null;
}>();
</script>

<template>
    <VisitHistory v-bind="props" endpoint="/residente/history" />
</template>
