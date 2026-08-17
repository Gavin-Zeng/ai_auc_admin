<script setup lang="ts">
import type { Appearance } from '@/types';

const appearance = defineModel<Appearance>({ required: true });
const options = [
    { value: 'light', label: '浅色' },
    { value: 'dark', label: '深色' },
    { value: 'system', label: '跟随系统' },
];

function update(value: string | number | boolean): void {
    appearance.value = value as Appearance;
}
</script>

<template>
    <ElSegmented v-model="appearance" :options="options" @change="update" />
</template>
