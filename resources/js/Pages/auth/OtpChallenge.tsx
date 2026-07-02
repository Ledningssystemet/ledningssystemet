import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';
import { useTranslations } from '@/hooks/useTranslations';

interface OtpChallengeProps {
    ttlMinutes: number;
    [key: string]: unknown;
    flash?: {
        status?: string | null;
    };
}

export default function OtpChallenge({ ttlMinutes }: OtpChallengeProps) {
    const { t } = useTranslations();
    const { props } = usePage();
    const pageProps = props as unknown as OtpChallengeProps;

    const verifyForm = useForm({
        otp: '',
    });

    const resendForm = useForm({});

    const verify = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        verifyForm.post('/otp/challenge');
    };

    const resend = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        resendForm.post('/otp/resend', {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title={t('auth.otp.title')} />

            <main className="mx-auto max-w-md px-6 py-16">
                <h1 className="text-2xl font-semibold">{t('auth.otp.heading')}</h1>
                <p className="mt-3 text-sm text-gray-700">{t('auth.otp.description', { minutes: ttlMinutes })}</p>

                {pageProps.flash?.status ? (
                    <p className="mt-4 rounded border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800">{pageProps.flash.status}</p>
                ) : null}

                <form onSubmit={verify} className="mt-6 space-y-4 rounded border border-gray-200 bg-white p-6">
                    <div>
                        <label htmlFor="otp" className="block text-sm font-medium">{t('auth.otp.code')}</label>
                        <input
                            id="otp"
                            type="text"
                            name="otp"
                            inputMode="numeric"
                            pattern="[0-9]*"
                            maxLength={6}
                            value={verifyForm.data.otp}
                            onChange={(event) => verifyForm.setData('otp', event.currentTarget.value)}
                            required
                            className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
                        />
                        {verifyForm.errors.otp ? <p className="mt-1 text-sm text-red-600">{verifyForm.errors.otp}</p> : null}
                    </div>

                    <button
                        type="submit"
                        disabled={verifyForm.processing}
                        className="w-full rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-70"
                    >
                        {t('auth.otp.verify')}
                    </button>
                </form>

                <form onSubmit={resend} className="mt-4">
                    <button
                        type="submit"
                        disabled={resendForm.processing}
                        className="text-sm text-blue-700 underline disabled:cursor-not-allowed disabled:opacity-70"
                    >
                        {t('auth.otp.resend')}
                    </button>
                </form>
            </main>
        </>
    );
}

