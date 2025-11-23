<script setup lang="ts">
import AreaController from '@/actions/App/Http/Controllers/AreaController';
import DeviceController from '@/actions/App/Http/Controllers/DeviceController';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Form, Head, router } from '@inertiajs/vue3';
import {
    IconBulb,
    IconCopy,
    IconDotsVertical,
    IconMapPin,
    IconPencil,
    IconPlus,
    IconPower,
    IconSettings,
    IconSun,
    IconTrash,
} from '@tabler/icons-vue';
import { computed, nextTick, reactive, ref, watch } from 'vue';

type DeviceType = 'switch' | 'dimmer';
type DeviceStatus = 'on' | 'off';

interface DeviceItem {
    id: number;
    name: string;
    location: string | null;
    location_id: number | null;
    area_id: number | null;
    type: DeviceType;
    status: DeviceStatus;
    brightness: number;
    created_at: string | null;
    updated_at: string | null;
    webhook_url: string;
}

interface AreaItem {
    id: number;
    name: string;
    location_id: number;
}

interface AreaOption {
    id: number;
    name: string;
    locationName: string;
}

interface LocationItem {
    id: number;
    name: string;
    areas: AreaItem[];
}

interface DashboardFilters {
    location: number | null;
    area: number | null;
}

interface Props {
    devices: DeviceItem[];
    locations: LocationItem[];
    filters: DashboardFilters;
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
const availableLocations = computed(() => props.locations);
const filters = computed(() => props.filters);
const locationFilter = computed(() => filters.value.location);
const areaFilter = computed(() => filters.value.area ?? null);
const areaOptions = computed<AreaOption[]>(() =>
    availableLocations.value.flatMap((location) =>
        location.areas.map((area) => ({
            id: area.id,
            name: area.name,
            locationName: location.name,
        }))
    )
);

const deviceDialogMode = ref<'create' | 'edit'>('create');
const isDeviceDialogOpen = ref(false);
const editingDevice = ref<DeviceItem | null>(null);
const deviceName = ref('');
const defaultDeviceType: DeviceType = 'switch';
const defaultSwitchBrightness = 100;
const defaultDimmerBrightness = 50;
const deviceType = ref<DeviceType>(defaultDeviceType);
const defaultDeviceStatus: DeviceStatus = 'off';
const deviceStatus = ref<DeviceStatus>(defaultDeviceStatus);
const deviceBrightness = ref<number>(defaultSwitchBrightness);
const selectedDeviceAreaId = ref<number | ''>('');
const isEditingDevice = computed(() => deviceDialogMode.value === 'edit');
const showBrightnessControl = computed(() => deviceType.value === 'dimmer');

const statusLabels: Record<DeviceStatus, string> = {
    on: 'Encendido',
    off: 'Apagado',
};

const dateFormatter = new Intl.DateTimeFormat('es-MX', {
    dateStyle: 'medium',
});

const statusUpdating = reactive<Record<number, boolean>>({});
const brightnessUpdating = reactive<Record<number, boolean>>({});
const brightnessPreview = reactive<Record<number, number | undefined>>({});
const isAreaDialogOpen = ref(false);
const newAreaName = ref('');
const newAreaLocationId = ref<number | ''>('');
const copyButton = ref<HTMLButtonElement | null>(null);

const areasForFilter = computed(() => areaOptions.value);

const resetDeviceForm = (): void => {
    deviceName.value = '';
    deviceType.value = defaultDeviceType;
    deviceStatus.value = defaultDeviceStatus;
    deviceBrightness.value = defaultSwitchBrightness;
    selectedDeviceAreaId.value = '';
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
    deviceType.value = device.type;
    deviceStatus.value = device.status;
    deviceBrightness.value = device.brightness;
    selectedDeviceAreaId.value = device.area_id ?? '';
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

watch(deviceType, (current, previous) => {
    if (current === 'switch') {
        deviceBrightness.value = defaultSwitchBrightness;

        return;
    }

    if (current === 'dimmer' && previous === 'switch') {
        deviceBrightness.value = defaultDimmerBrightness;
    }
});

const setStatusUpdating = (deviceId: number, value: boolean): void => {
    statusUpdating[deviceId] = value;
};

const currentDeviceBrightness = (device: DeviceItem): number => {
    const preview = brightnessPreview[device.id];

    if (preview !== undefined) {
        return preview;
    }

    if (typeof device.brightness === 'number') {
        return device.brightness;
    }

    return device.type === 'dimmer' ? defaultDimmerBrightness : defaultSwitchBrightness;
};

const handleStatusToggle = (device: DeviceItem): void => {
    const nextStatus: DeviceStatus = device.status === 'on' ? 'off' : 'on';
    setStatusUpdating(device.id, true);

    router.patch(DeviceController.update.url({ device: device.id }), {
        name: device.name,
        location: device.location ?? '',
        area_id: device.area_id,
        type: device.type,
        status: nextStatus,
        brightness: currentDeviceBrightness(device),
    }, {
        preserveScroll: true,
        onFinish: () => {
            setStatusUpdating(device.id, false);
        },
    });
};

const statusButtonLabel = (device: DeviceItem): string =>
    device.status === 'on' ? 'Apagar' : 'Encender';

const deviceBrightnessLabel = computed(() => {
    if (showBrightnessControl.value) {
        return deviceBrightness.value ?? defaultDimmerBrightness;
    }

    return defaultSwitchBrightness;
});

const setBrightnessUpdating = (deviceId: number, value: boolean): void => {
    brightnessUpdating[deviceId] = value;
};

const handleBrightnessInput = (device: DeviceItem, value: string | number): void => {
    const parsed = Number(value);

    if (Number.isNaN(parsed)) {
        return;
    }

    brightnessPreview[device.id] = parsed;
};

const handleBrightnessChange = (device: DeviceItem, value: string | number): void => {
    const parsed = Number(value);

    if (Number.isNaN(parsed)) {
        return;
    }

    brightnessPreview[device.id] = parsed;
    setBrightnessUpdating(device.id, true);

    router.patch(DeviceController.update.url({ device: device.id }), {
        name: device.name,
        location: device.location ?? '',
        area_id: device.area_id,
        type: device.type,
        status: device.status,
        brightness: parsed,
    }, {
        preserveScroll: true,
        onFinish: () => {
            setBrightnessUpdating(device.id, false);
            delete brightnessPreview[device.id];
        },
    });
};

const hideConfirmationKeyword = 'confirmo';
const isHideDialogOpen = ref(false);
const hideConfirmationInput = ref('');
const deviceToHide = ref<DeviceItem | null>(null);
const isHideConfirmationValid = computed(
    () => hideConfirmationInput.value.trim().toLowerCase() === hideConfirmationKeyword
);
const isAdvancedDialogOpen = ref(false);
const advancedDevice = ref<DeviceItem | null>(null);

const prepareHideDevice = (device: DeviceItem): void => {
    deviceToHide.value = device;
    hideConfirmationInput.value = '';
    isHideDialogOpen.value = true;
};

const closeHideDialog = (): void => {
    isHideDialogOpen.value = false;
    deviceToHide.value = null;
    hideConfirmationInput.value = '';
};

const confirmHideDevice = (): void => {
    if (!deviceToHide.value || !isHideConfirmationValid.value) {
        return;
    }

    router.delete(
        DeviceController.destroy.url({ device: deviceToHide.value.id }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: closeHideDialog,
        }
    );
};

const openAdvancedDialog = (device: DeviceItem): void => {
    advancedDevice.value = device;
    isAdvancedDialogOpen.value = true;
};

const copyWebhookUrl = async (): Promise<void> => {
    if (!advancedDevice.value) {
        return;
    }

    await navigator.clipboard.writeText(advancedDevice.value.webhook_url);
};

watch(isAdvancedDialogOpen, (isOpen) => {
    if (!isOpen) {
        advancedDevice.value = null;

        return;
    }

    nextTick(() => {
        copyButton.value?.focus();
    });
});


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

const handleAreaStored = (): void => {
    newAreaName.value = '';
    newAreaLocationId.value = '';
    isAreaDialogOpen.value = false;
};

const applyFilters = (locationValue: number | null, areaValue: number | null): void => {
    const query: Record<string, string | number> = locationValue === null ? {} : { location: locationValue };

    if (areaValue !== null) {
        query.area = areaValue;
    }

    router.get(
        dashboard({ query }).url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
        }
    );
};

const handleAreaFilterChange = (value: number | null): void => {
    applyFilters(locationFilter.value, value);
};
</script>

<template>
    <Head title="Panel" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-8 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold leading-tight">Tus dispositivos</h1>
                    <p class="text-sm text-muted-foreground">
                        Consulta y administra los dispositivos inteligentes de tu hogar.
                    </p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                    <div class="flex flex-col gap-1 text-sm text-muted-foreground">
                        <Label for="area-filter" class="font-medium text-foreground">Filtrar por área</Label>
                        <select
                            id="area-filter"
                            class="border-input focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid-border-destructive flex h-9 min-w-[220px] rounded-md border bg-transparent px-3 py-1 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring md:text-sm"
                            :value="areaFilter ?? ''"
                            @change="
                                handleAreaFilterChange(
                                    ($event.target as HTMLSelectElement).value === ''
                                        ? null
                                        : Number(($event.target as HTMLSelectElement).value)
                                )
                            "
                        >
                            <option value="">Todas las áreas</option>
                            <option
                                v-for="area in areasForFilter"
                                :key="area.id"
                                :value="area.id"
                            >
                                {{ area.name }} — {{ area.locationName }}
                            </option>
                        </select>
                    </div>

