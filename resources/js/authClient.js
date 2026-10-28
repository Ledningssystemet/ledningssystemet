function resolveCsrfToken() {
    const tokenMeta = document.head.querySelector('meta[name="csrf-token"]');
    if (tokenMeta instanceof HTMLMetaElement && tokenMeta.content) {
        return tokenMeta.content;
    }

    const xsrfMatch = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return xsrfMatch ? decodeURIComponent(xsrfMatch[1]) : '';
}

async function logout() {
    const csrfToken = resolveCsrfToken();
    const response = await window.axios.post('/logout', {}, {
        headers: {
            Accept: 'application/json',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
        },
    });

    const redirectTo = response?.data?.redirect_to || '/';
    window.location.assign(redirectTo);
}

window.auth = window.auth || {};
window.auth.logout = logout;
