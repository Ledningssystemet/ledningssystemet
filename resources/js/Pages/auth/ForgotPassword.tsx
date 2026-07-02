import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';
import { useTranslations } from '@/hooks/useTranslations';

interface ForgotPasswordPageProps {
    [key: string]: unknown;
    flash?: {
        status?: string | null;
    };
}

export default function ForgotPassword() {
    const { t } = useTranslations();
    const { props } = usePage();
    const pageProps = props as unknown as ForgotPasswordPageProps;

    const form = useForm({
        email: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.post('/forgot-password');
    };

    return (
        <>
            <Head title={t('auth.forgot_password.title')} />

            <main className="mx-auto max-w-md px-6 py-16">
                <h1 className="text-2xl font-semibold">{t('auth.forgot_password.heading')}</h1>

                {pageProps.flash?.status ? (
                    <p className="mt-4 rounded border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800">{pageProps.flash.status}</p>
                ) : null}

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
                            className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
                        />
                        {form.errors.email ? <p className="mt-1 text-sm text-red-600">{form.errors.email}</p> : null}
                    </div>

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="w-full rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-70"
                    >
                        {t('auth.forgot_password.send_link')}
                    </button>

                    <Link href="/login" className="inline-block text-sm text-blue-700 underline">
                        {t('auth.forgot_password.back_to_login')}
                    </Link>
                </form>
            </main>
        </>
    );
}

