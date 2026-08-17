const dateFormatter = new Intl.DateTimeFormat('zh-CN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
});

const dateTimeFormatter = new Intl.DateTimeFormat('zh-CN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hourCycle: 'h23',
});

function formatWith(formatter: Intl.DateTimeFormat, value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const date = value instanceof Date ? value : new Date(String(value));

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return formatter.format(date).replaceAll('/', '-');
}

export function formatDate(value: unknown): string {
    return formatWith(dateFormatter, value);
}

export function formatDateTime(value: unknown): string {
    return formatWith(dateTimeFormatter, value);
}

export function formatNumber(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const number = Number(value);

    return Number.isFinite(number)
        ? new Intl.NumberFormat('zh-CN').format(number)
        : String(value);
}
