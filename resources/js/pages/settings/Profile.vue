<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
};

defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: '个人资料',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="个人资料" />

    <h1 class="sr-only">个人资料</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="个人信息"
            description="更新你的账号和姓名"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="account">账号</Label>
                <Input
                    id="account"
                    class="mt-1 block w-full"
                    name="account"
                    :default-value="user.account"
                    required
                    autocomplete="username"
                    placeholder="账号"
                />
                <InputError class="mt-2" :message="errors.account" />
            </div>

            <div class="grid gap-2">
                <Label for="name">姓名</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    placeholder="姓名"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-profile-button"
                    >保存</Button
                >
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>
