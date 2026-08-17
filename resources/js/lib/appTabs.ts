export type AppTab = {
    key: string;
    title: string;
    href: string;
};

export type TabRemovalResult = {
    tabs: AppTab[];
    nextActiveKey: string | null;
};

const tabUrlBase = 'http://auc-admin.local';

export function normalizeTabHref(href: string): string {
    const url = new URL(href, tabUrlBase);

    return `${url.pathname || '/'}${url.search}${url.hash}`;
}

export function tabKey(href: string): string {
    return new URL(href, tabUrlBase).pathname || '/';
}

export function createTab(href: string, title: string): AppTab {
    const normalizedHref = normalizeTabHref(href);

    return {
        key: tabKey(normalizedHref),
        title: title.trim() || '未命名页面',
        href: normalizedHref,
    };
}

export function upsertTab(tabs: AppTab[], tab: AppTab): AppTab[] {
    const existingIndex = tabs.findIndex((item) => item.key === tab.key);

    if (existingIndex === -1) {
        return [...tabs, tab];
    }

    return tabs.map((item, index) =>
        index === existingIndex ? { ...item, ...tab } : item,
    );
}

export function removeTab(
    tabs: AppTab[],
    key: string,
    activeKey: string,
): TabRemovalResult {
    const index = tabs.findIndex((item) => item.key === key);

    if (index === -1) {
        return { tabs, nextActiveKey: activeKey };
    }

    const nextTabs = tabs.filter((item) => item.key !== key);

    if (key !== activeKey) {
        return { tabs: nextTabs, nextActiveKey: activeKey };
    }

    return {
        tabs: nextTabs,
        nextActiveKey: nextTabs[index - 1]?.key ?? nextTabs[index]?.key ?? null,
    };
}
