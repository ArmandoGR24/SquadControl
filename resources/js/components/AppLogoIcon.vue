<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <img
        src="/squad-logo.svg?v=1"
        alt="Logo SquadControl"
        :class="className"
        v-bind="$attrs"
    />
</template>
