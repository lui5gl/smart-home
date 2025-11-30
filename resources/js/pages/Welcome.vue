<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import {
    IconBulb,
    IconMapPin,
    IconMicrophone,
    IconPower,
    IconSparkles,
    IconShieldCheck,
} from '@tabler/icons-vue';
import { dashboard, login, register } from '@/routes';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const featureHighlights = [
    {
        title: 'Control instantáneo',
        description:
            'Activa o desactiva cualquier dispositivo desde la app y conoce su estado en tiempo real.',
        icon: IconPower,
    },
    {
        title: 'Organización por ubicaciones',
        description:
            'Divide tu hogar en ubicaciones y áreas para encontrar todo más rápido y mantenerlo ordenado.',
        icon: IconMapPin,
    },
    {
        title: 'Automatizaciones con voz',
        description:
            'Usa el modo de voz para pedir acciones al asistente del hogar sin tocar la pantalla.',
        icon: IconMicrophone,
    },
    {
        title: 'Escenarios inteligentes',
        description:
            'Configura escenas con ajustes de brillo, estados y dispositivos para cada momento del día.',
        icon: IconBulb,
    },
    {
        title: 'Seguridad y privacidad',
        description:
            'Tus dispositivos se comunican mediante webhooks protegidos y solo tú administras el acceso.',
        icon: IconShieldCheck,
    },
    {
        title: 'Alertas proactivas',
        description:
            'Recibe notificaciones si algún equipo queda sin responder o necesita atención.',
        icon: IconSparkles,
    },
];
</script>

<template>

    <Head title="Bienvenido">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div class="min-h-screen bg-gradient-to-b from-background to-slate-100 dark:from-slate-950 dark:to-slate-900">
        <div class="mx-auto flex w-full max-w-5xl flex-col gap-10 px-4 py-16 sm:px-6 lg:px-8">
            <header class="flex w-full justify-end">
                <nav class="flex items-center gap-3 text-sm">
                    <Link v-if="$page.props.auth.user" :href="dashboard()"
                        class="rounded-full border border-border/60 px-4 py-1.5 font-medium text-foreground transition hover:border-foreground dark:border-border/40 dark:text-white">
                    Panel de control
                    </Link>
                    <template v-else>
                        <Link :href="login()"
                            class="rounded-full border border-transparent px-4 py-1.5 font-medium text-foreground transition hover:border-border/80 hover:bg-foreground/10 hover:text-foreground dark:text-white dark:hover:bg-white/10">
                        Iniciar sesión
                        </Link>
                        <Link v-if="canRegister" :href="register()"
                            class="rounded-full border border-border/60 px-4 py-1.5 font-medium text-foreground transition hover:border-foreground dark:border-border/40 dark:text-white">
                        Registrarse
                        </Link>
                    </template>
                </nav>
            </header>
            <section
                class="rounded-3xl p-8 dark:border-border/40 dark:bg-gradient-to-br dark:from-slate-900 dark:to-slate-950">
                <div class="grid gap-8 lg:grid-cols-[1fr,1fr] lg:items-center">
                    <div class="space-y-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-primary">
                            Smart Home Control
                        </p>
                        <h1 class="text-4xl font-bold leading-tight text-foreground sm:text-5xl">
                            Controla tu hogar inteligente desde un solo panel
                        </h1>
                        <p class="text-base text-muted-foreground">
                            Organiza tus ubicaciones, define áreas, administra dispositivos y responde con
                            la voz. Todo sin complicaciones y con un rendimiento optimizado para tus hábitos.
                        </p>
                        <div class="flex flex-wrap gap-3">
                            <Link :href="login()">
                            <Button size="lg">Iniciar sesión</Button>
                            </Link>
                            <Link :href="register()" v-if="canRegister">
                            <Button size="lg" variant="outline">Crear cuenta</Button>
                            </Link>
                        </div>
                    </div>
                    <div
                        class="rounded-2xl border border-border/60 bg-gradient-to-br from-primary/10 via-background to-background p-6 text-sm text-muted-foreground dark:border-border/40">
                        <p class="text-xs uppercase tracking-[0.3em] text-foreground/70">¿Qué puedes hacer?</p>
                        <ul class="mt-4 space-y-4">
                            <li class="flex items-center gap-3">
                                <IconPower class="size-5 text-primary" />
                                Monitorea el estado de cada dispositivo al instante.
                            </li>
                            <li class="flex items-center gap-3">
                                <IconMapPin class="size-5 text-primary" />
                                Organiza tus habitaciones y zonas por ubicación.
                            </li>
                            <li class="flex items-center gap-3">
                                <IconMicrophone class="size-5 text-primary" />
                                Controla con comandos de voz y obtén respuestas inteligentes.
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <section class="space-y-6">
                <div>
                    <h2 class="text-2xl font-semibold text-foreground">Características principales</h2>
                    <p class="text-sm text-muted-foreground">
                        La plataforma combina organización espacial con control detallado para darte confianza
                        en cada acción.
                    </p>
                </div>
                <div class="grid gap-5 md:grid-cols-2">
                    <article v-for="(feature) in featureHighlights" :key="feature.title"
                        class="rounded-2xl border border-border/60 bg-background/80 p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-xl dark:border-border/40 dark:bg-slate-950/80">
                        <div class="flex items-center gap-3">
                            <component :is="feature.icon" class="size-6 text-primary" />
                            <h3 class="text-lg font-semibold text-foreground">{{ feature.title }}</h3>
                        </div>
                        <p class="mt-3 text-sm text-muted-foreground">{{ feature.description }}</p>
                    </article>
                </div>
            </section>
        </div>
    </div>
</template>
