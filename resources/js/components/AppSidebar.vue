<script setup lang="ts">
import LocationController from '@/actions/App/Http/Controllers/LocationController';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import InputError from '@/components/InputError.vue';
import AppLogo from '@/components/AppLogo.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Form, Link, router, usePage } from '@inertiajs/vue3';
import { IconPlus } from '@tabler/icons-vue';
import { computed, ref } from 'vue';

const mainNavItems: NavItem[] = [];

interface SidebarLocation {
    id: number;
    name: string;
}

type LocationFilter = number | null;

const page = usePage<{
    sidebarLocations?: SidebarLocation[];
    sidebarFilters?: { location?: LocationFilter };
}>();

const sidebarLocations = computed<SidebarLocation[]>(() => page.props.sidebarLocations ?? []);
const locationFilter = computed<LocationFilter>(() => page.props.sidebarFilters?.location ?? null);
const isLocationDialogOpen = ref(false);
const newLocationName = ref('');
const { state } = useSidebar();
const showLocationSection = computed(() => state.value === 'expanded');

const isActiveLocation = (location: LocationFilter): boolean => {
    if (location === null) {
        return locationFilter.value === null;
    }

    return locationFilter.value === location;
};

const applyLocationFilter = (location: LocationFilter): void => {
    const query = location === null ? {} : { location };

    router.get(
        dashboard({ query }).url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
        }
    );
};

const handleLocationStored = (): void => {
    newLocationName.value = '';
    isLocationDialogOpen.value = false;
};
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />

            <div
                v-if="showLocationSection"
                class="mt-6 space-y-3 px-2"
            >
                <div class="flex items-center justify-between text-xs uppercase tracking-wide text-muted-foreground/80">
                    <span>Ubicaciones</span>
                    <Dialog :open="isLocationDialogOpen" @update:open="isLocationDialogOpen = $event">
                        <DialogTrigger as-child>
                            <Button
                                size="icon"
                                variant="outline"
                                class="h-7 w-7"
                                @click.stop="isLocationDialogOpen = true"
                            >
                                <IconPlus class="size-4" />
                                <span class="sr-only">Agregar ubicación</span>
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-xs">
                            <DialogHeader class="space-y-2">
                                <DialogTitle>Nueva ubicación</DialogTitle>
                                <DialogDescription>
                                    Organiza tus dispositivos por habitación.
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
                                        Guardar
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>

                <div class="space-y-1">
                    <button
                        type="button"
                        class="w-full truncate whitespace-nowrap rounded-md px-3 py-2 text-left text-sm font-medium transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground dark:hover:bg-sidebar-accent/80 dark:hover:text-sidebar-accent-foreground"
                        :class="isActiveLocation(null) ? 'bg-primary text-primary-foreground' : ''"
                        @click="applyLocationFilter(null)"
                    >
                        Todas las ubicaciones
                    </button>
                    <template v-if="sidebarLocations.length">
                        <button
                            v-for="location in sidebarLocations"
                            :key="location.id"
                            type="button"
                            class="w-full truncate whitespace-nowrap rounded-md px-3 py-2 text-left text-sm font-medium transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground dark:hover:bg-sidebar-accent/80 dark:hover:text-sidebar-accent-foreground"
                            :class="isActiveLocation(location.id) ? 'bg-primary text-primary-foreground' : ''"
                            @click="applyLocationFilter(location.id)"
                        >
                            {{ location.name }}
                        </button>
                    </template>
                    <p v-else class="px-3 py-2 text-xs text-muted-foreground">
                        Aún no tienes ubicaciones guardadas.
                    </p>
                </div>
            </div>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
