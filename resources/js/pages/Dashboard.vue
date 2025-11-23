<script setup lang="ts">
import AreaController from '@/actions/App/Http/Controllers/AreaController';
import DeviceController from '@/actions/App/Http/Controllers/DeviceController';
import LocationController from '@/actions/App/Http/Controllers/LocationController';
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
    location_id: number | null;
    area_id: number | null;
    type: DeviceType;
    status: DeviceStatus;
    brightness: number;
    created_at: string | null;
    updated_at: string | null;
}

interface AreaItem {
    id: number;
    name: string;
    location_id: number;
}

interface LocationItem {
    id: number;
    name: string;
    areas: AreaItem[];
}

interface DashboardFilters {
    location: number | 'none' | null;
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
const selectedDeviceLocationId = ref<number | ''>('');
const selectedDeviceAreaId = ref<number | ''>('');
const isEditingDevice = computed(() => deviceDialogMode.value === 'edit');
const showBrightnessControl = computed(() => deviceType.value === 'dimmer');

const deviceTypeLabels: Record<DeviceType, string> = {
    switch: 'Interruptor',
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
const brightnessUpdating = reactive<Record<number, boolean>>({});
const brightnessPreview = reactive<Record<number, number | undefined>>({});
const isLocationDialogOpen = ref(false);
const newLocationName = ref('');
const isAreaDialogOpen = ref(false);
const newAreaName = ref('');
const newAreaLocationId = ref<number | ''>('');

const areasForSelectedLocation = computed(() => {
    if (!selectedDeviceLocationId.value) {
        return [];
    }

    const location = availableLocations.value.find((item) => item.id === Number(selectedDeviceLocationId.value));

    return location?.areas ?? [];
});

const areasForFilter = computed(() => {
    if (typeof locationFilter.value !== 'number') {
        return [];
    }

    const location = availableLocations.value.find((item) => item.id === locationFilter.value);

    return location?.areas ?? [];
});

const resetDeviceForm = (): void => {
    deviceName.value = '';
    deviceType.value = defaultDeviceType;
    deviceStatus.value = defaultDeviceStatus;
    deviceBrightness.value = defaultSwitchBrightness;
    selectedDeviceLocationId.value = '';
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
    selectedDeviceLocationId.value = device.location_id ?? '';
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

watch(selectedDeviceLocationId, (value) => {
    if (!value) {
        selectedDeviceAreaId.value = '';

        return;
    }

    const numericValue = Number(value);
    const selectedLocation = availableLocations.value.find((location) => location.id === numericValue);

    if (!selectedLocation) {
        selectedDeviceAreaId.value = '';

        return;
    }

    const hasCurrentArea = selectedLocation.areas.some((area) => area.id === Number(selectedDeviceAreaId.value));

    if (!hasCurrentArea) {
        selectedDeviceAreaId.value = '';
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

const handleLocationStored = (): void => {
    newLocationName.value = '';
    isLocationDialogOpen.value = false;
};

const handleAreaStored = (): void => {
    newAreaName.value = '';
    newAreaLocationId.value = '';
    isAreaDialogOpen.value = false;
};

const applyFilters = (locationValue: number | 'none' | null, areaValue: number | null): void => {
    const query: Record<string, string | number> =
        locationValue === null
            ? {}
            : {
                  location: locationValue === 'none' ? 'none' : locationValue,
              };

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

const handleLocationFilterChange = (value: number | 'none' | null): void => {
    applyFilters(value, null);
};

const handleAreaFilterChange = (value: number | null): void => {
    const currentLocation = locationFilter.value;

    if (typeof currentLocation !== 'number') {
        return;
    }

    applyFilters(currentLocation, value);
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

                <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center">
                    <div class="flex flex-col gap-1 text-sm text-muted-foreground">
                        <Label for="location-filter" class="font-medium text-foreground">Filtrar por ubicación</Label>
                        <select
                            id="location-filter"
                            class="border-input focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid-border-destructive flex h-9 min-w-[220px] rounded-md border bg-transparent px-3 py-1 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring md:text-sm"
                            :value="locationFilter ?? ''"
                            @change="
                                handleLocationFilterChange(
                                    ($event.target as HTMLSelectElement).value === ''
                                        ? null
                                        : ($event.target as HTMLSelectElement).value === 'none'
                                            ? 'none'
                                            : Number(($event.target as HTMLSelectElement).value)
                                )
                            "
                        >
                            <option value="">Todas las ubicaciones</option>
                            <option value="none">Sin ubicación</option>
                            <option
                                v-for="location in availableLocations"
                                :key="location.id"
                                :value="location.id"
                            >
                                {{ location.name }}
                            </option>
                        </select>
                    </div>

                    <div class="flex flex-col gap-1 text-sm text-muted-foreground">
                        <Label for="area-filter" class="font-medium text-foreground">Filtrar por área</Label>
                        <select
                            id="area-filter"
                            class="border-input focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid-border-destructive flex h-9 min-w-[220px] rounded-md border bg-transparent px-3 py-1 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring md:text-sm disabled:opacity-70"
                            :disabled="typeof locationFilter !== 'number'"
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
                                {{ area.name }}
                            </option>
                        </select>
                    </div>

                    <Dialog :open="isLocationDialogOpen" @update:open="isLocationDialogOpen = $event">
                        <DialogTrigger as-child>
                            <Button variant="outline" class="sm:w-auto" @click="isLocationDialogOpen = true">
                                <IconPlus class="size-4" />
                                Nueva ubicación
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-sm">
                            <DialogHeader class="space-y-2">
                                <DialogTitle>Registrar ubicación</DialogTitle>
                                <DialogDescription>
                                    Crea ubicaciones principales (por ejemplo: Casa, Oficina).
                                </DialogDescription>
                            </DialogHeader>
                            <Form
                                v-bind="LocationController.store.form()"
                                reset-on-success
                                @success="handleLocationStored"
                                class="space-y-4"
                                v-slot="{ errors, processing }"
                            >
                                <div class="grid gap-2">
                                    <Label for="new-location-name">Nombre</Label>
                                    <Input
                                        id="new-location-name"
                                        v-model="newLocationName"
                                        name="name"
                                        autocomplete="off"
                                        placeholder="Ej. Casa principal"
                                        :aria-invalid="Boolean(errors.name)"
                                    />
                                    <InputError :message="errors.name" />
                                </div>

                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button type="button" variant="secondary">Cancelar</Button>
                                    </DialogClose>
                                    <Button type="submit" :disabled="processing">
                                        Guardar
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>

                    <Dialog :open="isDeviceDialogOpen" @update:open="isDeviceDialogOpen = $event">
                        <DialogTrigger as-child>
                            <Button size="lg" class="sm:w-auto" @click="openCreateDialog">
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
                                    <Label for="device-location-id">Ubicación guardada</Label>
                                    <select
                                        id="device-location-id"
                                        v-model="selectedDeviceLocationId"
                                        name="location_id"
                                        class="border-input focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid-border-destructive flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring md:text-sm"
                                    >
                                        <option value="">Sin ubicación</option>
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
                                    <div class="flex items-center justify-between">
                                        <Label for="device-area">Área dentro de la ubicación</Label>
                                        <Dialog :open="isAreaDialogOpen" @update:open="isAreaDialogOpen = $event">
                                            <DialogTrigger as-child>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    class="h-8 px-2 text-xs"
                                                    @click="
                                                        () => {
                                                            newAreaLocationId.value = selectedDeviceLocationId.value
                                                                ? Number(selectedDeviceLocationId.value)
                                                                : '';
                                                            isAreaDialogOpen.value = true;
                                                        }
                                                    "
                                                >
                                                    Nueva área
                                                </Button>
                                            </DialogTrigger>
                                            <DialogContent class="sm:max-w-sm">
                                                <DialogHeader class="space-y-2">
                                                    <DialogTitle>Registrar área</DialogTitle>
                                                    <DialogDescription>
                                                        Selecciona una ubicación y asigna un nombre, por ejemplo &quot;Sala principal&quot;.
                                                    </DialogDescription>
                                                </DialogHeader>
                                                <Form
                                                    v-bind="AreaController.store.form()"
                                                    reset-on-success
                                                    @success="handleAreaStored"
                                                    class="space-y-4"
                                                    v-slot="{ errors: areaErrors, processing: areaProcessing }"
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
                                                        <InputError :message="areaErrors.location_id" />
                                                    </div>

                                                    <div class="grid gap-2">
                                                        <Label for="new-area-name">Nombre del área</Label>
                                                        <Input
                                                            id="new-area-name"
                                                            v-model="newAreaName"
                                                            name="name"
                                                            autocomplete="off"
                                                            placeholder="Ej. Sala principal"
                                                            :aria-invalid="Boolean(areaErrors.name)"
                                                        />
                                                        <InputError :message="areaErrors.name" />
                                                    </div>

                                                    <DialogFooter class="gap-2">
                                                        <DialogClose as-child>
                                                            <Button type="button" variant="secondary">Cancelar</Button>
                                                        </DialogClose>
                                                        <Button type="submit" :disabled="areaProcessing">
                                                            Guardar área
                                                        </Button>
                                                    </DialogFooter>
                                                </Form>
                                            </DialogContent>
                                        </Dialog>
                                    </div>
                                    <select
                                        id="device-area"
                                        v-model="selectedDeviceAreaId"
                                        name="area_id"
                                        class="border-input focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid-border-destructive flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring md:text-sm"
                                        :disabled="!selectedDeviceLocationId"
                                    >
                                        <option value="">
                                            {{
                                                selectedDeviceLocationId
                                                    ? 'Selecciona un área'
                                                    : 'Elige primero una ubicación'
                                            }}
                                        </option>
                                        <option
                                            v-for="area in areasForSelectedLocation"
                                            :key="area.id"
                                            :value="area.id"
                                        >
                                            {{ area.name }}
                                        </option>
                                    </select>
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

                <div v-if="hasDevices" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
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
                                <Badge variant="secondary">
                                    <IconBulb class="size-3.5" />
                                    {{ deviceTypeLabels[device.type] }}
                                </Badge>
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
                                    <span v-else>{{ statusButtonLabel(device) }}</span>
                                </Button>
                            </div>
                            <div
                                v-if="device.type === 'dimmer'"
                                class="space-y-3 rounded-lg border border-border/60 p-3 text-foreground"
                            >
                                <div class="flex items-center justify-between text-xs uppercase tracking-wide text-muted-foreground/80">
                                    <span>Nivel de potencia</span>
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
    </AppLayout>
</template>