                    <div class="flex flex-wrap gap-2 sm:justify-end">
                        <Dialog :open="isAreaDialogOpen" @update:open="isAreaDialogOpen = $event">
                        <DialogTrigger as-child>
                            <Button size="lg" variant="outline" class="h-10 px-6 gap-2 sm:w-auto" @click="isAreaDialogOpen = true">
                                    <IconPlus class="size-4" />
                                    Nueva área
                                </Button>
                            </DialogTrigger>
                            <DialogContent class="sm:max-w-sm">
                                <DialogHeader class="space-y-2">
                                    <DialogTitle>Registrar área</DialogTitle>
                                    <DialogDescription>
                                        Primero elige una ubicación y luego asigna un nombre descriptivo.
                                    </DialogDescription>
                                </DialogHeader>
                                <Form
                                    v-bind="AreaController.store.form()"
                                    reset-on-success
                                    @success="handleAreaStored"
                                    class="space-y-4"
                                    v-slot="{ errors, processing }"
                                >
                                    <div class="grid gap-2">
                                        <Label for="new-area-location">Ubicación</Label>
                                        <select
                                            id="new-area-location"
                                            v-model="newAreaLocationId"
                                            name="location_id"
                                            class="border-input focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid-border-destructive flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring md:text-sm"
                                        >
                                            <option value="">Selecciona una ubicación</option>
                                            <option
                                                v-for="location in availableLocations"
                                                :key="location.id"
                                                :value="location.id"
                                            >
                                                {{ location.name }}
                                            </option>
                                        </select>
                                        <InputError :message="errors.location_id" />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="new-area-name">Nombre del área</Label>
                                        <Input
                                            id="new-area-name"
                                            v-model="newAreaName"
                                            name="name"
                                            autocomplete="off"
                                            placeholder="Ej. Sala principal"
                                            :aria-invalid="Boolean(errors.name)"
                                        />
                                        <InputError :message="errors.name" />
                                    </div>

