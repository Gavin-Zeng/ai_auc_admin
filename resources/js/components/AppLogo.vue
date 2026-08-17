<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

withDefaults(
    defineProps<{
        compact?: boolean;
    }>(),
    {
        compact: false,
    },
);
</script>

<template>
    <div class="flex min-w-0 items-center gap-2">
        <div
            class="flex size-8 shrink-0 items-center justify-center rounded-md bg-primary text-primary-foreground"
        >
            <AppLogoIcon class="size-5 fill-current" />
        </div>
        <span v-if="!compact" class="truncate text-sm font-semibold">
            AUC 后台
        </span>
    </div>
</template>
