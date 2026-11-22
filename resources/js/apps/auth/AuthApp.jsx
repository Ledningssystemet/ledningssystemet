import React, { useEffect, useMemo, useState } from 'react';
import * as Checkbox from '@radix-ui/react-checkbox';
import * as Label from '@radix-ui/react-label';
import { Check } from 'lucide-react';
import { btnOutline, btnPrimary, cbCls, inputCls } from '../../components/table/helpers/styles';
import '../../components/table/css/table.css';
import '../../components/table/css/table.utilities.css';

const labelClass = 'mb-1 block text-sm';
const primaryButtonClass = `${btnPrimary} w-full justify-center disabled:cursor-not-allowed`;
const checkboxClass = `${cbCls} flex-none p-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring`;
const warningClass = 'mb-4 rounded-md border bg-badge-warning px-3 py-2 text-sm text-badge-warning-foreground';
const passwordToggleClass = 'absolute inset-y-0 right-0 flex w-9 items-center justify-center rounded-md border-0 text-muted-foreground hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

function AlertList({ errors = [], status = '' }) {
    return (
        <>
            {errors.length > 0 && (
                <div className={warningClass} role="alert">
                    <ul className="m-0 list-none space-y-1 p-0">
                        {errors.map((error, idx) => <li key={idx}>{error}</li>)}
                    </ul>
                </div>
            )}
            {status && (
                <div className="mb-4 rounded-md border bg-badge-info px-3 py-2 text-sm text-badge-info-foreground" role="status">
                    {status}
                </div>
            )}
        </>
    );
}

const eyeOpenIcon = (
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
        <circle cx="12" cy="12" r="3" />
    </svg>
);

const eyeClosedIcon = (
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M10.7 5.1A10.6 10.6 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-3.2 4.2M6.6 6.6A18 18 0 0 0 2 12s3.5 7 10 7a10.5 10.5 0 0 0 4.5-1" />
        <path d="m2 2 20 20" />
        <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
    </svg>
);

function flattenErrors(errorBag) {
    if (!errorBag || typeof errorBag !== 'object') {
        return [];
    }

    return Object.values(errorBag).flatMap((messages) => (
        Array.isArray(messages) ? messages : [messages]
    )).filter(Boolean);
}

function buildFormPayload(form) {
    const formData = new FormData(form);
    const payload = {};

    for (const [key, value] of formData.entries()) {
        payload[key] = value;
    }

    return payload;
}

function LoginForm({ props, submitting, onShowPasswordReset }) {
    const passwordLoginEnabled = !!props.passwordLoginEnabled;
    const ssoProviders = Array.isArray(props.ssoProviders) ? props.ssoProviders : [];
    const [showPassword, setShowPassword] = useState(false);

    return (
        <>
            {passwordLoginEnabled && (
                <>
                    <div className="mb-4">
                        <Label.Root className={labelClass} htmlFor="email">{props.texts.email}</Label.Root>
                        <input className={inputCls} id="email" name="email" type="email" required autoComplete="username" placeholder="name@company.com" />
                    </div>
                    <div className="mb-4">
                        <div className="mb-1 flex items-center justify-between">
                            <Label.Root className={labelClass} htmlFor="password">{props.texts.password}</Label.Root>
                            <button type="button" className="mb-1 border-0 p-0 text-xs font-medium text-muted-foreground hover:text-foreground hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" onClick={onShowPasswordReset}>
                                {props.texts.forgotPassword}
                            </button>
                        </div>
                        <div className="relative">
                            <input className={`${inputCls} pr-11`} id="password" name="password" type={showPassword ? 'text' : 'password'} required autoComplete="current-password" placeholder="••••••••••••" />
                            <button className={passwordToggleClass} type="button" onClick={() => setShowPassword((prev) => !prev)} aria-label={showPassword ? 'Hide password' : 'Show password'}>
                                {showPassword ? eyeClosedIcon : eyeOpenIcon}
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="remember" value="0" />
                    <div className="mb-4 flex items-center gap-2">
                        <Checkbox.Root className={checkboxClass} id="remember" name="remember" value="1">
                            <Checkbox.Indicator><Check size={12} aria-hidden="true" /></Checkbox.Indicator>
                        </Checkbox.Root>
                        <Label.Root className="cursor-pointer text-sm text-muted-foreground" htmlFor="remember">{props.texts.rememberMe}</Label.Root>
                    </div>
                    <button className={primaryButtonClass} type="submit" disabled={submitting}>{props.texts.signIn}</button>
                </>
            )}
            {passwordLoginEnabled && ssoProviders.length > 0 && (
                <div className="my-5 flex items-center gap-3 text-xs text-muted-foreground">
                    <span className="h-px flex-1 bg-border" aria-hidden="true" />
                    {props.texts.or}
                    <span className="h-px flex-1 bg-border" aria-hidden="true" />
                </div>
            )}
            {ssoProviders.length > 0 && (
                <div className="grid gap-2">
                    {ssoProviders.map((provider) => (
                        <a key={provider.name} className={`${btnOutline} w-full justify-center text-foreground no-underline`} href={provider.url}>
                            {provider.buttonText}
                        </a>
                    ))}
                </div>
            )}
        </>
    );
}

