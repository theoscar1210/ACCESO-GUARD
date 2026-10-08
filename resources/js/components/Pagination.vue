<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';

/** Paginador de Laravel tal como llega a Inertia */
export interface Paginator {
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{ paginator: Paginator }>();

// El primer y último enlace son "anterior" y "siguiente"; su texto depende del idioma,
// así que se identifican por posición y no por etiqueta
const pageLinks = computed(() => props.paginator.links.slice(1, -1));
</script>

<template>
    <div
        v-if="paginator.last_page > 1"
        class="flex items-center justify-between gap-3 text-sm"
    >
        <p class="text-muted-foreground">
            {{ paginator.from }}–{{ paginator.to }}
            <span class="hidden sm:inline">de {{ paginator.total.toLocaleString() }} registros</span>
            <span class="sm:hidden">de {{ paginator.total.toLocaleString() }}</span>
        </p>

        <nav class="flex items-center gap-1" aria-label="Paginación">
            <component
                :is="paginator.prev_page_url ? Link : 'span'"
                :href="paginator.prev_page_url ?? undefined"
                preserve-state
                aria-label="Página anterior"
                :class="[
                    'inline-flex h-10 min-w-10 items-center justify-center rounded-md transition-colors sm:h-8 sm:min-w-8',
                    paginator.prev_page_url ? 'hover:bg-muted' : 'pointer-events-none opacity-40',
                ]"
            >
                <ChevronLeft class="h-5 w-5 sm:h-4 sm:w-4" />
            </component>

            <!-- Móvil: solo la página actual -->
            <span class="px-2 font-medium sm:hidden">
                {{ paginator.current_page }} / {{ paginator.last_page }}
            </span>

            <!-- Tablet y PC: números de página (Laravel ya agrega los "…") -->
            <template v-for="(link, i) in pageLinks" :key="i">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-state
                    :aria-current="link.active ? 'page' : undefined"
                    :class="[
                        'hidden h-8 min-w-8 items-center justify-center rounded-md px-2 transition-colors sm:inline-flex',
                        link.active ? 'bg-primary font-semibold text-primary-foreground' : 'hover:bg-muted',
                    ]"
                >
                    {{ link.label }}
                </Link>
                <span
                    v-else
                    class="hidden h-8 min-w-6 items-center justify-center text-muted-foreground sm:inline-flex"
                >
                    {{ link.label }}
                </span>
            </template>

            <component
                :is="paginator.next_page_url ? Link : 'span'"
                :href="paginator.next_page_url ?? undefined"
                preserve-state
                aria-label="Página siguiente"
                :class="[
                    'inline-flex h-10 min-w-10 items-center justify-center rounded-md transition-colors sm:h-8 sm:min-w-8',
                    paginator.next_page_url ? 'hover:bg-muted' : 'pointer-events-none opacity-40',
                ]"
            >
                <ChevronRight class="h-5 w-5 sm:h-4 sm:w-4" />
            </component>
        </nav>
    </div>
</template>
