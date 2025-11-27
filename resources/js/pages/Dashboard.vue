<script setup lang="ts">
import AreaController from '@/actions/App/Http/Controllers/AreaController';
import DeviceController from '@/actions/App/Http/Controllers/DeviceController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    IconBulb,
    IconCopy,
    IconDotsVertical,
    IconMapPin,
    IconMicrophone,
    IconPencil,
    IconPlus,
    IconPower,
    IconSettings,
    IconSun,
    IconTrash,
    IconVolume,
    IconVolumeOff,
    IconX,
} from '@tabler/icons-vue';
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';

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
        title: 'Panel de control',
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
        })),
    ),
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

const formatDate = (
    timestamp: string | null,
    fallback = 'Fecha desconocida',
): string => {
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

    return device.type === 'dimmer'
        ? defaultDimmerBrightness
        : defaultSwitchBrightness;
};

const handleStatusToggle = (device: DeviceItem): void => {
    const nextStatus: DeviceStatus = device.status === 'on' ? 'off' : 'on';
    setStatusUpdating(device.id, true);

    router.patch(
        DeviceController.update.url({ device: device.id }),
        {
            name: device.name,
            location: device.location ?? '',
            area_id: device.area_id,
            type: device.type,
            status: nextStatus,
            brightness: currentDeviceBrightness(device),
        },
        {
            preserveScroll: true,
            onFinish: () => {
                setStatusUpdating(device.id, false);
            },
        },
    );
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

const handleBrightnessInput = (
    device: DeviceItem,
    value: string | number,
): void => {
    const parsed = Number(value);

    if (Number.isNaN(parsed)) {
        return;
    }

    brightnessPreview[device.id] = parsed;
};

const handleBrightnessChange = (
    device: DeviceItem,
    value: string | number,
): void => {
    const parsed = Number(value);

    if (Number.isNaN(parsed)) {
        return;
    }

    brightnessPreview[device.id] = parsed;
    setBrightnessUpdating(device.id, true);

    router.patch(
        DeviceController.update.url({ device: device.id }),
        {
            name: device.name,
            location: device.location ?? '',
            area_id: device.area_id,
            type: device.type,
            status: device.status,
            brightness: parsed,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                setBrightnessUpdating(device.id, false);
                delete brightnessPreview[device.id];
            },
        },
    );
};

