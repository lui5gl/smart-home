<script setup lang="ts">
import DeviceController from '@/actions/App/Http/Controllers/DeviceController';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Form, Head } from '@inertiajs/vue3';
import { IconBulb, IconMapPin, IconPlus } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

type DeviceType = 'switch' | 'dimmer';

interface DeviceItem {
    id: number;
    name: string;
    location: string | null;
    type: DeviceType;
    created_at: string | null;
}

interface Props {
    devices: DeviceItem[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const devices = computed(() => props.devices);
const hasDevices = computed(() => devices.value.length > 0);

const isAddDeviceOpen = ref(false);
const deviceName = ref('');
const deviceLocation = ref('');
const defaultDeviceType: DeviceType = 'switch';
const deviceType = ref<DeviceType>(defaultDeviceType);

const deviceTypeLabels: Record<DeviceType, string> = {
    switch: 'Encendido / Apagado',
    dimmer: 'Regulable',
};

const addedAtFormatter = new Intl.DateTimeFormat('es-MX', {
    dateStyle: 'medium',
});

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

const formatAddedAt = (timestamp: string | null): string => {
    if (!timestamp) {
        return 'Fecha desconocida';
    }

    return addedAtFormatter.format(new Date(timestamp));
};

const locationLabel = (location: string | null): string => {
    if (!location) {
        return 'Sin ubicación asignada';
    }

    return location;
};

const handleDeviceStored = (): void => {
    resetDeviceForm();
    isAddDeviceOpen.value = false;
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-8 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold leading-tight">Tus dispositivos</h1>
                    <p class="text-sm text-muted-foreground">
                        Consulta y administra los dispositivos inteligentes de tu hogar.
                    </p>
                </div>

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

            <div v-if="hasDevices" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Card v-for="device in devices" :key="device.id" class="border-border/70">
                    <CardHeader class="flex flex-row items-start justify-between gap-4">
                        <div>
                            <CardTitle class="text-lg font-semibold">{{ device.name }}</CardTitle>
                            <CardDescription>Agregado el {{ formatAddedAt(device.created_at) }}</CardDescription>
                        </div>
                        <Badge variant="secondary">
                            <IconBulb class="size-3.5" />
                            {{ deviceTypeLabels[device.type] }}
                        </Badge>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm text-muted-foreground">
                        <p class="flex items-center gap-2">
                            <IconMapPin class="size-4 text-foreground/70" />
                            <span>{{ locationLabel(device.location) }}</span>
                        </p>
                        <p class="flex items-center gap-2">
                            <IconBulb class="size-4 text-foreground/70" />
                            <span>{{ deviceTypeLabels[device.type] }}</span>
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div
                v-else
                class="flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed border-border/80 px-6 py-12 text-center"
            >
                <IconBulb class="size-10 text-muted-foreground" />
                <div class="space-y-1">
                    <p class="text-base font-medium">Aún no tienes dispositivos</p>
                    <p class="text-sm text-muted-foreground">
                        Agrega tu primer dispositivo para visualizarlo en esta lista.
                    </p>
                </div>
                <Button size="lg" @click="isAddDeviceOpen = true">
                    <IconPlus class="size-4" />
                    Agregar dispositivo
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
