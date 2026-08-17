<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import PasswordInput from '@/components/PasswordInput.vue';
import { edit } from '@/routes/security';

defineOptions({
    layout: { breadcrumbs: [{ title: '安全设置', href: edit() }] },
});

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function submit(): void {
    form.put(SecurityController.update().url, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head title="安全设置" />

    <section class="space-y-4">
        <div>
            <h2 class="text-base font-medium">修改密码</h2>
            <p class="text-sm text-muted-foreground">
                建议使用足够长且随机的密码以保护账号安全。
            </p>
        </div>
        <ElForm label-position="top" @submit.prevent="submit">
            <ElFormItem
                label="当前密码"
                required
                :error="form.errors.current_password"
            >
                <PasswordInput
                    v-model="form.current_password"
                    autocomplete="current-password"
                />
            </ElFormItem>
            <ElFormItem label="新密码" required :error="form.errors.password">
                <PasswordInput
                    v-model="form.password"
                    autocomplete="new-password"
                />
            </ElFormItem>
            <ElFormItem
                label="确认密码"
                required
                :error="form.errors.password_confirmation"
            >
                <PasswordInput
                    v-model="form.password_confirmation"
                    autocomplete="new-password"
                />
            </ElFormItem>
            <ElButton
                type="primary"
                native-type="submit"
                :loading="form.processing"
                data-test="update-password-button"
            >
                保存密码
            </ElButton>
        </ElForm>
    </section>
</template>