const hideConfirmationKeyword = 'confirmo';
const isHideDialogOpen = ref(false);
const hideConfirmationInput = ref('');
const deviceToHide = ref<DeviceItem | null>(null);
const isHideConfirmationValid = computed(
    () =>
        hideConfirmationInput.value.trim().toLowerCase() ===
        hideConfirmationKeyword,
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
        },
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
    isEditingDevice.value ? 'Editar dispositivo' : 'Agregar dispositivo',
);
const dialogDescription = computed(() =>
    isEditingDevice.value
        ? 'Actualiza los datos del dispositivo seleccionado.'
        : 'Ingresa el nombre del dispositivo que deseas agregar.',
);
const submitButtonLabel = computed(() =>
    isEditingDevice.value ? 'Guardar cambios' : 'Agregar',
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

const applyFilters = (
    locationValue: number | null,
    areaValue: number | null,
): void => {
    const query: Record<string, string | number> =
        locationValue === null ? {} : { location: locationValue };

    if (areaValue !== null) {
        query.area = areaValue;
    }

    router.get(
        dashboard({ query }).url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const handleAreaFilterChange = (value: number | null): void => {
    applyFilters(locationFilter.value, value);
};

const voiceWaveDelays = [0, 0.15, 0.3, 0.45];
const voiceModeActive = ref(false);
const voiceModeResponseActive = ref(false);
const voiceMuted = ref(false);
const voiceModeButtonLabel = computed(() =>
    voiceModeActive.value ? 'Modo de voz activo' : 'Activar modo de voz',
);
const voiceModeStatusText = computed(() =>
    voiceModeActive.value ? 'Modo de voz activo' : 'Modo de voz inactivo',
);
const voiceMuteButtonLabel = computed(() =>
    voiceMuted.value ? 'Micrófono silenciado' : 'Silenciar micrófono',
);
const voiceMuteIcon = computed(() =>
    voiceMuted.value ? IconVolumeOff : IconVolume,
);
const responseText = ref<string>('');

const toggleVoiceMode = (): void => {
    voiceModeActive.value = !voiceModeActive.value;
};

const toggleMute = (): void => {
    voiceMuted.value = !voiceMuted.value;
    // If muted, we stop sending audio chunks
};

// --- Realtime API State ---
const isConnecting = ref(false);
const isTalking = ref(false); // AI is talking
const isListening = ref(false); // User is talking (VAD detected)
const socket = ref<WebSocket | null>(null);
const audioContext = ref<AudioContext | null>(null);
const streamSource = ref<MediaStreamAudioSourceNode | null>(null);
const audioWorkletNode = ref<AudioWorkletNode | null>(null);
const assistantAudioQueue: string[] = []; // Base64 PCM chunks
let isPlayingAudio = false;
let nextStartTime = 0;

// Helper to convert Float32 (Web Audio) to Int16 (OpenAI)
const floatTo16BitPCM = (float32Array: Float32Array): Int16Array => {
    const int16Array = new Int16Array(float32Array.length);
    for (let i = 0; i < float32Array.length; i++) {
        let s = Math.max(-1, Math.min(1, float32Array[i]));
        int16Array[i] = s < 0 ? s * 0x8000 : s * 0x7fff;
    }
    return int16Array;
};

const base64EncodeAudio = (int16Array: Int16Array): string => {
    let binary = '';
    const bytes = new Uint8Array(int16Array.buffer);
    const len = bytes.byteLength;
    for (let i = 0; i < len; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return window.btoa(binary);
};

const ensureMicrophonePermission = async (): Promise<boolean> => {
    if (!navigator?.mediaDevices?.getUserMedia) {
        alert('Tu navegador no permite acceder al micrófono.');
        return false;
    }

    try {
        const status = await navigator.permissions?.query({
            name: 'microphone' as PermissionName,
        });

        if (status && status.state === 'denied') {
            alert(
                'Necesitamos permiso para usar tu micrófono. Actívalo en el navegador y vuelve a intentarlo.',
            );

            return false;
        }
    } catch (error) {
        console.warn('Permission API unavailable or failed', error);
    }

    return true;
};

const startRealtimeSession = async () => {
    isConnecting.value = true;
    responseText.value = 'Inicializando audio...';

    try {
        // 0. Init Audio Context immediately
        if (!audioContext.value) {
             audioContext.value = new (window.AudioContext || (window as any).webkitAudioContext)({ sampleRate: 24000 });
        }

        // 1. Start Microphone (User Experience)
        responseText.value = 'Solicitando acceso al micrófono...';
        const hasPermission = await ensureMicrophonePermission();

        if (!hasPermission) {
            isConnecting.value = false;
            voiceModeActive.value = false;
            return;
        }

        await startMicrophone();
        
        // 2. Get Ephemeral Token
        responseText.value = 'Autenticando...';
        const sessionResponse = await axios.post('/voice/session');
        const realtimeModel =
            sessionResponse.data?.model ??
            'gpt-realtime-mini-2025-10-06';
        const token = sessionResponse.data.client_secret.value;

        // 3. Connect WebSocket
        responseText.value = 'Conectando con OpenAI...';
        const wsUrl = `wss://api.openai.com/v1/realtime?model=${encodeURIComponent(
            realtimeModel,
        )}`;
        socket.value = new WebSocket(wsUrl, [
            'realtime', 
            `openai-insecure-api-key.${token}`, 
            'openai-beta.realtime-v1'
        ]);

        // Connection Timeout Safety
        const connectionTimeout = setTimeout(() => {
            if (isConnecting.value && socket.value?.readyState !== WebSocket.OPEN) {
                console.error('Connection timed out');
                responseText.value = 'Tiempo de espera agotado.';
                socket.value?.close();
                isConnecting.value = false;
            }
        }, 10000);

        socket.value.onopen = async () => {
            clearTimeout(connectionTimeout);
            console.log('WebSocket Connected');
            isConnecting.value = false;
            responseText.value = 'Conectado. Inicializando...';
            
            // Configure Session
            const sessionUpdate = {
                type: 'session.update',
                session: {
                    modalities: ['text', 'audio'],
                    instructions: 'Eres un asistente inteligente para el hogar. Controla dispositivos y sé breve.',
                    voice: 'verse',
                    input_audio_format: 'pcm16',
                    output_audio_format: 'pcm16',
                    turn_detection: {
                        type: 'server_vad',
                        threshold: 0.5,
                        prefix_padding_ms: 300,
                        silence_duration_ms: 500,
                    }
                }
            };
            socket.value?.send(JSON.stringify(sessionUpdate));
            
            responseText.value = 'Te escucho...';
        };

        socket.value.onmessage = async (event) => {
            try {
                const data = JSON.parse(event.data);
                // Log all events for debugging
                console.log('Realtime Event:', data.type, data);
                handleServerEvent(data);
            } catch (e) {
                console.error('Error parsing event:', e);
            }
        };

        socket.value.onerror = (err) => {
            console.error('WebSocket Error:', err);
            responseText.value = 'Error de conexión con OpenAI.';
            isConnecting.value = false;
            // Do not close immediately, let user see error
        };
        
        socket.value.onclose = (event) => {
             console.log('WebSocket Closed', event.code, event.reason);
             if (isConnecting.value || voiceModeActive.value) {
                 responseText.value = 'Conexión cerrada.';
                 isConnecting.value = false;
             }
        };

    } catch (e) {
        console.error('Failed to start session:', e);
        responseText.value = 'Error al iniciar sesión de voz.';
        isConnecting.value = false;
    }
};

const startMicrophone = async () => {
    if (!audioContext.value) return;

    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        console.log('Microphone started. Context Sample Rate:', audioContext.value.sampleRate);
        
        streamSource.value = audioContext.value.createMediaStreamSource(stream);

        const processor = audioContext.value.createScriptProcessor(4096, 1, 1);
        
        processor.onaudioprocess = (e) => {
            if (voiceMuted.value || !socket.value || socket.value.readyState !== WebSocket.OPEN) return;

            const inputData = e.inputBuffer.getChannelData(0);
            const pcm16 = floatTo16BitPCM(inputData);
            const base64 = base64EncodeAudio(pcm16);

            socket.value.send(JSON.stringify({
                type: 'input_audio_buffer.append',
                audio: base64,
            }));
        };

        streamSource.value.connect(processor);
        processor.connect(audioContext.value.destination);
    } catch (err) {
        console.error('Microphone Error:', err);
        responseText.value = 'Error al acceder al micrófono.';
        const error = err as DOMException;

        if (
            error?.name === 'NotAllowedError' ||
            error?.name === 'SecurityError'
        ) {
            alert(
                'No tenemos permiso para usar tu micrófono. Otorga acceso y vuelve a intentarlo.',
            );
        }

        voiceModeActive.value = false;
        throw err; // Re-throw to stop session start
    }
};

const handleServerEvent = async (event: any) => {
    switch (event.type) {
        case 'error':
            console.error('Server Error:', event.error);
            responseText.value = `Error: ${event.error.message}`;
            break;
        case 'session.created':
            console.log('Session Created:', event.session);
            break;
        case 'session.updated':
            console.log('Session Updated');
            break;
        case 'input_audio_buffer.speech_started':
            console.log('Speech Started');
            isListening.value = true;
            isTalking.value = false;
            interruptAudio();
            socket.value?.send(JSON.stringify({ type: 'input_audio_buffer.clear' }));
            break;
        case 'input_audio_buffer.speech_stopped':
            console.log('Speech Stopped');
            isListening.value = false;
            break;
        case 'response.audio.delta':
             if (event.delta) {
                 queueAudio(event.delta);
             }
             break;
        case 'response.text.delta':
             if (event.delta) {
                 // console.log('Text:', event.delta); 
             }
             break;
        case 'response.function_call_arguments.done':
             const callId = event.call_id;
             const name = event.name;
             const args = JSON.parse(event.arguments);
             
             console.log(`Calling Tool: ${name}`, args);
             responseText.value = `Ejecutando: ${name}...`;
             
             try {
                 const result = await axios.post('/voice/tool', {
                     name: name,
                     arguments: args
                 });
                 
                 console.log('Tool Result:', result.data);

                 socket.value?.send(JSON.stringify({
                     type: 'conversation.item.create',
                     item: {
                         type: 'function_call_output',
                         call_id: callId,
                         output: JSON.stringify(result.data),
                     }
                 }));
                 
                 socket.value?.send(JSON.stringify({
                     type: 'response.create',
                 }));
                 
                 router.reload({ only: ['devices'] });
                 responseText.value = 'Dispositivo actualizado.';
                 
             } catch (err) {
                 console.error('Tool error', err);
                 responseText.value = 'Error ejecutando acción.';
             }
             break;
        case 'response.done':
            console.log('Response Done');
            break;
    }
};

const queueAudio = (base64Data: string) => {
    assistantAudioQueue.push(base64Data);
    if (!isPlayingAudio) {
        playNextAudioChunk();
    }
};

const playNextAudioChunk = async () => {
    if (assistantAudioQueue.length === 0) {
        isPlayingAudio = false;
        isTalking.value = false;
        return;
    }

    isPlayingAudio = true;
    isTalking.value = true;
    
    const chunk = assistantAudioQueue.shift();
    if (!chunk || !audioContext.value) return;

    // Decode Base64 PCM16 to AudioBuffer
    const binary = window.atob(chunk);
    const len = binary.length;
    const bytes = new Uint8Array(len);
    for (let i = 0; i < len; i++) bytes[i] = binary.charCodeAt(i);
    const int16 = new Int16Array(bytes.buffer);
    const float32 = new Float32Array(int16.length);
    for (let i = 0; i < int16.length; i++) float32[i] = int16[i] / 32768.0;

    const buffer = audioContext.value.createBuffer(1, float32.length, 24000);
    buffer.getChannelData(0).set(float32);

    const source = audioContext.value.createBufferSource();
    source.buffer = buffer;
    source.connect(audioContext.value.destination);
    
    const currentTime = audioContext.value.currentTime;
    if (nextStartTime < currentTime) nextStartTime = currentTime;
    
    source.start(nextStartTime);
    nextStartTime += buffer.duration;
    
    source.onended = () => {
        // Just a trigger, logic is handled by queue consumption usually, 
        // but since we schedule ahead, this is loose. 
    };

    // Schedule next immediately
    playNextAudioChunk();
};

const interruptAudio = () => {
    assistantAudioQueue.length = 0; // Clear queue
    isPlayingAudio = false;
    isTalking.value = false;
    nextStartTime = 0;
    // We can't easily stop currently playing node without keeping reference to all sources, 
    // but clearing queue helps.
    // Ideally, we would disconnect the destination or suspend context briefly.
};

const stopRealtimeSession = () => {
    if (socket.value) {
        socket.value.close();
        socket.value = null;
    }
    if (audioContext.value) {
        audioContext.value.close();
        audioContext.value = null;
    }
    isConnecting.value = false;
    isListening.value = false;
    isTalking.value = false;
};

watch(voiceModeActive, (isActive) => {
    if (isActive) {
        startRealtimeSession();
    } else {
        stopRealtimeSession();
    }
});

onBeforeUnmount(() => {
    stopRealtimeSession();
});
</script>

<template>
    <Head title="Panel" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-8 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl leading-tight font-semibold">
                        Tus dispositivos
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Consulta y administra los dispositivos inteligentes de
                        tu hogar.
                    </p>
                </div>

                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end"
                >
                    <div
                        class="order-1 flex w-full flex-col gap-1 text-sm text-muted-foreground sm:order-none sm:w-auto"
                    >
                        <Label
                            for="area-filter"
                            class="font-medium text-foreground"
                            >Filtrar por área</Label
                        >
                        <select
                            id="area-filter"
                            class="aria-invalid-border-destructive flex h-9 min-w-[220px] rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 md:text-sm dark:aria-invalid:ring-destructive/40"
                            :value="areaFilter ?? ''"
                            @change="
                                handleAreaFilterChange(
                                    ($event.target as HTMLSelectElement)
                                        .value === ''
                                        ? null
                                        : Number(
                                              (
                                                  $event.target as HTMLSelectElement
                                              ).value,
                                          ),
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

                    <div
                        class="order-2 flex w-full flex-wrap gap-2 sm:order-none sm:w-auto sm:justify-end"
                    >
                        <Dialog
                            :open="isDeviceDialogOpen"
                            @update:open="isDeviceDialogOpen = $event"
                        >
                            <DialogTrigger as-child>
                                <Button
                                    size="lg"
                                    class="order-2 h-10 min-w-[200px] gap-2 px-6 sm:order-none sm:w-auto"
                                    @click="openCreateDialog"
                                >
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
                                            <Label for="device-name"
                                                >Nombre del dispositivo</Label
                                            >
                                            <Input
                                                id="device-name"
                                                v-model="deviceName"
                                                name="name"
                                                autocomplete="off"
                                                placeholder="Ej. Sensor de temperatura"
                                                required
                                                :aria-invalid="
                                                    Boolean(errors.name)
                                                "
                                            />
                                            <InputError
                                                :message="errors.name"
                                            />
                                        </div>

                                        <div class="grid gap-2">
                                            <div
                                                class="flex items-center justify-between"
                                            >
                                                <Label for="device-area"
                                                    >Área registrada</Label
                                                >
                                                <Button
                                                    type="button"
                                                    variant="link"
                                                    size="sm"
                                                    class="h-auto p-0 text-xs font-medium"
                                                    @click="
                                                        isAreaDialogOpen = true
                                                    "
                                                >
                                                    Crear nueva área
                                                </Button>
                                            </div>
                                            <select
                                                id="device-area"
                                                v-model="selectedDeviceAreaId"
                                                name="area_id"
                                                class="aria-invalid-border-destructive flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-70 aria-invalid:ring-destructive/20 md:text-sm dark:aria-invalid:ring-destructive/40"
                                                :disabled="!areaOptions.length"
                                            >
                                                <option value="">
                                                    Selecciona un área
                                                </option>
                                                <option
                                                    v-for="area in areaOptions"
                                                    :key="area.id"
                                                    :value="area.id"
                                                >
                                                    {{ area.name }} —
                                                    {{ area.locationName }}
                                                </option>
                                            </select>
                                            <p
                                                v-if="!areaOptions.length"
                                                class="text-xs text-muted-foreground"
                                            >
                                                Crea un área desde el botón
                                                &quot;Nueva área&quot; antes de
                                                continuar.
                                            </p>
                                            <InputError
                                                :message="errors.area_id"
                                            />
                                        </div>

                                        <div class="grid gap-2">
                                            <Label for="device-type"
                                                >Tipo de dispositivo</Label
                                            >
                                            <select
                                                id="device-type"
                                                v-model="deviceType"
                                                name="type"
                                                class="aria-invalid-border-destructive flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 md:text-sm dark:aria-invalid:ring-destructive/40"
                                            >
                                                <option value="switch">
                                                    Encendido / Apagado
                                                </option>
                                                <option value="dimmer">
                                                    Regulable
                                                </option>
                                            </select>
                                            <InputError
                                                :message="errors.type"
                                            />
                                        </div>

                                        <div
                                            v-if="deviceDialogMode === 'create'"
                                            class="grid gap-2"
                                        >
                                            <Label for="device-status"
                                                >Estado</Label
                                            >
                                            <select
                                                id="device-status"
                                                v-model="deviceStatus"
                                                name="status"
                                                class="aria-invalid-border-destructive flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 md:text-sm dark:aria-invalid:ring-destructive/40"
                                            >
                                                <option value="on">
                                                    Encendido
                                                </option>
                                                <option value="off">
                                                    Apagado
                                                </option>
                                            </select>
                                            <InputError
                                                :message="errors.status"
                                            />
                                        </div>
                                        <input
                                            v-else
                                            type="hidden"
                                            name="status"
                                            :value="deviceStatus"
                                        />

                                        <div
                                            v-if="
                                                showBrightnessControl &&
                                                deviceDialogMode === 'create'
                                            "
                                            class="grid gap-2"
                                        >
                                            <div
                                                class="flex items-center justify-between"
                                            >
                                                <Label for="device-brightness"
                                                    >Nivel de potencia</Label
                                                >
                                                <span
                                                    class="text-sm text-muted-foreground"
                                                    >{{
                                                        deviceBrightnessLabel
                                                    }}%</span
                                                >
                                            </div>
                                            <input
                                                id="device-brightness"
                                                v-model.number="
                                                    deviceBrightness
                                                "
                                                type="range"
                                                min="0"
                                                max="100"
                                                step="5"
                                                name="brightness"
                                                class="h-2 w-full cursor-pointer appearance-none rounded-full bg-secondary accent-primary"
                                            />
                                            <div
                                                class="flex justify-between text-xs text-muted-foreground"
                                            >
                                                <span>0%</span>
                                                <span>50%</span>
                                                <span>100%</span>
                                            </div>
                                            <InputError
                                                :message="errors.brightness"
                                            />
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
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                >Cancelar</Button
                                            >
                                        </DialogClose>
                                        <Button
                                            type="submit"
                                            :disabled="processing"
                                        >
                                            {{ submitButtonLabel }}
                                        </Button>
                                    </DialogFooter>
                                </Form>
                            </DialogContent>
                        </Dialog>

                        <Dialog
                            :open="isAreaDialogOpen"
                            @update:open="isAreaDialogOpen = $event"
                        >
                            <DialogTrigger as-child>
                                <Button
                                    size="lg"
                                    variant="outline"
                                    class="order-1 h-10 min-w-[200px] gap-2 px-6 sm:order-none sm:w-auto"
                                    @click="isAreaDialogOpen = true"
                                >
                                    <IconPlus class="size-4" />
                                    Nueva área
                                </Button>
                            </DialogTrigger>
                            <DialogContent class="sm:max-w-sm">
                                <DialogHeader class="space-y-2">
                                    <DialogTitle>Registrar área</DialogTitle>
                                    <DialogDescription>
                                        Primero elige una ubicación y luego
                                        asigna un nombre descriptivo.
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
                                        <Label for="new-area-location"
                                            >Ubicación</Label
                                        >
                                        <select
                                            id="new-area-location"
                                            v-model="newAreaLocationId"
                                            name="location_id"
                                            class="aria-invalid-border-destructive flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 md:text-sm dark:aria-invalid:ring-destructive/40"
                                        >
                                            <option value="">
                                                Selecciona una ubicación
                                            </option>
                                            <option
                                                v-for="location in availableLocations"
                                                :key="location.id"
                                                :value="location.id"
                                            >
                                                {{ location.name }}
                                            </option>
                                        </select>
                                        <InputError
                                            :message="errors.location_id"
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="new-area-name"
                                            >Nombre del área</Label
                                        >
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
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                >Cancelar</Button
                                            >
                                        </DialogClose>
                                        <Button
                                            type="submit"
                                            :disabled="processing"
                                        >
                                            Guardar área
                                        </Button>
                                    </DialogFooter>
                                </Form>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>

                <div
                    v-if="hasDevices"
                    class="grid w-full grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
                >
                    <Card
                        v-for="device in devices"
                        :key="device.id"
                        class="border-border/70"
                    >
                        <CardHeader
                            class="relative flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div class="space-y-1">
                                <CardTitle class="text-lg font-semibold">{{
                                    device.name
                                }}</CardTitle>
                                <CardDescription
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <IconMapPin
                                        class="size-4 text-foreground/70"
                                    />
                                    <span>{{
                                        locationLabel(device.location)
                                    }}</span>
                                </CardDescription>
                            </div>
                            <div
                                class="absolute top-6 right-6 flex flex-wrap items-center gap-2 sm:static sm:justify-end sm:self-auto"
                            >
                                <DropdownMenu>
                                    <DropdownMenuTrigger :as-child="true">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="shrink-0 gap-2"
                                        >
                                            <IconDotsVertical class="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        class="w-40"
                                    >
                                        <DropdownMenuItem
                                            @click="openEditDialog(device)"
                                        >
                                            <IconPencil class="size-4" />
                                            Editar
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            @click="openAdvancedDialog(device)"
                                        >
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
                        <CardContent
                            class="space-y-4 text-sm text-muted-foreground"
                        >
                            <div
                                class="flex flex-col gap-3 rounded-lg border border-border/60 p-3 text-foreground/80 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="flex flex-col">
                                    <span
                                        class="text-xs tracking-wide text-muted-foreground/80 uppercase"
                                    >
                                        Estado actual
                                    </span>
                                    <span
                                        class="text-base font-medium text-foreground"
                                    >
                                        {{ statusLabels[device.status] }}
                                    </span>
                                </div>
                                <Button
                                    type="button"
                                    :variant="
                                        device.status === 'on'
                                            ? 'outline'
                                            : 'default'
                                    "
                                    size="sm"
                                    class="w-full sm:w-auto"
                                    :disabled="statusUpdating[device.id]"
                                    @click="handleStatusToggle(device)"
                                >
                                    <Spinner
                                        v-if="statusUpdating[device.id]"
                                        class="size-4"
                                    />
                                    <template v-else>
                                        <IconPower class="size-4" />
                                        <span>{{
                                            statusButtonLabel(device)
                                        }}</span>
                                    </template>
                                </Button>
                            </div>
                            <div
                                v-if="device.type === 'dimmer'"
                                class="space-y-3 rounded-lg border border-border/60 p-3 text-foreground"
                            >
                                <div
                                    class="flex items-center justify-between text-xs tracking-wide text-muted-foreground/80 uppercase"
                                >
                                    <span
                                        class="inline-flex items-center gap-1 text-muted-foreground"
                                    >
                                        <IconSun class="size-4" />
                                        Nivel de potencia
                                    </span>
                                    <span
                                        class="text-base font-semibold text-foreground"
                                    >
                                        {{ currentDeviceBrightness(device) }}%
                                    </span>
                                </div>
                                <input
                                    type="range"
                                    min="0"
                                    max="100"
                                    step="5"
                                    :value="currentDeviceBrightness(device)"
                                    class="h-2 w-full cursor-pointer appearance-none rounded-full bg-secondary accent-primary disabled:cursor-not-allowed"
                                    :disabled="brightnessUpdating[device.id]"
                                    @input="
                                        handleBrightnessInput(
                                            device,
                                            $event.target.value,
                                        )
                                    "
                                    @change="
                                        handleBrightnessChange(
                                            device,
                                            $event.target.value,
                                        )
                                    "
                                />
                                <div
                                    class="flex items-center justify-between text-xs text-muted-foreground"
                                >
                                    <span>Apagado</span>
                                    <span>Máximo</span>
                                </div>
                                <p
                                    v-if="brightnessUpdating[device.id]"
                                    class="text-xs text-muted-foreground"
                                >
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
                        <p class="text-base font-medium">
                            Aún no tienes dispositivos
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Agrega tu primer dispositivo para visualizarlo en
                            esta lista.
                        </p>
                    </div>
                    <Button size="lg" @click="openCreateDialog">
                        <IconPlus class="size-4" />
                        Agregar dispositivo
                    </Button>
                </div>
            </div>
        </div>
        <Dialog
            :open="isAdvancedDialogOpen"
            @update:open="isAdvancedDialogOpen = $event"
        >
            <DialogContent class="sm:max-w-lg">
                <DialogHeader class="space-y-2">
                    <DialogTitle>Opciones avanzadas</DialogTitle>
                    <DialogDescription>
                        Comparte este enlace con tu dispositivo para leer su
                        estado actual.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="advancedDevice" class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="advanced-webhook-url"
                            >URL del webhook</Label
                        >
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-center"
                        >
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
                            Tus dispositivos pueden usar esta URL para consultar
                            el estado y la potencia.
                        </p>
                    </div>

                    <div
                        class="space-y-3 rounded-lg border border-border/60 bg-muted/30 p-3 text-sm text-foreground"
                    >
                        <div class="flex items-center justify-between">
                            <span
                                class="inline-flex items-center gap-1 text-muted-foreground"
                            >
                                <IconPower class="size-4" />
                                Estado
                            </span>
                            <span class="font-semibold text-foreground">
                                {{ statusLabels[advancedDevice.status] }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span
                                class="inline-flex items-center gap-1 text-muted-foreground"
                            >
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
                        <Button type="button" variant="secondary"
                            >Cerrar</Button
                        >
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
        <Dialog
            :open="isHideDialogOpen"
            @update:open="isHideDialogOpen = $event"
        >
            <DialogContent class="sm:max-w-xs">
                <DialogHeader class="space-y-2">
                    <DialogTitle>Confirmar eliminación</DialogTitle>
                    <DialogDescription>
                        Escribe <strong>&ldquo;confirmo&rdquo;</strong> para
                        marcar este dispositivo como eliminado.
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
                        <Button
                            type="button"
                            variant="secondary"
                            @click="closeHideDialog"
                            >Cancelar</Button
                        >
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
    <div
        class="fixed right-6 bottom-6 z-50 flex flex-col items-center sm:right-8 sm:bottom-8"
    >
        <Button
            type="button"
            size="icon"
            class="h-14 w-14 rounded-full bg-primary text-primary-foreground shadow-lg shadow-primary/40 hover:shadow-primary/70 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            :aria-label="voiceModeButtonLabel"
            :title="voiceModeButtonLabel"
            :aria-pressed="voiceModeActive"
            @click="toggleVoiceMode"
        >
            <IconMicrophone class="size-6" />
            <span class="sr-only">{{ voiceModeButtonLabel }}</span>
        </Button>
        <p
            class="mt-2 text-xs font-semibold text-white"
            :class="voiceModeActive ? 'text-emerald-400' : 'text-white/70'"
        >
            {{ voiceModeStatusText }}
        </p>
    </div>
    <teleport to="body">
        <transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="voiceModeActive"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 backdrop-blur-md"
                role="dialog"
                aria-modal="true"
                aria-label="Panel de voz"
            >
                <div
                    class="relative flex w-full max-w-lg flex-col items-center gap-12 p-8 text-center"
                >
                    <!-- Status Indicator Ring -->
                    <div class="relative">
                        <div
                            class="absolute inset-0 animate-ping rounded-full opacity-20 duration-2000"
                            :class="
                                voiceModeResponseActive
                                    ? 'bg-emerald-500'
                                    : 'bg-white'
                            "
                        ></div>
                        <div
                            class="flex h-32 w-32 items-center justify-center rounded-full border border-white/10 shadow-2xl transition-all duration-500"
                            :class="
                                voiceModeResponseActive
                                    ? 'bg-emerald-500/20 shadow-emerald-500/20 ring-2 ring-emerald-500/50'
                                    : 'bg-white/5 shadow-white/10 ring-1 ring-white/20'
                            "
                        >
                            <IconMicrophone
                                class="size-12 transition-transform duration-500"
                                :class="
                                    voiceModeResponseActive
                                        ? 'scale-110 text-emerald-400'
                                        : 'text-white'
                                "
                            />
                        </div>
                    </div>

                    <div class="space-y-3">
                        <h2
                            class="text-3xl font-bold tracking-tight text-white"
                        >
                            Modo de voz
                        </h2>
                        <p class="text-lg text-white/60">
                            {{
                                isConnecting
                                    ? 'Conectando...'
                                    : isTalking
                                      ? 'Hablando...'
                                      : isListening
                                        ? 'Te escucho...'
                                        : responseText || 'Esperando...'
                            }}
                        </p>
                    </div>

                    <!-- Wave Animation -->
                    <div
                        class="flex h-16 items-end gap-2"
                        role="status"
                        aria-live="polite"
                    >
                        <span
                            v-for="delay in voiceWaveDelays"
                            :key="delay"
                            class="w-2 rounded-full bg-white/80 transition-all duration-300"
                            :class="{
                                'animate-voice-wave': isTalking || isListening,
                                'h-4 bg-emerald-400': isTalking,
                                'h-2 bg-white/40': !isTalking && !isListening,
                            }"
                            :style="{
                                animationDelay: `${delay}s`,
                            }"
                        ></span>
                    </div>

                    <!-- Controls -->
                    <div class="flex items-center gap-6">
                        <Button
                            variant="outline"
                            size="lg"
                            type="button"
                            class="h-14 rounded-full border-white/10 bg-white/5 px-8 text-base font-medium text-white backdrop-blur-sm transition-all hover:bg-white/10 hover:text-white hover:border-white/20"
                            @click="toggleMute"
                        >
                            <component
                                :is="voiceMuteIcon"
                                class="mr-2 size-5"
                            />
                            {{ voiceMuteButtonLabel }}
                        </Button>

                        <Button
                            variant="ghost"
                            size="icon"
                            type="button"
                            class="h-14 w-14 rounded-full border border-white/10 bg-white/5 text-white transition-all hover:bg-red-500/20 hover:border-red-500/30 hover:text-red-400"
                            aria-label="Cerrar modo de voz"
                            @click="toggleVoiceMode"
                        >
                            <IconX class="size-6" />
                        </Button>
                    </div>
                </div>
            </div>
        </transition>
    </teleport>
</template>

<style scoped>
@keyframes voice-wave {
    0%,
    100% {
        height: 0.75rem;
        opacity: 0.4;
    }
    50% {
        height: 3rem;
        opacity: 1;
    }
}

.animate-voice-wave {
    animation: voice-wave 1.2s ease-in-out infinite;
}
</style>
