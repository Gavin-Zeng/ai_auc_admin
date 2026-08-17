<script setup lang="ts">
withDefaults(
    defineProps<{
        currentPage: number;
        lastPage: number;
        pageSize: number;
        total: number;
        disabled?: boolean;
    }>(),
    {
        disabled: false,
    },
);

defineEmits<{
    change: [page: number];
}>();
</script>

<template>
    <div
        v-if="lastPage > 1"
        class="flex justify-end border-t border-border pt-4"
    >
        <ElPagination
            background
            layout="total, prev, pager, next"
            :current-page="currentPage"
            :page-size="pageSize"
            :total="total"
            :disabled="disabled"
            @current-change="$emit('change', $event)"
        />
    </div>
</template>
