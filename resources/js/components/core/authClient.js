function resolveCsrfToken() {
    const tokenMeta = document.head.querySelector('meta[name="csrf-token"]');
    if (tokenMeta instanceof HTMLMetaElement && tokenMeta.content) {
        return tokenMeta.content;
    }

    return '';
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
