<script setup lang="ts">
import DeviceController from '@/actions/App/Http/Controllers/DeviceController';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Form, Head, router } from '@inertiajs/vue3';
import { IconBulb, IconMapPin, IconPencil, IconPlus } from '@tabler/icons-vue';
import { computed, reactive, ref, watch } from 'vue';

type DeviceType = 'switch' | 'dimmer';
type DeviceStatus = 'on' | 'off';

interface DeviceItem {
    id: number;
    name: string;
    location: string | null;
    type: DeviceType;
    status: DeviceStatus;
    created_at: string | null;
    updated_at: string | null;
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

const deviceDialogMode = ref<'create' | 'edit'>('create');
const isDeviceDialogOpen = ref(false);
const editingDevice = ref<DeviceItem | null>(null);
const deviceName = ref('');
const deviceLocation = ref('');
const defaultDeviceType: DeviceType = 'switch';
const deviceType = ref<DeviceType>(defaultDeviceType);
const defaultDeviceStatus: DeviceStatus = 'off';
const deviceStatus = ref<DeviceStatus>(defaultDeviceStatus);
const isEditingDevice = computed(() => deviceDialogMode.value === 'edit');

const deviceTypeLabels: Record<DeviceType, string> = {
    switch: 'Encendido / Apagado',
    dimmer: 'Regulable',
};
const statusLabels: Record<DeviceStatus, string> = {
    on: 'Encendido',
    off: 'Apagado',
};

const dateFormatter = new Intl.DateTimeFormat('es-MX', {
    dateStyle: 'medium',
});

const statusUpdating = reactive<Record<number, boolean>>({});

const resetDeviceForm = (): void => {
    deviceName.value = '';
    deviceLocation.value = '';
    deviceType.value = defaultDeviceType;
    deviceStatus.value = defaultDeviceStatus;
};

const openCreateDialog = (): void => {
    deviceDialogMode.value = 'create';
    editingDevice.value = null;
    resetDeviceForm();
    isDeviceDialogOpen.value = true;
};

const openEditDialog = (device: DeviceItem): void => {
    deviceDialogMode.value = 'edit';
    editingDevice.value = device;
    deviceName.value = device.name;
    deviceLocation.value = device.location ?? '';
    deviceType.value = device.type;
    deviceStatus.value = device.status;
    isDeviceDialogOpen.value = true;
};

watch(isDeviceDialogOpen, (isOpen) => {
    if (!isOpen) {
        editingDevice.value = null;
        deviceDialogMode.value = 'create';
        resetDeviceForm();
    }
});

const formatDate = (timestamp: string | null, fallback = 'Fecha desconocida'): string => {
    if (!timestamp) {
        return fallback;
    }

    return dateFormatter.format(new Date(timestamp));
};

const locationLabel = (location: string | null): string => {
    if (!location) {
        return 'Sin ubicación asignada';
    }

    return location;
};

const handleDeviceSaved = (): void => {
    resetDeviceForm();
    editingDevice.value = null;
    deviceDialogMode.value = 'create';
    isDeviceDialogOpen.value = false;
};

const setStatusUpdating = (deviceId: number, value: boolean): void => {
    statusUpdating[deviceId] = value;
};

const handleStatusToggle = (device: DeviceItem): void => {
    const nextStatus: DeviceStatus = device.status === 'on' ? 'off' : 'on';
    setStatusUpdating(device.id, true);

    router.patch(DeviceController.update.url({ device: device.id }), {
        name: device.name,
        location: device.location ?? '',
        type: device.type,
        status: nextStatus,
    }, {
        preserveScroll: true,
        onFinish: () => {
            setStatusUpdating(device.id, false);
        },
    });
};

const statusButtonLabel = (device: DeviceItem): string =>
    device.status === 'on' ? 'Apagar' : 'Encender';

const dialogTitle = computed(() =>
    isEditingDevice.value ? 'Editar dispositivo' : 'Agregar dispositivo'
);
const dialogDescription = computed(() =>
    isEditingDevice.value
        ? 'Actualiza los datos del dispositivo seleccionado.'
        : 'Ingresa el nombre del dispositivo que deseas agregar.'
);
const submitButtonLabel = computed(() =>
    isEditingDevice.value ? 'Guardar cambios' : 'Agregar'
);

const deviceFormDefinition = computed(() => {
    if (isEditingDevice.value && editingDevice.value) {
        return DeviceController.update.form({ device: editingDevice.value.id });
    }

    return DeviceController.store.form();
});
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

