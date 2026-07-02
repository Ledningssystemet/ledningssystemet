import type { AxiosResponse, InternalAxiosRequestConfig } from 'axios';
import type {
    AxiosErrorPluginInterceptor,
    AxiosRequestPluginInterceptor,
    AxiosResponsePluginInterceptor,
    FrontendPluginApi,
    FrontendPluginDefinition,
    FrontendPluginRuntimeConfig,
    PluginFetchInterceptor,
    PluginFetchRequestContext,
    PluginRouteContribution,
    PluginSlotContext,
    PluginSlotContribution,
} from '@/types/plugins';

const slotRegistry = new Map<string, PluginSlotContribution[]>();
const routeRegistry: PluginRouteContribution[] = [];
const axiosRequestInterceptors: AxiosRequestPluginInterceptor[] = [];
const axiosResponseInterceptors: AxiosResponsePluginInterceptor[] = [];
const axiosErrorInterceptors: AxiosErrorPluginInterceptor[] = [];
const fetchInterceptors: PluginFetchInterceptor[] = [];

let initializationPromise: Promise<void> | null = null;
let initialized = false;

function getRuntimeConfig(): FrontendPluginRuntimeConfig {
    return window.__APP_PLUGIN_RUNTIME__ ?? { plugins: [] };
}

function addSlotContribution(slotName: string, contribution: PluginSlotContribution): void {
    const existing = slotRegistry.get(slotName) ?? [];
    const filtered = existing.filter((item) => item.id !== contribution.id);

    filtered.push(contribution);
    filtered.sort((left, right) => left.order - right.order || left.id.localeCompare(right.id));

    slotRegistry.set(slotName, filtered);
}

function createPluginApi(definition: FrontendPluginDefinition): FrontendPluginApi {
    return {
        id: definition.id,
        name: definition.name,
        version: definition.version,
        context: definition.context ?? {},
        meta: definition.meta ?? {},
        registerSlot(slotName, render, options = {}) {
            addSlotContribution(slotName, {
                id: options.id ?? `${definition.id}:${slotName}:${slotRegistry.get(slotName)?.length ?? 0}`,
                order: options.order ?? 10,
                render,
            });
        },
        registerRoute(route) {
            const existingIndex = routeRegistry.findIndex((item) => item.key === route.key || item.path === route.path);

            if (existingIndex >= 0) {
                routeRegistry.splice(existingIndex, 1, route);
                return;
            }

            routeRegistry.push(route);
        },
        registerAxiosRequestInterceptor(interceptor) {
            axiosRequestInterceptors.push(interceptor);
        },
        registerAxiosResponseInterceptor(interceptor) {
            axiosResponseInterceptors.push(interceptor);
        },
        registerAxiosErrorInterceptor(interceptor) {
            axiosErrorInterceptors.push(interceptor);
        },
        registerFetchInterceptor(interceptor) {
            fetchInterceptors.push(interceptor);
        },
    };
}

async function loadPluginModule(definition: FrontendPluginDefinition): Promise<void> {
    try {
        const module = await import(/* @vite-ignore */ definition.specifier);
        const register = module.default ?? module.register;

        if (typeof register === 'function') {
            await register(createPluginApi(definition));
        }
    } catch (error) {
        console.error(`Failed to load frontend plugin [${definition.id}]`, error);
    }
}

export async function initializeFrontendPlugins(): Promise<void> {
    if (initialized) {
        return;
    }

    if (initializationPromise !== null) {
        return initializationPromise;
    }

    initializationPromise = (async () => {
        const runtimeConfig = getRuntimeConfig();

        for (const definition of runtimeConfig.plugins) {
            await loadPluginModule(definition);
        }

        initialized = true;
    })();

    return initializationPromise;
}

export function getPluginSlotContributions(slotName: string): PluginSlotContribution[] {
    return slotRegistry.get(slotName) ?? [];
}

export function getPluginRoutes(): PluginRouteContribution[] {
    return [...routeRegistry].sort((left, right) => left.label.localeCompare(right.label));
}

export async function applyAxiosRequestInterceptors(
    config: InternalAxiosRequestConfig,
): Promise<InternalAxiosRequestConfig> {
    let current = config;

    for (const interceptor of axiosRequestInterceptors) {
        current = await interceptor(current);
    }

    return current;
}

export async function applyAxiosResponseInterceptors(response: AxiosResponse): Promise<AxiosResponse> {
    let current = response;

    for (const interceptor of axiosResponseInterceptors) {
        current = await interceptor(current);
    }

    return current;
}

export async function applyAxiosErrorInterceptors(error: unknown): Promise<unknown> {
    let current = error;

    for (const interceptor of axiosErrorInterceptors) {
        current = await interceptor(current);
    }

    return current;
}

export async function applyFetchBeforeInterceptors(
    request: PluginFetchRequestContext,
): Promise<PluginFetchRequestContext> {
    let current = request;

    for (const interceptor of fetchInterceptors) {
        if (interceptor.before) {
            current = await interceptor.before(current);
        }
    }

    return current;
}

export async function applyFetchAfterInterceptors(
    response: Response,
    request: PluginFetchRequestContext,
): Promise<Response> {
    let current = response;

    for (const interceptor of fetchInterceptors) {
        if (interceptor.after) {
            current = await interceptor.after(current, request);
        }
    }

    return current;
}

export async function applyFetchErrorInterceptors(
    error: unknown,
    request: PluginFetchRequestContext,
): Promise<unknown> {
    let current = error;

    for (const interceptor of fetchInterceptors) {
        if (interceptor.error) {
            current = await interceptor.error(current, request);
        }
    }

    return current;
}

export function createPluginSlotContext(context: PluginSlotContext = {}): PluginSlotContext {
    return context;
}

