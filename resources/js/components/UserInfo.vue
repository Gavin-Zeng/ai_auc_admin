<script setup lang="ts">
import { computed } from 'vue';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';

type Props = {
    user: User;
    showEmail?: boolean;
    compact?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
    compact: false,
});

const { getInitials } = useInitials();

// Compute whether we should show the avatar image
const showAvatar = computed(
    () => props.user.avatar && props.user.avatar !== '',
);
</script>

<template>
    <div class="flex min-w-0 items-center gap-2">
        <ElAvatar :size="32" :src="showAvatar ? user.avatar : undefined">
            {{ getInitials(user.name) }}
        </ElAvatar>
        <div v-if="!compact" class="min-w-0 flex-1 text-left leading-tight">
            <div class="truncate text-sm font-medium">{{ user.name }}</div>
            <div
                v-if="showEmail && user.email"
                class="truncate text-xs text-muted-foreground"
            >
                {{ user.email }}
            </div>
        </div>
    </div>
</template>
