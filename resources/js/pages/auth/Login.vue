<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';

defineOptions({
    layout: {
        title: 'AUC 后台',
        description: '',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
    captcha: {
        question: string;
    };
}>();
</script>

<template>
    <Head title="登录" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'captcha_answer']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <input type="hidden" name="remember" value="1" />

        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="account">账号</Label>
                <Input
                    id="account"
                    type="text"
                    name="account"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="username"
                    placeholder="Account"
                />
                <InputError :message="errors.account" />
            </div>

            <div class="grid gap-2">
                <Label for="password">密码</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="密码"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="captcha_answer">验证码</Label>
                <div class="grid grid-cols-[1fr_96px] gap-3">
                    <Input
                        id="captcha_answer"
                        type="text"
                        name="captcha_answer"
                        required
                        inputmode="numeric"
                        :tabindex="3"
                        autocomplete="off"
                        placeholder="结果"
                    />
                    <div
                        class="flex h-9 items-center justify-center rounded-md border border-input bg-muted px-2 font-mono text-sm whitespace-nowrap text-muted-foreground"
                        aria-label="验证码题目"
                    >
                        {{ captcha.question }}
                    </div>
                </div>
                <InputError :message="errors.captcha_answer" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                登录
            </Button>
        </div>
    </Form>
</template>
