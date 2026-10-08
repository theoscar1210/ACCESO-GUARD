<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { Camera, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { BreadcrumbItem } from '@/types';

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
};

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Perfil',
        href: edit(),
    },
];

const page = usePage();
const user = computed(() => page.props.auth.user);

// Avatar upload
const fileInput = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(null);
const uploading = ref(false);

function pickFile() {
    fileInput.value?.click();
}

function onFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    previewUrl.value = URL.createObjectURL(file);
    uploadAvatar(file);
}

function uploadAvatar(file: File) {
    uploading.value = true;
    const data = new FormData();
    data.append('avatar', file);
    router.post('/settings/avatar', data, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => { uploading.value = false; },
    });
}

function removeAvatar() {
    router.delete('/settings/avatar', { preserveScroll: true });
    previewUrl.value = null;
}

const avatarSrc = computed(() => previewUrl.value ?? user.value?.avatar_url ?? null);
const avatarInitials = computed(() => {
    const name = user.value?.name ?? '';
    return name.split(' ').map((w: string) => w[0]).join('').slice(0, 2).toUpperCase();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Perfil" />

        <h1 class="sr-only">Configuración de perfil</h1>

        <SettingsLayout>
            <div class="flex flex-col space-y-6">

                <!-- Foto de perfil -->
                <Heading
                    variant="small"
                    title="Foto de perfil"
                    description="Esta foto aparecerá en tu carnet digital y en el menú lateral"
                />
                <div class="flex items-center gap-5">
                    <div class="relative">
                        <div class="h-20 w-20 overflow-hidden rounded-full border-2 border-primary/20 bg-muted">
                            <img v-if="avatarSrc" :src="avatarSrc" alt="Avatar" class="h-full w-full object-cover" />
                            <div v-else class="flex h-full w-full items-center justify-center text-xl font-bold text-muted-foreground">
                                {{ avatarInitials }}
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="pickFile"
                            :disabled="uploading"
                            class="absolute -bottom-1 -right-1 flex h-7 w-7 items-center justify-center rounded-full border border-border bg-background shadow-sm transition hover:bg-muted disabled:opacity-50"
                        >
                            <Camera class="h-3.5 w-3.5 text-muted-foreground" />
                        </button>
                        <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onFileChange" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Button type="button" variant="outline" size="sm" @click="pickFile" :disabled="uploading">
                            {{ uploading ? 'Subiendo...' : 'Cambiar foto' }}
                        </Button>
                        <button
                            v-if="avatarSrc"
                            type="button"
                            @click="removeAvatar"
                            class="flex items-center gap-1.5 text-xs text-destructive hover:underline"
                        >
                            <Trash2 class="h-3 w-3" /> Eliminar foto
                        </button>
                    </div>
                </div>

                <!-- Información del perfil -->
                <Heading
                    variant="small"
                    title="Información del perfil"
                    description="Actualiza tu nombre y correo electrónico"
                />

                <Form
                    v-bind="ProfileController.update.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="first_name">Nombres</Label>
                            <Input
                                id="first_name"
                                class="mt-1 block w-full"
                                name="first_name"
                                :default-value="user.first_name"
                                required
                                autocomplete="given-name"
                                placeholder="Nombres"
                            />
                            <InputError class="mt-2" :message="errors.first_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="last_name">Apellidos</Label>
                            <Input
                                id="last_name"
                                class="mt-1 block w-full"
                                name="last_name"
                                :default-value="user.last_name"
                                required
                                autocomplete="family-name"
                                placeholder="Apellidos"
                            />
                            <InputError class="mt-2" :message="errors.last_name" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Correo electrónico</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            name="email"
                            :default-value="user.email"
                            required
                            autocomplete="username"
                            placeholder="correo@ejemplo.com"
                        />
                        <InputError class="mt-2" :message="errors.email" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="-mt-4 text-sm text-muted-foreground">
                            Tu correo electrónico no está verificado.
                            <Link
                                :href="send()"
                                as="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                Haz clic aquí para reenviar el correo de verificación.
                            </Link>
                        </p>

                        <div
                            v-if="status === 'verification-link-sent'"
                            class="mt-2 text-sm font-medium text-green-600"
                        >
                            Se ha enviado un enlace de verificación a tu correo electrónico.
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button
                            :disabled="processing"
                            data-test="update-profile-button"
                            >Guardar</Button
                        >

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful"
                                class="text-sm text-neutral-600"
                            >
                                Guardado.
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