                                    <DialogFooter class="gap-2">
                                        <DialogClose as-child>
                                            <Button type="button" variant="secondary">Cancelar</Button>
                                        </DialogClose>
                                        <Button type="submit" :disabled="processing">
                                            Guardar área
                                        </Button>
                                    </DialogFooter>
                                </Form>
                            </DialogContent>
                        </Dialog>

                        <Dialog :open="isDeviceDialogOpen" @update:open="isDeviceDialogOpen = $event">
                        <DialogTrigger as-child>
                            <Button size="lg" class="h-10 px-6 gap-2 sm:w-auto" @click="openCreateDialog">
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
                                    <div class="flex items-center justify-between">
                                        <Label for="device-area">Área registrada</Label>
                                        <Button
                                            type="button"
                                            variant="link"
                                            size="sm"
                                            class="h-auto p-0 text-xs font-medium"
                                            @click="isAreaDialogOpen = true"
                                        >
                                            Crear nueva área
                                        </Button>
                                    </div>
                                    <select
                                        id="device-area"
                                        v-model="selectedDeviceAreaId"
                                        name="area_id"
                                        class="border-input focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid-border-destructive flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring md:text-sm disabled:opacity-70"
                                        :disabled="!areaOptions.length"
                                    >
                                        <option value="">Selecciona un área</option>
                                        <option
                                            v-for="area in areaOptions"
                                            :key="area.id"
                                            :value="area.id"
                                        >
                                            {{ area.name }} — {{ area.locationName }}
                                        </option>
                                    </select>
                                    <p
                                        v-if="!areaOptions.length"
                                        class="text-xs text-muted-foreground"
                                    >
                                        Crea un área desde el botón &quot;Nueva área&quot; antes de continuar.
                                    </p>
                                    <InputError :message="errors.area_id" />
                                </div>

