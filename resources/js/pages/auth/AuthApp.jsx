import React, { useEffect, useMemo, useState } from 'react';
import { AlertList } from './AlertList.jsx';
import { LoginForm } from './LoginForm.jsx';
import { MfaEnforcementForm } from './MfaEnforcementForm.jsx';
import { PasswordResetForm } from './PasswordResetForm.jsx';
import { TwoFactorForm } from './TwoFactorForm.jsx';
import { buildFormPayload, flattenErrors } from './authHelpers.js';
import { warningClass } from './authStyles.jsx';
import '../../../css/utilities.css';
import './AuthApp.css';

export function AuthApp(props) {
    const [keepAliveOk, setKeepAliveOk] = useState(true);
    const [errors, setErrors] = useState([]);
    const [status, setStatus] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [screen, setScreen] = useState(props.screen);
    const csrfToken = useMemo(() => {
        const tokenMeta = document.head.querySelector('meta[name="csrf-token"]');
        if (tokenMeta instanceof HTMLMetaElement && tokenMeta.content) {
            return tokenMeta.content;
        }

        return '';
    }, []);

    const activeProps = useMemo(() => {
        if (screen !== 'password-reset' || props.screen === 'password-reset') {
            return props;
        }

        return {
            ...props,
            screen: 'password-reset',
            pageClass: 'passwordreset',
            formAction: props.passwordResetRequestAction || props.formAction,
            resettingPassword: false,
            logoSrc: props.passwordResetLogoSrc || props.logoSrc,
            texts: {
                ...props.texts,
                email: props.passwordResetTexts?.email || props.texts.email,
                password: props.passwordResetTexts?.password || props.texts.password,
                repeatPassword: props.passwordResetTexts?.repeatPassword || props.texts.repeatPassword,
                resetPassword: props.passwordResetTexts?.resetPassword || props.texts.resetPassword,
                genericError: props.passwordResetTexts?.genericError || props.texts.genericError,
                keepAliveWarning: props.passwordResetTexts?.keepAliveWarning || props.texts.keepAliveWarning,
            },
        };
    }, [props, screen]);

    const handleSubmit = async (event) => {
        event.preventDefault();

        // The MFA enforcement screen manages its own submission (start/confirm
        // buttons) and must never be triggered by the outer form (e.g. pressing
        // Enter in the verification code field), which would otherwise re-run
        // the "enable" action and invalidate the freshly scanned QR secret.
        if (activeProps.screen === 'mfa-enforcement') {
            return;
        }

        const form = event.currentTarget;
        setSubmitting(true);
        setErrors([]);
        setStatus('');

        try {
            const response = await window.axios.post(activeProps.formAction, buildFormPayload(form), {
                headers: {
                    Accept: 'application/json',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
            });

            if (activeProps.screen === 'login' && activeProps.mfaEnabled && response?.data?.two_factor) {
                window.location.assign('/two-factor-challenge');
                return;
            }

            if (activeProps.screen === 'password-reset') {
                const message = response?.data?.message;
                if (message) {
                    setStatus(message);
                }

                if (activeProps.resettingPassword) {
                    window.location.assign('/login');
                    return;
                }

                return;
            }

            window.location.assign(props.redirectUrl || '/');
        } catch (error) {
            if (window.axios.isAxiosError(error)) {
                const responseErrors = flattenErrors(error.response?.data?.errors);
                if (responseErrors.length > 0) {
                    setErrors(responseErrors);
                } else if (error.response?.data?.message) {
                    setErrors([error.response.data.message]);
                } else {
                    setErrors([activeProps.texts.genericError]);
                }
            } else {
                setErrors([activeProps.texts.genericError]);
            }
        } finally {
            setSubmitting(false);
        }
    };

    useEffect(() => {
        if (props.status) {
            setStatus(props.status);
        }
    }, [props.status]);

    useEffect(() => {
        if (!activeProps.keepAliveUrl || !activeProps.keepAliveIntervalMs) return undefined;

        const timer = window.setInterval(async () => {
            try {
                await window.axios.post(activeProps.keepAliveUrl, {}, csrfToken ? { headers: { 'X-CSRF-TOKEN': csrfToken } } : undefined);
                setKeepAliveOk(true);
            } catch (e) {
                setKeepAliveOk(false);
            }
        }, activeProps.keepAliveIntervalMs);

        return () => window.clearInterval(timer);
    }, [activeProps.keepAliveIntervalMs, activeProps.keepAliveUrl, csrfToken]);

    return (
        <div className="ls-auth">
        <div className={`ls-auth ${activeProps.pageClass || 'login'} grid min-h-screen grid-cols-1 bg-muted font-brand min-[992px]:grid-cols-[1.05fr_1fr]`}>
            <aside className="relative flex flex-col justify-center bg-[#383838] px-6 py-10 min-[992px]:p-14">
                <div className="glow glow-1" aria-hidden="true"></div>
                <div className="glow glow-2" aria-hidden="true"></div>
                <div className="relative flex items-center gap-2.5">
                    <a className="inline-flex" href="https://ledningssystemet.se" target="_blank" rel="noopener noreferrer">
                        <img src={activeProps.logoSrc} alt="Ledningssystemet" className="max-h-14 w-auto object-contain" />
                    </a>
                </div>
                <div className="relative mt-10 hidden max-w-md min-[992px]:block">
                    <p className="inline-flex items-center gap-2 rounded-md border border-white/15 px-3 py-1 text-xs font-medium text-[#d1d5db]">
                        Secure access to your management system
                    </p>
                    <h2 className="mb-0 mt-6">
                        Simplify compliance.<span className="block">Accelerate growth.</span>
                    </h2>
                    <ul className="mt-6 list-none space-y-5 p-0">
                        <li className="flex gap-3.5">
                            <span className="flex h-9 w-9 flex-none items-center justify-center rounded-md border border-white/15 text-[#f8f8f8]">✓</span>
                            <div>
                                <p className="m-0 text-sm font-semibold text-[#f8f8f8]">ISO 9001, ISO 14001, ISO/IEC 27001 and more</p>
                                <p className="m-0 text-sm text-[#d1d5db]">An integrated management system for full compliance.</p>
                            </div>
                        </li>
                        <li className="flex gap-3.5">
                            <span className="flex h-9 w-9 flex-none items-center justify-center rounded-md border border-white/15 text-[#f8f8f8]">🔒</span>
                            <div>
                                <p className="m-0 text-sm font-semibold text-[#f8f8f8]">Secure access</p>
                                <p className="m-0 text-sm text-[#d1d5db]">SSO, MFA, and detailed access logs for every sign-in.</p>
                            </div>
                        </li>
                        <li className="flex gap-3.5">
                            <span className="flex h-9 w-9 flex-none items-center justify-center rounded-md border border-white/15 text-[#f8f8f8]">🏢</span>
                            <div>
                                <p className="m-0 text-sm font-semibold text-[#f8f8f8]">Built for business</p>
                                <p className="m-0 text-sm text-[#d1d5db]">Role-based access, audit trails, and EU data storage.</p>
                            </div>
                        </li>
                    </ul>
                </div>
                <p className="relative mt-10 hidden text-xs text-[#d1d5db] min-[992px]:block">© {new Date().getFullYear()} Ledningssystemet.se</p>
            </aside>
            <main className="flex items-center justify-center px-5 py-12 sm:px-10">
                <div className="w-full max-w-md">
                    <form method="POST" action={activeProps.formAction} onSubmit={handleSubmit}>
                        <input type="hidden" name="_token" value={csrfToken} />
                        <div className="rounded-lg border bg-card p-5 shadow-sm sm:p-8">
                            <h1 className="m-0 font-heading text-lg font-semibold">
                                {activeProps.screen === 'two-factor' ? 'Two-step verification' : activeProps.screen === 'password-reset' ? (activeProps.resettingPassword ? 'Choose a new password' : 'Reset password') : activeProps.screen === 'mfa-enforcement' ? 'Multi-factor authentication required' : 'Log in'}
                            </h1>
                            <p className="mb-0 mt-2 text-sm text-muted-foreground">
                                {activeProps.screen === 'two-factor' ? 'Confirm your sign-in to continue.' : activeProps.screen === 'password-reset' ? (activeProps.resettingPassword ? 'The password must meet your organization\'s password policy.' : 'Enter your email address and we\'ll send a reset link.') : activeProps.screen === 'mfa-enforcement' ? 'Your organization requires MFA before you can continue.' : 'Use your organization account to continue.'}
                            </p>
                            <div className="mt-6">
                                {!keepAliveOk && <div className={warningClass} role="alert">{activeProps.texts.keepAliveWarning}</div>}
                                <AlertList errors={errors} status={status} />
                                {activeProps.screen === 'login' && activeProps.mfaEnabled && !activeProps.passwordLoginEnabled && activeProps.ssoProviders?.length === 0 && (
                                    <div className={warningClass} role="alert">
                                        MFA enforcement requires a configured sign-in method.
                                    </div>
                                )}
                                {activeProps.screen === 'login' && <LoginForm props={activeProps} submitting={submitting} onShowPasswordReset={() => { setErrors([]); setStatus(''); setScreen('password-reset'); }} />}
                                {activeProps.screen === 'password-reset' && <PasswordResetForm props={activeProps} submitting={submitting} />}
                                {activeProps.mfaEnabled && activeProps.screen === 'two-factor' && <TwoFactorForm props={activeProps} submitting={submitting} />}
                                {activeProps.mfaEnabled && activeProps.screen === 'mfa-enforcement' && <MfaEnforcementForm props={activeProps} />}
                            </div>
                        </div>
                    </form>
                </div>
            </main>
        </div>
        </div>
    );
}
