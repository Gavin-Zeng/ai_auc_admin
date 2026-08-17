<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import PasswordInput from '@/components/PasswordInput.vue';
import { store } from '@/routes/password/confirm';

defineOptions({
    layout: {
        title: '确认密码',
        description: '这是安全操作区域，请先确认密码再继续。',
    },
});

const form = useForm({ password: '' });

function submit(): void {
    form.post(store().url, { onSuccess: () => form.reset() });
}
</script>

<template>
    <Head title="确认密码" />

    <ElForm label-position="top" @submit.prevent="submit">
        <ElFormItem label="密码" required :error="form.errors.password">
            <PasswordInput
                v-model="form.password"
                autocomplete="current-password"
                autofocus
            />
        </ElFormItem>
        <ElButton
            type="primary"
            native-type="submit"
            class="w-full"
            :loading="form.processing"
            data-test="confirm-password-button"
        >
            确认密码
        </ElButton>
    </ElForm>
</template>
