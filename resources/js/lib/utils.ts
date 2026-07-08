import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

export function formatDate(date: string): string {
    const [year, month, day] = date.split('T')[0].split('-').map(Number);
    const parsed = new Date(year, month - 1, day);
    const monthAbbr = parsed.toLocaleDateString('en-US', { month: 'short' });
    return `${monthAbbr}. ${parsed.getDate()}, ${year}`;
}
