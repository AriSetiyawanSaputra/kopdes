// Frontend bootstrap configuration for KOPDES Inertia + Vue 3 SPA

const getCookie = (name) => {
    const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const match = document.cookie.match(new RegExp(`(?:^|; )${escaped}=([^;]*)`));
    return match ? decodeURIComponent(match[1]) : '';
};

window.kopdesCsrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const buildUrlWithParams = (url, params) => {
    if (!params || typeof params !== 'object') {
        return url;
    }

    const searchParams = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            searchParams.append(key, String(value));
        }
    });

    const queryString = searchParams.toString();
    if (!queryString) {
        return url;
    }

    return url.includes('?') ? `${url}&${queryString}` : `${url}?${queryString}`;
};

const request = async (method, url, body = undefined, config = {}) => {
    const finalUrl = buildUrlWithParams(url, config.params);
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(config.headers || {}),
    };

    const xsrfCookie = getCookie('XSRF-TOKEN');
    if (xsrfCookie) {
        headers['X-XSRF-TOKEN'] = xsrfCookie;
    } else {
        const metaToken = window.kopdesCsrfToken();
        if (metaToken) {
            headers['X-CSRF-TOKEN'] = metaToken;
        }
    }

    const fetchOptions = {
        method,
        headers,
        credentials: 'same-origin',
    };

    if (body !== undefined && method !== 'GET' && method !== 'HEAD') {
        headers['Content-Type'] = 'application/json';
        fetchOptions.body = JSON.stringify(body);
    }

    const response = await window.fetch(finalUrl, fetchOptions);
    const contentType = response.headers.get('content-type') || '';
    const data = contentType.includes('application/json')
        ? await response.json().catch(() => ({}))
        : await response.text().catch(() => '');

    if (!response.ok) {
        const error = new Error(
            (data && typeof data === 'object' && data.message) ||
                `HTTP Error ${response.status}`,
        );
        error.response = {
            status: response.status,
            data,
            headers: response.headers,
        };
        throw error;
    }

    return {
        status: response.status,
        data,
        headers: response.headers,
    };
};

// Provide an Axios-compatible JSON HTTP client using native Fetch + Laravel CSRF support
window.axios = {
    get: (url, config = {}) => request('GET', url, undefined, config),
    post: (url, data = {}, config = {}) => request('POST', url, data, config),
    put: (url, data = {}, config = {}) => request('PUT', url, data, config),
    patch: (url, data = {}, config = {}) => request('PATCH', url, data, config),
    delete: (url, config = {}) => request('DELETE', url, undefined, config),
};
