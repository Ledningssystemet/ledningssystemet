import type { InternalAxiosRequestConfig, AxiosResponse } from 'axios';
import type { ComponentType, ReactNode } from 'react';
import type { AppSectionRoute } from '@/app/routes';

export interface FrontendPluginDefinition {
    id: string;
    name: string;
    version: string;
    specifier: string;
    context: Record<string, unknown>;
    meta: Record<string, unknown>;
}

export interface FrontendPluginRuntimeConfig {
    plugins: FrontendPluginDefinition[];
}

export interface PluginSlotContext {
    route?: AppSectionRoute;
    [key: string]: unknown;
}

export interface PluginSlotContribution {
    id: string;
    order: number;
    render: (context: PluginSlotContext) => ReactNode;
}

export interface PluginRouteContribution extends AppSectionRoute {
    component: ComponentType<{ route: AppSectionRoute }>;
}

export interface PluginFetchRequestContext {
    input: RequestInfo | URL;
    init?: RequestInit;
}

export interface PluginFetchInterceptor {
    before?: (request: PluginFetchRequestContext) => PluginFetchRequestContext | Promise<PluginFetchRequestContext>;
    after?: (response: Response, request: PluginFetchRequestContext) => Response | Promise<Response>;
    error?: (error: unknown, request: PluginFetchRequestContext) => unknown | Promise<unknown>;
}

export type AxiosRequestPluginInterceptor = (
    config: InternalAxiosRequestConfig,
) => InternalAxiosRequestConfig | Promise<InternalAxiosRequestConfig>;

export type AxiosResponsePluginInterceptor = (
    response: AxiosResponse,
) => AxiosResponse | Promise<AxiosResponse>;

export type AxiosErrorPluginInterceptor = (error: unknown) => unknown | Promise<unknown>;

export interface FrontendPluginApi {
    id: string;
    name: string;
    version: string;
    context: Record<string, unknown>;
    meta: Record<string, unknown>;
    registerSlot: (
        slotName: string,
        render: (context: PluginSlotContext) => ReactNode,
        options?: { id?: string; order?: number },
    ) => void;
    registerRoute: (route: PluginRouteContribution) => void;
    registerAxiosRequestInterceptor: (interceptor: AxiosRequestPluginInterceptor) => void;
    registerAxiosResponseInterceptor: (interceptor: AxiosResponsePluginInterceptor) => void;
    registerAxiosErrorInterceptor: (interceptor: AxiosErrorPluginInterceptor) => void;
    registerFetchInterceptor: (interceptor: PluginFetchInterceptor) => void;
}

declare global {
    interface Window {
        __APP_PLUGIN_RUNTIME__?: FrontendPluginRuntimeConfig;
        React?: typeof import('react');
    }
}

