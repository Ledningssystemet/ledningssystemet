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

function notifyIfSessionExpired(status) {
    if (status === 401 || status === 419) {
        window.dispatchEvent(new CustomEvent(SESSION_EXPIRED_EVENT));
    }
}

window.axios.interceptors.request.use(async (config) => applyAxiosRequestInterceptors(config));

window.axios.interceptors.response.use(
    async (response) => {
        const transformedResponse = await applyAxiosResponseInterceptors(response);
        notifyIfSessionExpired(transformedResponse?.status);

        return transformedResponse;
    },
    async (error) => {
        const status = error?.response?.status;
        const transformedError = await applyAxiosErrorInterceptors(error);

        notifyIfSessionExpired(status);

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

            notifyIfSessionExpired(transformedResponse.status);

            return transformedResponse;
        } catch (error) {
            const transformedError = await applyFetchErrorInterceptors(error, request);

            if (transformedError instanceof Response) {
                notifyIfSessionExpired(transformedError.status);
                return transformedError;
            }

            throw transformedError;
        }
    };
}
