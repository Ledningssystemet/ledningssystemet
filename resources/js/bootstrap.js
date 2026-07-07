import axios from 'axios';
import {
    applyAxiosErrorInterceptors,
    applyAxiosRequestInterceptors,
    applyAxiosResponseInterceptors,
    applyFetchAfterInterceptors,
    applyFetchBeforeInterceptors,
    applyFetchErrorInterceptors,
} from './plugins/runtime';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

const SESSION_EXPIRED_EVENT = 'session:expired';
const SESSION_PING_PATH = '/api/session/ping';

function extractPathname(url) {
    if (!url || typeof url !== 'string') {
        return null;
    }

    try {
        return new URL(url, window.location.origin).pathname;
    } catch {
        return null;
    }
}

function notifyIfSessionExpired(status, url = null) {
    if (status === 419) {
        window.dispatchEvent(new CustomEvent(SESSION_EXPIRED_EVENT));
        return;
    }

    if (status === 401 && extractPathname(url) === SESSION_PING_PATH) {
        window.dispatchEvent(new CustomEvent(SESSION_EXPIRED_EVENT));
    }
}

window.axios.interceptors.request.use(async (config) => applyAxiosRequestInterceptors(config));

window.axios.interceptors.response.use(
    async (response) => {
        const transformedResponse = await applyAxiosResponseInterceptors(response);
        notifyIfSessionExpired(transformedResponse?.status, transformedResponse?.config?.url ?? null);

        return transformedResponse;
    },
    async (error) => {
        const status = error?.response?.status;
        const requestUrl = error?.response?.config?.url ?? error?.config?.url ?? null;
        const transformedError = await applyAxiosErrorInterceptors(error);

        notifyIfSessionExpired(status, requestUrl);

        return Promise.reject(transformedError);
    },
);

const nativeFetch = typeof window.fetch === 'function' ? window.fetch.bind(window) : null;

if (nativeFetch) {
    window.fetch = async (input, init) => {
        const request = await applyFetchBeforeInterceptors({ input, init });
        const requestInit = {
            ...request.init,
            credentials: request.init?.credentials ?? 'include',
        };

        try {
            const response = await nativeFetch(request.input, requestInit);
            const transformedResponse = await applyFetchAfterInterceptors(response, request);

            notifyIfSessionExpired(
                transformedResponse.status,
                typeof request.input === 'string' ? request.input : request.input?.url ?? null,
            );

            return transformedResponse;
        } catch (error) {
            const transformedError = await applyFetchErrorInterceptors(error, request);

            if (transformedError instanceof Response) {
                notifyIfSessionExpired(
                    transformedError.status,
                    typeof request.input === 'string' ? request.input : request.input?.url ?? null,
                );
                return transformedError;
            }

            throw transformedError;
        }
    };
}