function MfaEnforcementForm({ props }) {
    const [setupStarted, setSetupStarted] = useState(false);
    const [verificationCode, setVerificationCode] = useState('');
    const [qrCodeSvg, setQrCodeSvg] = useState('');
    const [recoveryCodes, setRecoveryCodes] = useState([]);
    const [loadingSetup, setLoadingSetup] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [localErrors, setLocalErrors] = useState([]);
    const canConfirm = verificationCode.trim().length === 6;

    const startSetup = async () => {
        setLoadingSetup(true);
        setLocalErrors([]);

        try {
            await window.axios.post(props.formAction, {}, {
                headers: {
                    Accept: 'application/json',
                },
            });

            const [qrResponse, recoveryResponse] = await Promise.all([
                window.axios.get(props.qrCodeUrl, { headers: { Accept: 'application/json' } }),
                window.axios.get(props.recoveryCodesUrl, { headers: { Accept: 'application/json' } }),
            ]);

            setQrCodeSvg(qrResponse?.data?.svg || '');
            setRecoveryCodes(Array.isArray(recoveryResponse?.data) ? recoveryResponse.data : []);
            setSetupStarted(true);
        } catch (error) {
            if (window.axios.isAxiosError(error)) {
                const responseErrors = flattenErrors(error.response?.data?.errors);
                if (responseErrors.length > 0) {
                    setLocalErrors(responseErrors);
                } else if (error.response?.data?.message) {
                    setLocalErrors([error.response.data.message]);
                } else {
                    setLocalErrors([props.texts.genericError]);
                }
            } else {
                setLocalErrors([props.texts.genericError]);
            }
        } finally {
            setLoadingSetup(false);
        }
    };

    const confirmSetup = async () => {
        setConfirming(true);
        setLocalErrors([]);

        try {
            await window.axios.post(props.confirmAction, { code: verificationCode }, {
                headers: {
                    Accept: 'application/json',
                },
            });

            window.location.assign('/');
        } catch (error) {
            if (window.axios.isAxiosError(error)) {
                const responseErrors = flattenErrors(error.response?.data?.errors);
                if (responseErrors.length > 0) {
                    setLocalErrors(responseErrors);
                } else if (error.response?.data?.message) {
                    setLocalErrors([error.response.data.message]);
                } else {
                    setLocalErrors([props.texts.genericError]);
                }
            } else {
                setLocalErrors([props.texts.genericError]);
            }
        } finally {
            setConfirming(false);
        }
    };

    return (
        <>
            <div className={warningClass} role="alert">
                {props.texts.message}
            </div>
            <AlertList errors={localErrors} status={''} />
            {!setupStarted ? (
                <button className={primaryButtonClass} type="button" onClick={startSetup} disabled={loadingSetup}>
                    {props.texts.action}
                </button>
            ) : (
                <>
                    <div className="mb-3 block rounded-md border bg-muted p-3">
                        <p className="mb-2 font-semibold">{props.texts.scanQr}</p>
                        {qrCodeSvg ? <div dangerouslySetInnerHTML={{ __html: qrCodeSvg }} /> : null}
                    </div>
                    <div className="mb-3 block rounded-md border bg-muted p-3">
                        <p className="mb-2 font-semibold">{props.texts.recoveryCodes}</p>
                        <p className="mb-2 text-sm text-muted-foreground">{props.texts.saveRecoveryCodes}</p>
                        <ul className="mb-0 list-disc space-y-1 pl-5 font-mono text-sm">
                            {recoveryCodes.map((code, idx) => <li key={idx}>{code}</li>)}
                        </ul>
                    </div>
                    <div className="mb-3">
                        <Label.Root className={labelClass} htmlFor="mfa-verification-code">{props.texts.verificationCode}</Label.Root>
                        <input
                            className={inputCls}
                            id="mfa-verification-code"
                            name="mfa_verification_code"
                            type="text"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            placeholder="000000"
                            maxLength={6}
                            value={verificationCode}
                            onChange={(event) => setVerificationCode(event.target.value.replace(/\D/g, '').slice(0, 6))}
                        />
                        <p className="mt-2 text-sm text-muted-foreground">{props.texts.enterCode}</p>
                    </div>
                    <button className={primaryButtonClass} type="button" onClick={confirmSetup} disabled={confirming || !canConfirm}>
                        {props.texts.confirm}
                    </button>
                </>
            )}
        </>
    );
}

