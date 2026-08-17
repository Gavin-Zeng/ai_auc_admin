<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import PasswordInput from '@/components/PasswordInput.vue';

const dialogVisible = ref(false);
const passwordInput = ref<{ focus: () => void }>();
const form = useForm({ password: '' });

function closeDialog(): void {
    if (form.processing) {
        return;
    }

    dialogVisible.value = false;
    form.reset();
    form.clearErrors();
}

function destroy(): void {
    form.delete(ProfileController.destroy().url, {
        preserveScroll: true,
        onError: () => passwordInput.value?.focus(),
        onSuccess: () => {
            dialogVisible.value = false;
        },
    });
}
</script>

<template>
    <section class="space-y-3">
        <div>
            <h2 class="text-base font-medium">删除账号</h2>
            <p class="text-sm text-muted-foreground">
                删除账号及其所有相关资源。
            </p>
        </div>
        <ElAlert
            type="error"
            :closable="false"
            title="此操作无法撤销"
            description="账号删除后，相关资源和数据也会被永久删除。"
            show-icon
        >
            <template #default>
                <ElButton
                    type="danger"
                    class="mt-3"
                    data-test="delete-user-button"
                    @click="dialogVisible = true"
                >
                    删除账号
                </ElButton>
            </template>
        </ElAlert>
    </section>

    <ElDialog
        v-model="dialogVisible"
        title="确认删除账号"
        width="min(480px, 92vw)"
        :close-on-click-modal="false"
        :before-close="closeDialog"
    >
        <p class="mb-4 text-sm text-muted-foreground">
            请输入密码以确认永久删除账号。
        </p>
        <ElForm label-position="top" @submit.prevent="destroy">
            <ElFormItem label="密码" :error="form.errors.password">
                <PasswordInput
                    ref="passwordInput"
                    v-model="form.password"
                    autocomplete="current-password"
                />
            </ElFormItem>
        </ElForm>
        <template #footer>
            <ElButton :disabled="form.processing" @click="closeDialog"
                >取消</ElButton
            >
            <ElButton
                type="danger"
                :loading="form.processing"
                data-test="confirm-delete-user-button"
                @click="destroy"
            >
                删除账号
            </ElButton>
        </template>
    </ElDialog>
</template>
