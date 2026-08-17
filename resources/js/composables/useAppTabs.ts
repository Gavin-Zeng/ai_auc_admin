import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import type { AppTab } from '@/lib/appTabs';
import {
    createTab,
    normalizeTabHref,
    removeTab,
    tabKey,
    upsertTab,
} from '@/lib/appTabs';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';

type UseAppTabsOptions = {
    fallbackTitle: string;
    getCurrentTitle: () => string;
};

type StoredTabs = {
    tabs: AppTab[];
    activeKey: string;
};

const storageNamespace = 'auc-admin-tabs:v1';

export function useAppTabs(options: UseAppTabsOptions) {
    const page = usePage();
    const tabs = ref<AppTab[]>([]);
    const activeKey = ref('');
    const loadedStorageKey = ref<string | null>(null);
    const dashboardHref = normalizeTabHref(toUrl(dashboard()));
    const storageKey = computed(() => {
        const auth = page.props.auth as
            | { user?: { id?: number }; tenant?: { id?: number } | null }
            | undefined;
        const userId = auth?.user?.id ?? 'guest';
        const tenantId = auth?.tenant?.id ?? 'global';

        return `${storageNamespace}:${userId}:${tenantId}`;
    });

    function persist(): void {
        if (
            typeof window === 'undefined' ||
            loadedStorageKey.value !== storageKey.value
        ) {
            return;
        }

        const payload: StoredTabs = {
            tabs: tabs.value,
            activeKey: activeKey.value,
        };

        try {
            window.sessionStorage.setItem(
                storageKey.value,
                JSON.stringify(payload),
            );
        } catch {
            // Storage can be unavailable in private browsing or restricted frames.
        }
    }

    function load(): void {
        if (
            typeof window === 'undefined' ||
            loadedStorageKey.value === storageKey.value
        ) {
            return;
        }

        loadedStorageKey.value = storageKey.value;
        tabs.value = [];
        activeKey.value = '';

        try {
            const raw = window.sessionStorage.getItem(storageKey.value);
            const stored = raw ? (JSON.parse(raw) as StoredTabs) : null;

            if (
                stored &&
                Array.isArray(stored.tabs) &&
                typeof stored.activeKey === 'string'
            ) {
                tabs.value = stored.tabs
                    .filter(
                        (tab) =>
                            typeof tab?.title === 'string' &&
                            typeof tab?.href === 'string',
                    )
                    .map((tab) => createTab(tab.href, tab.title));
                activeKey.value = tabs.value.some(
                    (tab) => tab.key === stored.activeKey,
                )
                    ? stored.activeKey
                    : '';
            }
        } catch {
            tabs.value = [];
            activeKey.value = '';
        }
    }

    function openTab(href: string, title: string): void {
        const tab = createTab(href, title);

        tabs.value = upsertTab(tabs.value, tab);
        activeKey.value = tab.key;
        persist();
    }

    function activateTab(key: string): void {
        const tab = tabs.value.find((item) => item.key === key);

        if (!tab) {
            return;
        }

        activeKey.value = key;
        persist();

        if (normalizeTabHref(page.url) !== tab.href) {
            router.visit(tab.href);
        }
    }

    function closeTab(key: string): void {
        const wasActive = key === activeKey.value;
        const result = removeTab(tabs.value, key, activeKey.value);

        tabs.value = result.tabs;

        if (result.nextActiveKey) {
            activeKey.value = result.nextActiveKey;
            persist();

            if (!wasActive) {
                return;
            }

            if (tabKey(page.url) !== result.nextActiveKey) {
                const nextTab = tabs.value.find(
                    (tab) => tab.key === result.nextActiveKey,
                );

                if (nextTab) {
                    router.visit(nextTab.href);
                }
            }

            return;
        }

        const fallback = createTab(dashboardHref, options.fallbackTitle);

        tabs.value = [fallback];
        activeKey.value = fallback.key;
        persist();

        if (tabKey(page.url) !== fallback.key) {
            router.visit(fallback.href);
        }
    }

    function registerCurrentPage(): void {
        const currentTab = createTab(
            normalizeTabHref(page.url),
            options.getCurrentTitle(),
        );

        tabs.value = upsertTab(tabs.value, currentTab);
        activeKey.value = currentTab.key;
        persist();
    }

    registerCurrentPage();

    onMounted(() => {
        load();
        registerCurrentPage();

        watch(storageKey, () => {
            load();
            registerCurrentPage();
        });
    });
    watch(
        () => page.url,
        () => registerCurrentPage(),
    );
    watch([tabs, activeKey], () => persist(), { deep: true });

    return {
        tabs,
        activeKey,
        openTab,
        activateTab,
        closeTab,
    };
}
