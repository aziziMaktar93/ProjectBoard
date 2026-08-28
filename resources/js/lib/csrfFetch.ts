function readCookie(name: string): string {
    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));

    return match ? decodeURIComponent(match[1]) : '';
}

export async function csrfFetch(url: string, options: RequestInit = {}): Promise<Response> {
    const headers = new Headers(options.headers);
    headers.set('Accept', 'application/json');

    if (options.body) {
        headers.set('Content-Type', 'application/json');
    }

    const method = (options.method ?? 'GET').toUpperCase();

    if (method !== 'GET') {
        headers.set('X-XSRF-TOKEN', readCookie('XSRF-TOKEN'));
    }

    return fetch(url, { ...options, headers, credentials: 'same-origin' });
}
