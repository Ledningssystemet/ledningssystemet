import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';
import { useTranslations } from '@/hooks/useTranslations';

interface LoginPageProps {
    showPasswordForm: boolean;
    showOauthButton: boolean;
    mfaEnabled: boolean;
    mfaEnforced: boolean;
    [key: string]: unknown;
    flash?: {
        status?: string | null;
        oauth_error?: string | null;
    };
}

export default function Login() {
    const { t } = useTranslations();
    const { props } = usePage();
    const { showPasswordForm, showOauthButton, mfaEnabled, mfaEnforced, flash } = props as unknown as LoginPageProps;

    const form = useForm({
        email: '',
        password: '',
        remember: false,
        use_otp: false,
    });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.post('/login', {
            onFinish: () => form.reset('password'),
        });
    };

    return (
        <>
            <Head title={t('auth.login.title')} />

            <main className="mx-auto max-w-md px-6 py-16">
                <h1 className="text-2xl font-semibold">{t('auth.login.title')}</h1>

                {flash?.status ? (
                    <p className="mt-4 rounded border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800">{flash.status}</p>
                ) : null}

                {flash?.oauth_error ? (
                    <p className="mt-4 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">{flash.oauth_error}</p>
                ) : null}

                {showOauthButton ? (
                    <a
                        href="/oauth/workplace/redirect"
                        className="mt-6 inline-block w-full rounded bg-blue-700 px-4 py-2 text-center text-sm font-medium text-white"
                    >
                        {t('auth.login.workplace_account')}
                    </a>
                ) : null}

                {showPasswordForm ? (
                    <form onSubmit={submit} className="mt-6 space-y-4 rounded border border-gray-200 bg-white p-6">
                        <div>
                            <label htmlFor="email" className="block text-sm font-medium">{t('auth.login.email')}</label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value={form.data.email}
                                onChange={(event) => form.setData('email', event.currentTarget.value)}
                                required
                                autoFocus
                                className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
                            />
                            {form.errors.email ? <p className="mt-1 text-sm text-red-600">{form.errors.email}</p> : null}
                        </div>

                        <div>
                            <label htmlFor="password" className="block text-sm font-medium">{t('auth.login.password')}</label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                value={form.data.password}
                                onChange={(event) => form.setData('password', event.currentTarget.value)}
                                required
                                className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
                            />
                            {form.errors.password ? <p className="mt-1 text-sm text-red-600">{form.errors.password}</p> : null}
                        </div>

                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                name="remember"
                                checked={form.data.remember}
                                onChange={(event) => form.setData('remember', event.currentTarget.checked)}
                            />
                            {t('auth.login.remember_me')}
                        </label>

                        {mfaEnabled && !mfaEnforced ? (
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="use_otp"
                                    checked={form.data.use_otp}
                                    onChange={(event) => form.setData('use_otp', event.currentTarget.checked)}
                                />
                                {t('auth.login.use_otp')}
                            </label>
                        ) : null}

                        {mfaEnforced ? (
                            <p className="rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                {t('auth.login.mfa_required')}
                            </p>
                        ) : null}

                        <button
                            type="submit"
                            disabled={form.processing}
                            className="w-full rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-70"
                        >
                            {t('auth.login.submit')}
                        </button>

                        <Link href="/forgot-password" className="inline-block text-sm text-blue-700 underline">
                            {t('auth.login.forgot_password')}
                        </Link>
                    </form>
                ) : null}
            </main>
        </>
    );
}

