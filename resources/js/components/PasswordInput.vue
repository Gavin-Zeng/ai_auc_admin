<script setup lang="ts">
import { ref } from 'vue';

defineOptions({ inheritAttrs: false });

const inputRef = ref<{ focus: () => void }>();

defineExpose({
    focus: () => inputRef.value?.focus(),
});
</script>

<template>
    <ElInput ref="inputRef" type="password" show-password v-bind="$attrs" />
</template>
