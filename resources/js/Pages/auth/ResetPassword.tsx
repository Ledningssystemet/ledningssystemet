import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { useTranslations } from '@/hooks/useTranslations';

interface ResetPasswordProps {
    token: string;
    email: string;
}

export default function ResetPassword({ token, email }: ResetPasswordProps) {
    const { t } = useTranslations();

    const form = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.post('/reset-password', {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    };

    return (
        <>
            <Head title={t('auth.reset_password.title')} />

            <main className="mx-auto max-w-md px-6 py-16">
                <h1 className="text-2xl font-semibold">{t('auth.reset_password.heading')}</h1>

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

                    <div>
                        <label htmlFor="password" className="block text-sm font-medium">{t('auth.reset_password.new_password')}</label>
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

                    <div>
                        <label htmlFor="password_confirmation" className="block text-sm font-medium">{t('auth.reset_password.confirm_password')}</label>
                        <input
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            value={form.data.password_confirmation}
                            onChange={(event) => form.setData('password_confirmation', event.currentTarget.value)}
                            required
                            className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
                        />
                    </div>

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="w-full rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-70"
                    >
                        {t('auth.reset_password.save')}
                    </button>
                </form>
            </main>
        </>
    );
}