function PasswordResetForm({ props, submitting }) {
    const resettingPassword = !!props.resettingPassword;
    const [showPassword, setShowPassword] = useState(false);
    return (
        <>
            {resettingPassword ? (
                <>
                    <input type="hidden" name="email" value={props.email || ''} />
                    <input type="hidden" name="token" value={props.token || ''} />
                    <div className="mb-4">
                        <Label.Root className={labelClass} htmlFor="new-password">{props.texts.password}</Label.Root>
                        <div className="relative">
                            <input className={`${inputCls} pr-11`} id="new-password" name="password" type={showPassword ? 'text' : 'password'} required autoComplete="new-password" />
                            <button className={passwordToggleClass} type="button" onClick={() => setShowPassword((prev) => !prev)} aria-label={showPassword ? 'Hide password' : 'Show password'}>
                                {showPassword ? eyeClosedIcon : eyeOpenIcon}
                            </button>
                        </div>
                    </div>
                    <div className="mb-4">
                        <Label.Root className={labelClass} htmlFor="password-confirm">{props.texts.repeatPassword}</Label.Root>
                        <input className={inputCls} id="password-confirm" name="password_confirmation" type={showPassword ? 'text' : 'password'} required autoComplete="new-password" />
                    </div>
                </>
            ) : (
                <div className="mb-4">
                    <Label.Root className={labelClass} htmlFor="reset-email">{props.texts.email}</Label.Root>
                    <input className={inputCls} id="reset-email" name="email" type="email" required placeholder="name@company.com" />
                </div>
            )}
            <button className={primaryButtonClass} type="submit" disabled={submitting}>{props.texts.resetPassword}</button>
        </>
    );
}