                                    <div class="grid gap-2">
                                        <Label for="device-type">Tipo de dispositivo</Label>
                                        <select
                                            id="device-type"
                                            v-model="deviceType"
                                            name="type"
                                            class="border-input focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid-border-destructive flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring md:text-sm"
                                        >
                                            <option value="switch">Encendido / Apagado</option>
                                            <option value="dimmer">Regulable</option>
                                        </select>
                                        <InputError :message="errors.type" />
                                    </div>

                                <div
                                    v-if="deviceDialogMode === 'create'"
                                    class="grid gap-2"
                                >
                                    <Label for="device-status">Estado</Label>
                                    <select
                                        id="device-status"
                                        v-model="deviceStatus"
                                        name="status"
                                        class="border-input focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid-border-destructive flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring md:text-sm"
                                    >
                                        <option value="on">Encendido</option>
                                        <option value="off">Apagado</option>
                                    </select>
                                    <InputError :message="errors.status" />
                                </div>
                                <input
                                    v-else
                                    type="hidden"
                                    name="status"
                                    :value="deviceStatus"
                                />

                                <div
                                    v-if="showBrightnessControl && deviceDialogMode === 'create'"
                                    class="grid gap-2"
                                >
                                    <div class="flex items-center justify-between">
                                        <Label for="device-brightness">Nivel de potencia</Label>
                                        <span class="text-sm text-muted-foreground"
                                                >{{ deviceBrightnessLabel }}%</span
                                            >
                                        </div>
                                        <input
                                            id="device-brightness"
                                            v-model.number="deviceBrightness"
                                            type="range"
                                            min="0"
                                            max="100"
                                            step="5"
                                            name="brightness"
                                            class="accent-primary h-2 w-full cursor-pointer appearance-none rounded-full bg-secondary"
                                        />
                                        <div class="flex justify-between text-xs text-muted-foreground">
                                            <span>0%</span>
                                            <span>50%</span>
                                            <span>100%</span>
                                        </div>
                                        <InputError :message="errors.brightness" />
                                    </div>
                                    <input
                                        v-else
                                        type="hidden"
                                        name="brightness"
                                        :value="deviceBrightness"
                                    />
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
            </div>

