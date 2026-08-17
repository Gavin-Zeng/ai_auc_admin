<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import { edit } from '@/routes/profile';

defineProps<{ mustVerifyEmail: boolean; status?: string }>();
defineOptions({
    layout: { breadcrumbs: [{ title: '个人资料', href: edit() }] },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const form = useForm({ account: user.value.account, name: user.value.name });

function submit(): void {
    form.patch(ProfileController.update().url, { preserveScroll: true });
}
</script>

<template>
    <Head title="个人资料" />

    <section class="space-y-4">
        <div>
            <h2 class="text-base font-medium">个人信息</h2>
            <p class="text-sm text-muted-foreground">更新你的账号和姓名。</p>
        </div>
        <ElForm label-position="top" @submit.prevent="submit">
            <ElFormItem label="账号" required :error="form.errors.account">
                <ElInput v-model="form.account" autocomplete="username" />
            </ElFormItem>
            <ElFormItem label="姓名" required :error="form.errors.name">
                <ElInput v-model="form.name" autocomplete="name" />
            </ElFormItem>
            <ElButton
                type="primary"
                native-type="submit"
                :loading="form.processing"
                data-test="update-profile-button"
            >
                保存
            </ElButton>
        </ElForm>
    </section>

    <DeleteUser />
</template>
