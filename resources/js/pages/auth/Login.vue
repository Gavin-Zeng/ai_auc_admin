<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import PasswordInput from '@/components/PasswordInput.vue';
import { store } from '@/routes/login';

defineOptions({
    layout: { title: 'AUC 后台', description: '' },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
    captcha: { question: string };
}>();

const form = useForm({
    account: '',
    password: '',
    captcha_answer: '',
    remember: true,
});

function submit(): void {
    form.post(store().url, {
        onSuccess: () => form.reset('password', 'captcha_answer'),
    });
}
</script>

<template>
    <Head title="登录" />

    <ElAlert
        v-if="status"
        type="success"
        :closable="false"
        :title="status"
        class="mb-4"
    />

    <ElForm label-position="top" @submit.prevent="submit">
        <ElFormItem label="账号" required :error="form.errors.account">
            <ElInput
                v-model="form.account"
                autofocus
                autocomplete="username"
                placeholder="请输入账号"
                tabindex="1"
            />
        </ElFormItem>
        <ElFormItem label="密码" required :error="form.errors.password">
            <PasswordInput
                v-model="form.password"
                autocomplete="current-password"
                placeholder="请输入密码"
                tabindex="2"
            />
        </ElFormItem>
        <ElFormItem label="验证码" required :error="form.errors.captcha_answer">
            <div class="grid w-full grid-cols-[1fr_100px] gap-3">
                <ElInput
                    v-model="form.captcha_answer"
                    inputmode="numeric"
                    autocomplete="off"
                    placeholder="计算结果"
                    tabindex="3"
                />
                <div
                    class="flex items-center justify-center rounded-md border border-border bg-muted font-mono text-sm"
                >
                    {{ captcha.question }}
                </div>
            </div>
        </ElFormItem>
        <ElButton
            type="primary"
            native-type="submit"
            class="w-full"
            :loading="form.processing"
            data-test="login-button"
            tabindex="4"
        >
            登录
        </ElButton>
    </ElForm>
</template>
