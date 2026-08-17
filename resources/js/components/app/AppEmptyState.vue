<script setup lang="ts">
withDefaults(
    defineProps<{
        description?: string;
        imageSize?: number;
    }>(),
    {
        description: '暂无数据',
        imageSize: 88,
    },
);
</script>

<template>
    <ElEmpty :description="description" :image-size="imageSize">
        <slot />
    </ElEmpty>
</template>
