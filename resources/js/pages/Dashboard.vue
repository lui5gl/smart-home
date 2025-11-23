<script setup lang="ts">
import DeviceController from '@/actions/App/Http/Controllers/DeviceController';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Form, Head } from '@inertiajs/vue3';
import { IconPlus } from '@tabler/icons-vue';
import { ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const isAddDeviceOpen = ref(false);
const deviceName = ref('');
const deviceLocation = ref('');
type DeviceType = 'switch' | 'dimmer';
const defaultDeviceType: DeviceType = 'switch';
const deviceType = ref<DeviceType>(defaultDeviceType);

const resetDeviceForm = (): void => {
    deviceName.value = '';
    deviceLocation.value = '';
    deviceType.value = defaultDeviceType;
};

watch(isAddDeviceOpen, (isOpen) => {
    if (!isOpen) {
        resetDeviceForm();
    }
});

const handleDeviceStored = (): void => {
    isAddDeviceOpen.value = false;
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-6">
            <div class="flex justify-end">
                <Dialog :open="isAddDeviceOpen" @update:open="isAddDeviceOpen = $event">
                    <DialogTrigger as-child>
                        <Button size="lg">
                            <IconPlus class="size-4" />
                            Agregar dispositivo
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-md">
                        <DialogHeader class="space-y-2">
                            <DialogTitle>Agregar dispositivo</DialogTitle>
                            <DialogDescription>
                                Ingresa el nombre del dispositivo que deseas agregar.
                            </DialogDescription>
                        </DialogHeader>

                        <Form
                            v-bind="DeviceController.store.form()"
                            reset-on-success
                            @success="handleDeviceStored"
                            class="space-y-6"
                            v-slot="{ errors, processing }"
                        >
                            <div class="grid gap-4">
                                <div class="grid gap-2">
                                    <Label for="device-name">Nombre del dispositivo</Label>
                                    <Input
                                        id="device-name"
                                        v-model="deviceName"
                                        name="name"
                                        autocomplete="off"
                                        placeholder="Ej. Sensor de temperatura"
                                        required
                                        :aria-invalid="Boolean(errors.name)"
                                    />
                                    <InputError :message="errors.name" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="device-location">Ubicación</Label>
                                    <Input
                                        id="device-location"
                                        v-model="deviceLocation"
                                        name="location"
                                        autocomplete="off"
                                        placeholder="Ej. Sala principal"
                                        :aria-invalid="Boolean(errors.location)"
                                    />
                                    <InputError :message="errors.location" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="device-type">Tipo de dispositivo</Label>
                                    <select
                                        id="device-type"
                                        v-model="deviceType"
                                        name="type"
                                        class="border-input focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring md:text-sm"
                                    >
                                        <option value="switch">Encendido / Apagado</option>
                                        <option value="dimmer">Regulable</option>
                                    </select>
                                    <InputError :message="errors.type" />
                                </div>
                            </div>

                            <DialogFooter class="gap-2">
                                <DialogClose as-child>
                                    <Button type="button" variant="secondary">Cancelar</Button>
                                </DialogClose>
                                <Button type="submit" :disabled="processing">Agregar</Button>
                            </DialogFooter>
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    </AppLayout>
</template>