                <Dialog :open="isDeviceDialogOpen" @update:open="isDeviceDialogOpen = $event">
                    <DialogTrigger as-child>
                        <Button size="lg" @click="openCreateDialog">
                            <IconPlus class="size-4" />
                            Agregar dispositivo
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-md">
                        <DialogHeader class="space-y-2">
                            <DialogTitle>{{ dialogTitle }}</DialogTitle>
                            <DialogDescription>
                                {{ dialogDescription }}
                            </DialogDescription>
                        </DialogHeader>

                        <Form
                            v-bind="deviceFormDefinition"
                            reset-on-success
                            @success="handleDeviceSaved"
                            class="space-y-6"
                            v-slot="{ errors, processing }"
                        >
                            <div
                                v-if="isEditingDevice && editingDevice"
                                class="space-y-1 rounded-lg border border-border/70 bg-muted/30 p-3 text-xs text-muted-foreground"
                            >
                                <p>
                                    <span class="font-semibold text-foreground">Agregado el:</span>
                                    {{ formatDate(editingDevice.created_at) }}
                                </p>
                                <p>
                                    <span class="font-semibold text-foreground">Última modificación:</span>
                                    {{ formatDate(editingDevice.updated_at, 'Sin cambios registrados') }}
                                </p>
                            </div>

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
                                <div class="grid gap-2">
                                    <Label for="device-status">Estado</Label>
                                    <select
                                        id="device-status"
                                        v-model="deviceStatus"
                                        name="status"
                                        class="border-input focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring md:text-sm"
                                    >
                                        <option value="on">Encendido</option>
                                        <option value="off">Apagado</option>
                                    </select>
                                    <InputError :message="errors.status" />
                                </div>
                            </div>

                            <DialogFooter class="gap-2">
                                <DialogClose as-child>
                                    <Button type="button" variant="secondary">Cancelar</Button>
                                </DialogClose>
                                <Button type="submit" :disabled="processing">
                                    {{ submitButtonLabel }}
                                </Button>
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
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge variant="secondary">
                                    <IconBulb class="size-3.5" />
                                    {{ deviceTypeLabels[device.type] }}
                                </Badge>
                                <Badge :variant="device.status === 'on' ? 'default' : 'outline'">
                                    {{ statusLabels[device.status] }}
                                </Badge>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                class="shrink-0"
                                @click="openEditDialog(device)"
                            >
                                <IconPencil class="size-4" />
                                Editar
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm text-muted-foreground">
                        <p class="flex items-center gap-2">
                            <IconMapPin class="size-4 text-foreground/70" />
                            <span>{{ locationLabel(device.location) }}</span>
                        </p>
                        <div
                            class="flex flex-col gap-3 rounded-lg border border-border/60 p-3 text-foreground/80 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex items-center gap-2">
                                <IconBulb class="size-4 text-foreground/70" />
                                <span>{{ statusLabels[device.status] }}</span>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="w-full sm:w-auto"
                                :disabled="statusUpdating[device.id]"
                                @click="handleStatusToggle(device)"
                            >
                                <Spinner v-if="statusUpdating[device.id]" class="size-4" />
                                <span v-else>{{ statusButtonLabel(device) }}</span>
                            </Button>
                        </div>
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
                <Button size="lg" @click="openCreateDialog">
                    <IconPlus class="size-4" />
                    Agregar dispositivo
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
