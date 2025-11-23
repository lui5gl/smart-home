<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Head } from '@inertiajs/vue3';
import { IconPlus } from '@tabler/icons-vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-6">
            <div class="flex justify-end">
                <Button size="lg">
                    <IconPlus class="size-4" />
                    Agregar dispositivo
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
