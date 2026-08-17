<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import { Check, RotateCcw, Save, Undo2 } from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import {
    BRAND_PRESETS,
    contrastWithWhite,
    CUSTOM_THEME_DEFAULTS,
    DEFAULT_THEME_SETTINGS,
    useTheme,
} from '@/composables/useTheme';
import { edit } from '@/routes/appearance';
import type {
    ThemeDensity,
    ThemeNeutral,
    ThemeRadius,
    ThemeSettings,
    ThemeStyle,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: '外观设置',
                href: edit(),
            },
        ],
    },
});

const { settings, previewTheme, refreshTheme, restoreTheme, saveTheme } =
    useTheme();
const draft = reactive<ThemeSettings>({ ...DEFAULT_THEME_SETTINGS });
const isReady = ref(false);
const saving = ref(false);

const styleOptions = [
    { value: 'default', label: '默认主题' },
    { value: 'custom', label: '高级主题' },
];
const neutralOptions = [
    { value: 'graphite', label: '石墨灰' },
    { value: 'neutral', label: '中性灰' },
    { value: 'warm', label: '暖灰' },
];
const radiusOptions = [
    { value: '2', label: '2px' },
    { value: '4', label: '4px' },
    { value: '6', label: '6px' },
];
const densityOptions = [
    { value: 'compact', label: '紧凑' },
    { value: 'standard', label: '标准' },
];

const brandColor = computed<string>({
    get: () => draft.brand,
    set: (value) => {
        if (value) {
            draft.brand = value.toUpperCase();
        }
    },
});
const contrastRatio = computed(() => contrastWithWhite(draft.brand));
const hasValidBrandFormat = computed(() =>
    /^#[0-9A-Fa-f]{6}$/.test(draft.brand),
);
const brandError = computed(() => {
    if (draft.style === 'default') {
        return undefined;
    }

    if (!hasValidBrandFormat.value) {
        return '请输入以 # 开头的六位 HEX 颜色。';
    }

    if (contrastRatio.value < 4.5) {
        return `与白色文字的对比度为 ${contrastRatio.value.toFixed(2)}:1，至少需要 4.5:1。`;
    }

    return undefined;
});
const hasValidBrand = computed(() => brandError.value === undefined);
const hasChanges = computed(
    () => JSON.stringify(draft) !== JSON.stringify(settings.value),
);

function assignDraft(value: ThemeSettings): void {
    Object.assign(draft, value);
}

function selectStyle(value: string | number | boolean): void {
    draft.style = value as ThemeStyle;
}

function selectNeutral(value: string | number | boolean): void {
    draft.neutral = value as ThemeNeutral;
}

function selectRadius(value: string | number | boolean): void {
    draft.radius = value as ThemeRadius;
}

function selectDensity(value: string | number | boolean): void {
    draft.density = value as ThemeDensity;
}

function resetCustomTheme(): void {
    Object.assign(draft, CUSTOM_THEME_DEFAULTS);
}

function cancelChanges(): void {
    assignDraft({ ...settings.value });
    restoreTheme();
}

async function saveChanges(): Promise<void> {
    if (!hasValidBrand.value || saving.value) {
        return;
    }

    saving.value = true;
    await nextTick();

    const saved = saveTheme({ ...draft });

    assignDraft(saved);
    saving.value = false;
    ElMessage.success('外观设置已保存。');
}

watch(
    draft,
    (value) => {
        if (isReady.value) {
            previewTheme({ ...value });
        }
    },
    { deep: true },
);

onMounted(() => {
    assignDraft(refreshTheme());
    isReady.value = true;
    previewTheme({ ...draft });
});

onBeforeUnmount(restoreTheme);
</script>

<template>
    <Head title="外观设置" />

    <section class="space-y-6">
        <div>
            <h2 class="text-base font-medium">外观设置</h2>
            <p class="text-sm text-muted-foreground">
                调整当前浏览器的界面外观。
            </p>
        </div>

        <ElSkeleton v-if="!isReady" :rows="6" animated />

        <ElForm v-else label-position="top" @submit.prevent="saveChanges">
            <div class="space-y-6">
                <ElFormItem label="显示模式">
                    <AppearanceTabs v-model="draft.appearance" />
                </ElFormItem>

                <ElFormItem label="主题风格">
                    <ElSegmented
                        v-model="draft.style"
                        :options="styleOptions"
                        @change="selectStyle"
                    />
                </ElFormItem>

                <div
                    v-if="draft.style === 'custom'"
                    class="space-y-6 border-t border-border pt-6"
                >
                    <ElFormItem label="品牌色" :error="brandError">
                        <div class="flex flex-wrap items-center gap-3">
                            <ElColorPicker
                                v-model="brandColor"
                                :predefine="
                                    BRAND_PRESETS.map((item) => item.value)
                                "
                            />
                            <ElInput
                                v-model="draft.brand"
                                aria-label="品牌色 HEX"
                                class="w-28"
                                maxlength="7"
                                @change="
                                    draft.brand = draft.brand.toUpperCase()
                                "
                            />
                            <div class="flex items-center gap-2">
                                <ElTooltip
                                    v-for="preset in BRAND_PRESETS"
                                    :key="preset.value"
                                    :content="preset.label"
                                    placement="top"
                                >
                                    <button
                                        type="button"
                                        class="flex size-7 items-center justify-center rounded-sm border border-black/10 text-white shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                                        :style="{
                                            backgroundColor: preset.value,
                                        }"
                                        :aria-label="preset.label"
                                        @click="draft.brand = preset.value"
                                    >
                                        <Check
                                            v-if="draft.brand === preset.value"
                                            class="size-4"
                                        />
                                    </button>
                                </ElTooltip>
                            </div>
                        </div>
                    </ElFormItem>

                    <ElFormItem label="中性色">
                        <ElSegmented
                            v-model="draft.neutral"
                            :options="neutralOptions"
                            @change="selectNeutral"
                        />
                    </ElFormItem>

                    <ElFormItem label="圆角">
                        <ElSegmented
                            v-model="draft.radius"
                            :options="radiusOptions"
                            @change="selectRadius"
                        />
                    </ElFormItem>

                    <ElFormItem label="界面密度">
                        <ElSegmented
                            v-model="draft.density"
                            :options="densityOptions"
                            @change="selectDensity"
                        />
                    </ElFormItem>

                    <ElButton :icon="RotateCcw" @click="resetCustomTheme">
                        恢复高级主题初始值
                    </ElButton>
                </div>

                <div class="flex flex-wrap gap-3 border-t border-border pt-6">
                    <ElButton
                        type="primary"
                        native-type="submit"
                        :icon="Save"
                        :loading="saving"
                        :disabled="!hasChanges || !hasValidBrand"
                    >
                        保存
                    </ElButton>
                    <ElButton
                        :icon="Undo2"
                        :disabled="!hasChanges || saving"
                        @click="cancelChanges"
                    >
                        取消更改
                    </ElButton>
                </div>
            </div>
        </ElForm>
    </section>
</template>
