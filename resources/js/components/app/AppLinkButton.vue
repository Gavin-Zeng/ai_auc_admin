<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';

withDefaults(
    defineProps<{
        href: NonNullable<InertiaLinkProps['href']>;
        type?:
            | 'primary'
            | 'success'
            | 'warning'
            | 'danger'
            | 'info'
            | 'default';
        size?: 'small' | 'default' | 'large';
        plain?: boolean;
        link?: boolean;
        disabled?: boolean;
        prefetch?: boolean;
    }>(),
    {
        type: 'default',
        size: 'default',
        plain: false,
        link: false,
        disabled: false,
        prefetch: false,
    },
);
</script>

<template>
    <ElButton
        :tag="Link"
        :href="href"
        :type="type"
        :size="size"
        :plain="plain"
        :link="link"
        :disabled="disabled"
        :prefetch="prefetch"
    >
        <slot />
    </ElButton>
</template>
