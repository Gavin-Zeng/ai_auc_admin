<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

type Props = {
    breadcrumbs: BreadcrumbItemType[];
};

defineProps<Props>();
</script>

<template>
    <ElBreadcrumb separator="/">
        <ElBreadcrumbItem
            v-for="(item, index) in breadcrumbs"
            :key="`${item.title}-${index}`"
        >
            <span v-if="index === breadcrumbs.length - 1">
                {{ item.title }}
            </span>
            <Link v-else :href="item.href" class="hover:text-primary">
                {{ item.title }}
            </Link>
        </ElBreadcrumbItem>
    </ElBreadcrumb>
</template>
