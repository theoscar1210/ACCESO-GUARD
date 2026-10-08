<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

const loading = ref(false);

const filters = ref({
    format: 'pdf',
    date_from: '',
    date_to: '',
    type: '',
    cedula: '',
    only: '',
});

async function exportReport() {
    loading.value = true;

    const params = new URLSearchParams();
    Object.entries(filters.value).forEach(([k, v]) => {
        if (v) params.append(k, v);
    });

    // POST que devuelve un archivo — usamos un form submit nativo
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/vigilante/reports/export';

    // CSRF token
    const csrf = document.querySelector(
        'meta[name="csrf-token"]',
    ) as HTMLMetaElement;
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_token';
    csrfInput.value = csrf?.content ?? '';
    form.appendChild(csrfInput);

    // Campos del formulario
    Object.entries(filters.value).forEach(([k, v]) => {
        if (v) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = k;
            input.value = v;
            form.appendChild(input);
        }
    });

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);

    setTimeout(() => {
        loading.value = false;
    }, 2000);
}
</script>

<template>
    <AppLayout>
        <Head title="Reportes" />

        <div class="mx-auto max-w-2xl p-4 sm:p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold">Generar Reporte</h1>
                <p class="text-sm text-muted-foreground">
                    Exporta el registro de ingresos en PDF o Excel
                </p>
            </div>

            <div class="space-y-5 rounded-xl border bg-card p-6 shadow-sm">
                <!-- Formato -->
                <div class="grid gap-2">
                    <Label>Formato de exportación *</Label>
                    <div class="flex gap-3">
                        <label
                            v-for="opt in [
                                { value: 'pdf', label: '📄 PDF' },
                                { value: 'excel', label: '📊 Excel (.xlsx)' },
                            ]"
                            :key="opt.value"
                            :class="[
                                'flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-lg border p-3 text-sm font-medium transition-colors',
                                filters.format === opt.value
                                    ? 'border-primary bg-primary/5 text-primary'
                                    : 'hover:bg-muted/50',
                            ]"
                        >
                            <input
                                type="radio"
                                v-model="filters.format"
                                :value="opt.value"
                                class="hidden"
                            />
                            {{ opt.label }}
                        </label>
                    </div>
                </div>

                <!-- Rango de fechas -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="date_from">Fecha desde</Label>
                        <Input
                            id="date_from"
                            type="date"
                            v-model="filters.date_from"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="date_to">Fecha hasta</Label>
                        <Input
                            id="date_to"
                            type="date"
                            v-model="filters.date_to"
                        />
                    </div>
                </div>

                <!-- Tipo de visitante -->
                <div class="grid gap-1.5">
                    <Label for="type">Tipo de visitante</Label>
                    <select
                        id="type"
                        v-model="filters.type"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                    >
                        <option value="">Todos</option>
                        <option value="propietario">Propietario</option>
                        <option value="autorizado">Autorizado</option>
                        <option value="visitante">Visitante</option>
                        <option value="proveedor">Proveedor</option>
                    </select>
                </div>

                <!-- Filtro por cédula -->
                <div class="grid gap-1.5">
                    <Label for="cedula">Filtrar por cédula</Label>
                    <Input
                        id="cedula"
                        v-model="filters.cedula"
                        placeholder="Número de cédula (parcial o completo)"
                    />
                </div>

                <!-- Solo ingresos o salidas -->
                <div class="grid gap-1.5">
                    <Label for="only">Mostrar</Label>
                    <select
                        id="only"
                        v-model="filters.only"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                    >
                        <option value="">Todos (ingresos y salidas)</option>
                        <option value="entries">
                            Solo personas dentro (sin salida)
                        </option>
                        <option value="exits">
                            Solo con salida registrada
                        </option>
                    </select>
                </div>

                <div class="flex gap-3">
                <Button
                    type="button"
                    variant="outline"
                    class="flex-1"
                    @click="filters = { format: 'pdf', date_from: '', date_to: '', type: '', cedula: '', only: '' }"
                >Limpiar</Button>
                <Button
                    @click="exportReport"
                    class="flex-1"
                    :disabled="loading"
                >
                    <span v-if="loading">Generando reporte...</span>
                    <span v-else>
                        {{
                            filters.format === 'pdf'
                                ? '📄 Descargar PDF'
                                : '📊 Descargar Excel'
                        }}
                    </span>
                </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
