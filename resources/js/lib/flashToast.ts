import { router } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import type { FlashToast } from '@/types/ui';

export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (data) {
            ElMessage({ type: data.type, message: data.message });
        }
    });

    router.on('networkError', () => {
        ElMessage.error('网络连接异常，请稍后重试。');
    });

    router.on('httpException', (event) => {
        const status = (event as CustomEvent).detail?.response?.status;

        if (typeof status === 'number' && status >= 500) {
            ElMessage.error('服务暂时不可用，请稍后重试。');
        }
    });
}
