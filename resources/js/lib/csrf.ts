/**
 * Reads the XSRF-TOKEN cookie Laravel sets on every response, for use in
 * plain `fetch()` calls that bypass Inertia's request handling (which
 * doesn't apply here since these calls don't navigate to a new page).
 */
export function getCsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}