                <div
                    v-if="hasDevices"
                    class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 w-full"
                >
                    <Card v-for="device in devices" :key="device.id" class="border-border/70">
                        <CardHeader class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="space-y-1">
                                <CardTitle class="text-lg font-semibold">{{ device.name }}</CardTitle>
                                <CardDescription class="flex items-center gap-2 text-sm">
                                    <IconMapPin class="size-4 text-foreground/70" />
                                    <span>{{ locationLabel(device.location) }}</span>
                                </CardDescription>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <DropdownMenu>
                                    <DropdownMenuTrigger :as-child="true">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="gap-2 shrink-0"
                                        >
                                            <IconDotsVertical class="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" class="w-40">
                                        <DropdownMenuItem @click="openEditDialog(device)">
                                            <IconPencil class="size-4" />
                                            Editar
                                        </DropdownMenuItem>
                                        <DropdownMenuItem @click="openAdvancedDialog(device)">
                                            <IconSettings class="size-4" />
                                            Avanzado
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            @click="prepareHideDevice(device)"
                                        >
                                            <IconTrash class="size-4" />
                                            Eliminar
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-4 text-sm text-muted-foreground">
                            <div
                                class="flex flex-col gap-3 rounded-lg border border-border/60 p-3 text-foreground/80 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="flex flex-col">
                                    <span class="text-xs uppercase tracking-wide text-muted-foreground/80">
                                        Estado actual
                                    </span>
                                    <span class="text-base font-medium text-foreground">
                                        {{ statusLabels[device.status] }}
                                    </span>
                                </div>
                                <Button
                                    type="button"
                                    :variant="device.status === 'on' ? 'outline' : 'default'"
                                    size="sm"
                                    class="w-full sm:w-auto"
                                    :disabled="statusUpdating[device.id]"
                                    @click="handleStatusToggle(device)"
                                >
                                    <Spinner v-if="statusUpdating[device.id]" class="size-4" />
                                    <template v-else>
                                        <IconPower class="size-4" />
                                        <span>{{ statusButtonLabel(device) }}</span>
                                    </template>
                                </Button>
                            </div>
                            <div
                                v-if="device.type === 'dimmer'"
                                class="space-y-3 rounded-lg border border-border/60 p-3 text-foreground"
                            >
                                <div class="flex items-center justify-between text-xs uppercase tracking-wide text-muted-foreground/80">
                                    <span class="inline-flex items-center gap-1 text-muted-foreground">
                                        <IconSun class="size-4" />
                                        Nivel de potencia
                                    </span>
                                    <span class="text-base font-semibold text-foreground">
                                        {{ currentDeviceBrightness(device) }}%
                                    </span>
                                </div>
                                <input
                                    type="range"
                                    min="0"
                                    max="100"
                                    step="5"
                                    :value="currentDeviceBrightness(device)"
                                    class="accent-primary h-2 w-full cursor-pointer appearance-none rounded-full bg-secondary disabled:cursor-not-allowed"
                                    :disabled="brightnessUpdating[device.id]"
                                    @input="handleBrightnessInput(device, $event.target.value)"
                                    @change="handleBrightnessChange(device, $event.target.value)"
                                />
                                <div class="flex items-center justify-between text-xs text-muted-foreground">
                                    <span>Apagado</span>
                                    <span>Máximo</span>
                                </div>
                                <p v-if="brightnessUpdating[device.id]" class="text-xs text-muted-foreground">
                                    Actualizando potencia...
                                </p>
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
        </div>
        <Dialog :open="isAdvancedDialogOpen" @update:open="isAdvancedDialogOpen = $event">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader class="space-y-2">
                    <DialogTitle>Opciones avanzadas</DialogTitle>
                    <DialogDescription>
                        Comparte este enlace con tu dispositivo para leer su estado actual.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="advancedDevice" class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="advanced-webhook-url">URL del webhook</Label>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <Input
                                id="advanced-webhook-url"
                                :model-value="advancedDevice.webhook_url"
                                readonly
                                class="w-full"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                class="w-full sm:w-auto"
                                @click="copyWebhookUrl"
                                ref="copyButton"
                            >
                                <IconCopy class="size-4" />
                                Copiar
                            </Button>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Tus dispositivos pueden usar esta URL para consultar el estado y la potencia.
                        </p>
                    </div>

                    <div class="space-y-3 rounded-lg border border-border/60 bg-muted/30 p-3 text-sm text-foreground">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center gap-1 text-muted-foreground">
                                <IconPower class="size-4" />
                                Estado
                            </span>
                            <span class="font-semibold text-foreground">
                                {{ statusLabels[advancedDevice.status] }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center gap-1 text-muted-foreground">
                                <IconSun class="size-4" />
                                Potencia
                            </span>
                            <span class="font-semibold text-foreground">
                                {{
                                    advancedDevice.type === 'dimmer'
                                        ? `${advancedDevice.brightness}%`
                                        : '100%'
                                }}
                            </span>
                        </div>
                    </div>
                </div>

                <DialogFooter class="justify-end gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">Cerrar</Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
        <Dialog :open="isHideDialogOpen" @update:open="isHideDialogOpen = $event">
            <DialogContent class="sm:max-w-xs">
                <DialogHeader class="space-y-2">
                    <DialogTitle>Confirmar eliminación</DialogTitle>
                    <DialogDescription>
                        Escribe <strong>&ldquo;confirmo&rdquo;</strong> para marcar este dispositivo como eliminado.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="hide-device-confirmation">Confirmación</Label>
                    <Input
                        id="hide-device-confirmation"
                        v-model="hideConfirmationInput"
                        placeholder="confirmo"
                        autocomplete="off"
                    />
                </div>
                <DialogFooter class="flex w-full justify-end gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary" @click="closeHideDialog">Cancelar</Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="!isHideConfirmationValid"
                        @click="confirmHideDevice"
                    >
                        Eliminar dispositivo
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
