<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    value: boolean | number | string | null | undefined;
    label?: string;
}>();

const normalizedValue = computed(() => String(props.value ?? '').toLowerCase());
const isEnabled = computed(
    () =>
        props.value === true ||
        props.value === 1 ||
        ['1', 'active', 'enabled', 'true', 'normal', 'passed'].includes(
            normalizedValue.value,
        ),
);
const isDisabled = computed(
    () =>
        props.value === false ||
        props.value === 0 ||
        ['0', 'disabled', 'false', 'failed', 'error'].includes(
            normalizedValue.value,
        ),
);
const tagType = computed(() => {
    if (isEnabled.value) {
        return 'success';
    }

    if (isDisabled.value) {
        return 'danger';
    }

    return 'info';
});
const displayLabel = computed(() => {
    if (props.label) {
        return props.label;
    }

    if (isEnabled.value) {
        return '启用';
    }

    if (isDisabled.value) {
        return '停用';
    }

    return String(props.value ?? '未知');
});
</script>

<template>
    <ElTag :type="tagType" effect="light" size="small">
        {{ displayLabel }}
    </ElTag>
</template>
