<script setup lang="ts">
import { IconHome } from '@tabler/icons-vue';
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

const props = defineProps<Props>();
</script>

<template>
    <IconHome
        :class="props.className"
        aria-hidden="true"
        v-bind="$attrs"
    />
</template>