function TwoFactorForm({ props, submitting }) {
    const [code, setCode] = useState('');
    const [recoveryCode, setRecoveryCode] = useState('');
    const [recoveryMode, setRecoveryMode] = useState(false);
    const [trustDevice, setTrustDevice] = useState(false);
    const codeComplete = code.trim().length === 6;
    const recoveryComplete = recoveryCode.trim().length >= 8;
    const canSubmit = recoveryMode ? recoveryComplete : codeComplete;

    return (
        <>
            <p className="mb-4 mt-2 text-sm text-muted-foreground">{recoveryMode ? 'Enter one of your saved recovery codes.' : props.texts.codeInstruction}</p>
            <div className="mb-4 flex items-center gap-3 rounded-md border bg-muted p-3">
                <span className="flex h-9 w-9 items-center justify-center rounded-md bg-card text-primary">{recoveryMode ? '🔑' : '📱'}</span>
                <div className="text-xs">
                    <p className="m-0 font-semibold">Verification</p>
                    <p className="m-0 text-muted-foreground">{recoveryMode ? 'Recovery code (single-use)' : 'Authenticator app'}</p>
                </div>
            </div>
            {!recoveryMode ? (
                <div className="mb-3">
                    <Label.Root className={labelClass} htmlFor="code">{props.texts.authenticationCode}</Label.Root>
                    <input
                        className={inputCls}
                        id="code"
                        name="code"
                        type="text"
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        placeholder="000000"
                        maxLength={6}
                        value={code}
                        onChange={(event) => setCode(event.target.value.replace(/\D/g, '').slice(0, 6))}
                    />
                </div>
            ) : (
                <div className="mb-3">
                    <Label.Root className={labelClass} htmlFor="recovery_code">{props.texts.recoveryCode}</Label.Root>
                    <input className={`${inputCls} font-mono`} id="recovery_code" name="recovery_code" type="text" autoComplete="one-time-code" value={recoveryCode} onChange={(event) => setRecoveryCode(event.target.value)} />
                </div>
            )}
            <div className="mb-4 flex items-center gap-2">
                <Checkbox.Root
                    className={checkboxClass}
                    id="trust_device"
                    name="trust_device"
                    value="1"
                    checked={trustDevice}
                    onCheckedChange={(checked) => setTrustDevice(checked === true)}
                >
                    <Checkbox.Indicator><Check size={12} aria-hidden="true" /></Checkbox.Indicator>
                </Checkbox.Root>
                <Label.Root className="cursor-pointer text-sm text-muted-foreground" htmlFor="trust_device">
                    {props.texts.trustDevice}
                </Label.Root>
            </div>
            <div className="mb-3 mt-3 flex items-center justify-between text-xs">
                <button type="button" className="border-0 p-0 text-xs font-medium text-foreground no-underline hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" onClick={() => setRecoveryMode((prev) => !prev)}>
                    {recoveryMode ? 'Use authenticator app' : props.texts.orUseRecoveryCode}
                </button>
            </div>
            <button className={primaryButtonClass} type="submit" disabled={submitting || !canSubmit}>{props.texts.verify}</button>
        </>
    );
}

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

        const xsrfMatch = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
        return xsrfMatch ? decodeURIComponent(xsrfMatch[1]) : '';
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
        <div className={`ls-table ${activeProps.pageClass || 'login'} grid min-h-screen grid-cols-1 bg-muted font-sans min-[992px]:grid-cols-[1.05fr_1fr]`}>
            <aside className="relative flex flex-col justify-center bg-[#383838] px-6 py-10 min-[992px]:p-14">
                <div className="relative flex items-center gap-2.5">
                    <a className="inline-flex" href="https://ledningssystemet.se" target="_blank" rel="noopener noreferrer">
                        <img src={activeProps.logoSrc} alt="Ledningssystemet" className="max-h-14 w-auto object-contain" />
                    </a>
                </div>
                <div className="relative mt-10 hidden max-w-md min-[992px]:block">
                    <p className="inline-flex items-center gap-2 rounded-md border border-white/15 px-3 py-1 text-xs font-medium text-[#d1d5db]">
                        Secure access to your management system
                    </p>
                    <h2 className="mb-0 mt-6 text-2xl font-semibold text-[#f8f8f8]">
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
                <p className="relative mt-10 hidden text-xs text-[#d1d5db] min-[992px]:block">© 2026 Ledningssystemet.se · EU data storage</p>
            </aside>
            <main className="flex items-center justify-center px-5 py-12 sm:px-10">
                <div className="w-full max-w-md">
                    <form method="POST" action={activeProps.formAction} onSubmit={handleSubmit}>
                        <input type="hidden" name="_token" value={csrfToken} />
                        <div className="rounded-lg border bg-card p-5 shadow-sm sm:p-8">
                            <h1 className="m-0 text-lg font-semibold">
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
