import React, { useEffect, useMemo, useState } from 'react';

function AlertList({ errors = [], status = '' }) {
    return (
        <>
            {errors.length > 0 && (
                <div className="alert alert-warning" role="alert">
                    <ul className="list-unstyled mb-0">
                        {errors.map((error, idx) => <li key={idx}>{error}</li>)}
                    </ul>
                </div>
            )}
            {status && (
                <div className="alert alert-info" role="alert">
                    <ul className="list-unstyled mb-0">{status}</ul>
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
                    <div className="mb-3">
                        <label className="form-label" htmlFor="email">{props.texts.email}</label>
                        <input className="form-control" id="email" name="email" type="email" required autoComplete="username" placeholder="name@company.com" />
                    </div>
                    <div className="mb-3">
                        <div className="d-flex align-items-center justify-content-between">
                            <label className="form-label" htmlFor="password">{props.texts.password}</label>
                            <button type="button" className="small-link border-0 bg-transparent p-0" onClick={onShowPasswordReset}>
                                {props.texts.forgotPassword}
                            </button>
                        </div>
                        <div className="position-relative">
                            <input className="form-control pe-5" id="password" name="password" type={showPassword ? 'text' : 'password'} required autoComplete="current-password" placeholder="••••••••••••" />
                            <button className="pw-toggle" type="button" onClick={() => setShowPassword((prev) => !prev)} aria-label={showPassword ? 'Hide password' : 'Show password'}>
                                {showPassword ? eyeClosedIcon : eyeOpenIcon}
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="remember" value="0" />
                    <div className="form-check mb-3">
                        <input className="form-check-input" type="checkbox" value="1" id="remember" name="remember" />
                        <label className="form-check-label muted" style={{ fontSize: '.875rem' }} htmlFor="remember">{props.texts.rememberMe}</label>
                    </div>
                    <button className="btn btn-brand" type="submit" disabled={submitting}>{props.texts.signIn}</button>
                </>
            )}
            {passwordLoginEnabled && ssoProviders.length > 0 && <div className="divider my-4">{props.texts.or}</div>}
            {ssoProviders.length > 0 && (
                <div className="d-grid gap-2">
                    {ssoProviders.map((provider) => (
                        <a key={provider.name} className="btn btn-ghost-outline" href={provider.url}>
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
            <div className="alert alert-warning" role="alert">
                {props.texts.message}
            </div>
            <AlertList errors={localErrors} status={''} />
            {!setupStarted ? (
                <button className="btn btn-brand" type="button" onClick={startSetup} disabled={loadingSetup}>
                    {props.texts.action}
                </button>
            ) : (
                <>
                    <div className="tile mb-3" style={{ display: 'block' }}>
                        <p className="mb-2 fw-semibold">{props.texts.scanQr}</p>
                        {qrCodeSvg ? <div dangerouslySetInnerHTML={{ __html: qrCodeSvg }} /> : null}
                    </div>
                    <div className="tile mb-3" style={{ display: 'block' }}>
                        <p className="mb-2 fw-semibold">{props.texts.recoveryCodes}</p>
                        <p className="mb-2 muted">{props.texts.saveRecoveryCodes}</p>
                        <ul className="mb-0 ps-3">
                            {recoveryCodes.map((code, idx) => <li key={idx} className="font-monospace">{code}</li>)}
                        </ul>
                    </div>
                    <div className="mb-3">
                        <label className="form-label" htmlFor="mfa-verification-code">{props.texts.verificationCode}</label>
                        <input
                            className="form-control"
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
                        <p className="sub">{props.texts.enterCode}</p>
                    </div>
                    <button className="btn btn-brand" type="button" onClick={confirmSetup} disabled={confirming || !canConfirm}>
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
                    <div className="mb-3">
                        <label className="form-label" htmlFor="new-password">{props.texts.password}</label>
                        <div className="position-relative">
                            <input className="form-control pe-5" id="new-password" name="password" type={showPassword ? 'text' : 'password'} required autoComplete="new-password" />
                            <button className="pw-toggle" type="button" onClick={() => setShowPassword((prev) => !prev)} aria-label={showPassword ? 'Hide password' : 'Show password'}>
                                {showPassword ? eyeClosedIcon : eyeOpenIcon}
                            </button>
                        </div>
                    </div>
                    <div className="mb-3">
                        <label className="form-label" htmlFor="password-confirm">{props.texts.repeatPassword}</label>
                        <input className="form-control" id="password-confirm" name="password_confirmation" type={showPassword ? 'text' : 'password'} required autoComplete="new-password" />
                    </div>
                </>
            ) : (
                <div className="mb-3">
                    <label className="form-label" htmlFor="reset-email">{props.texts.email}</label>
                    <input className="form-control" id="reset-email" name="email" type="email" required placeholder="name@company.com" />
                </div>
            )}
            <button className="btn btn-brand" type="submit" disabled={submitting}>{props.texts.resetPassword}</button>
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
            <p className="sub mb-4">{recoveryMode ? 'Enter one of your saved recovery codes.' : props.texts.codeInstruction}</p>
            <div className="tile mb-4">
                <span className="tile-icon">{recoveryMode ? '🔑' : '📱'}</span>
                <div style={{ fontSize: '.75rem' }}>
                    <p className="mb-0 fw-semibold">Verification</p>
                    <p className="mb-0 muted">{recoveryMode ? 'Recovery code (single-use)' : 'Authenticator app'}</p>
                </div>
            </div>
            {!recoveryMode ? (
                <div className="mb-3">
                    <label className="form-label" htmlFor="code">{props.texts.authenticationCode}</label>
                    <input
                        className="form-control"
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
                    <label className="form-label" htmlFor="recovery_code">{props.texts.recoveryCode}</label>
                    <input className="form-control font-monospace" id="recovery_code" name="recovery_code" type="text" autoComplete="one-time-code" value={recoveryCode} onChange={(event) => setRecoveryCode(event.target.value)} />
                </div>
            )}
            <div className="form-check mb-3">
                <input
                    className="form-check-input"
                    id="trust_device"
                    name="trust_device"
                    type="checkbox"
                    value="1"
                    checked={trustDevice}
                    onChange={(event) => setTrustDevice(event.target.checked)}
                />
                <label className="form-check-label" htmlFor="trust_device" style={{ fontSize: '.8125rem' }}>
                    {props.texts.trustDevice}
                </label>
            </div>
            <div className="d-flex align-items-center justify-content-between mt-3 mb-3" style={{ fontSize: '.75rem' }}>
                <button type="button" className="btn btn-link p-0 text-decoration-none fw-medium" style={{ color: 'var(--fg)', fontSize: '.75rem' }} onClick={() => setRecoveryMode((prev) => !prev)}>
                    {recoveryMode ? 'Use authenticator app' : props.texts.orUseRecoveryCode}
                </button>
            </div>
            <button className="btn btn-brand" type="submit" disabled={submitting || !canSubmit}>{props.texts.verify}</button>
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
        <div className={`auth-grid ${activeProps.pageClass || 'login'}`}>
            <style>{`
                :root{
                  --brand-navy:#0f2f52; --brand-navy-deep:#0a2138; --brand-navy-ink:#061524;
                  --brand-teal:#23a997; --brand-teal-soft:#63c9bb;
                  --on-navy:#f7fafc; --on-navy-muted:#b9c6d3;
                  --bg:#f7f9fb; --card:#ffffff; --fg:#16283f; --muted-fg:#69788c; --border:#e3e8ef;
                  --gradient-brand:linear-gradient(145deg,var(--brand-navy-ink) 0%,var(--brand-navy-deep) 45%,var(--brand-navy) 100%);
                  --gradient-teal:linear-gradient(100deg,var(--brand-teal),var(--brand-teal-soft));
                  --shadow-card:0 1px 2px rgba(6,21,36,.06),0 18px 44px -28px rgba(6,21,36,.35);
                }
                .auth-grid{min-height:100vh;display:grid;grid-template-columns:1fr;background:var(--bg);color:var(--fg);font-family:"Instrument Sans",system-ui,-apple-system,"Segoe UI",sans-serif}
                @media (min-width:992px){.auth-grid{grid-template-columns:1.05fr 1fr}}
                .brand-panel{position:relative;overflow:hidden;background-image:var(--gradient-brand);padding:2.5rem 1.5rem;display:flex;flex-direction:column;justify-content:center}
                @media (min-width:992px){.brand-panel{padding:3.5rem}}
                .brand-panel .glow{position:absolute;border-radius:50%;filter:blur(80px);pointer-events:none}
                .glow-1{top:-8rem;left:-6rem;width:26rem;height:26rem;opacity:.25;background:var(--gradient-teal)}
                .glow-2{right:-8rem;bottom:0;width:30rem;height:30rem;opacity:.15;background:var(--brand-teal)}
                .logo{display:flex;align-items:center;gap:.625rem;position:relative}
                .brand-copy{position:relative;max-width:28rem;margin-top:2.5rem;display:none}
                @media (min-width:992px){.brand-copy,.brand-foot{display:block}}
                .pill{display:inline-flex;align-items:center;gap:.5rem;border:1px solid rgba(247,250,252,.15);background:rgba(247,250,252,.06);color:var(--on-navy-muted);border-radius:999px;padding:.25rem .75rem;font-size:.75rem;font-weight:500}
                .brand-copy h2{color:var(--on-navy);font-size:2.25rem;line-height:1.1;margin:1.5rem 0 0}
                .brand-copy h2 .accent{display:block;color:var(--brand-teal-soft)}
                .hl{display:flex;gap:.875rem;margin-top:1.25rem}
                .hl-icon{flex:0 0 auto;width:2.25rem;height:2.25rem;border-radius:.5rem;border:1px solid rgba(247,250,252,.12);background:rgba(247,250,252,.08);display:flex;align-items:center;justify-content:center;color:var(--brand-teal-soft)}
                .hl-title{color:var(--on-navy);font-size:.875rem;font-weight:600;margin:0}
                .hl-text{color:var(--on-navy-muted);font-size:.875rem;margin:0}
                .brand-foot{position:relative;margin-top:2.5rem;color:var(--on-navy-muted);font-size:.75rem;display:none}
                .form-panel{display:flex;align-items:center;justify-content:center;padding:3rem 1.25rem}
                @media (min-width:576px){.form-panel{padding:3rem 2.5rem}}
                .form-wrap{width:100%;max-width:26rem}
                .auth-card{background:var(--card);border:1px solid var(--border);border-radius:1rem;box-shadow:var(--shadow-card);padding:1.75rem}
                @media (min-width:576px){.auth-card{padding:2rem}}
                .auth-card h1{font-family:"Quicksand",system-ui,sans-serif;font-size:1.5rem;margin:0;font-weight:700}
                .sub{color:var(--muted-fg);font-size:.875rem;margin:.5rem 0 0}
                .form-label{font-size:.875rem;font-weight:500;color:var(--fg);margin-bottom:.375rem}
                .form-control{height:2.75rem;border-color:var(--border);border-radius:.625rem;font-size:.9375rem}
                .form-control:focus{border-color:var(--brand-teal);box-shadow:0 0 0 3px rgba(35,169,151,.18)}
                .btn-brand{height:2.75rem;border-radius:.625rem;background:var(--brand-teal);border:1px solid var(--brand-teal);color:#062a25;font-weight:600;font-size:.875rem;width:100%}
                .btn-brand:hover{background:#1d9686;border-color:#1d9686;color:#062a25}
                .btn-ghost-outline{height:2.75rem;border-radius:.625rem;border:1px solid var(--border);background:#fff;color:var(--fg);font-weight:500;font-size:.875rem;width:100%;display:flex;align-items:center;justify-content:center;gap:.625rem}
                .divider{display:flex;align-items:center;gap:.75rem;color:var(--muted-fg);font-size:.75rem}
                .divider:before,.divider:after{content:"";height:1px;flex:1;background:var(--border)}
                .muted{color:var(--muted-fg)}
                .small-link{font-size:.75rem;font-weight:500;color:var(--muted-fg);text-decoration:none}
                .small-link:hover{color:var(--fg);text-decoration:underline}
                .pw-toggle{position:absolute;top:0;right:0;height:2.75rem;width:2.75rem;border:0;background:transparent;color:var(--muted-fg);display:flex;align-items:center;justify-content:center}
                .tile{display:flex;gap:.875rem;align-items:center;border:1px solid var(--border);background:#f7f9fb;border-radius:.75rem;padding:.75rem .875rem}
                .tile-icon{width:2.25rem;height:2.25rem;border-radius:.5rem;background:#fff;display:flex;align-items:center;justify-content:center;color:var(--brand-teal)}
            `}</style>
            <aside className="brand-panel">
                <div className="glow glow-1" aria-hidden="true" />
                <div className="glow glow-2" aria-hidden="true" />
                <div className="splash-logo">
                    <a href="https://ledningssystemet.se" target={"_blank"} rel="noopener noreferrer">
                        <img src={activeProps.logoSrc} alt="Ledningssystemet" className="logo-mark" />
                    </a>
                </div>
                <div className="brand-copy">
                    <p className="pill">Secure access to your management system</p>
                    <h2>Simplify compliance.<span className="accent">Accelerate growth.</span></h2>
                    <ul className="list-unstyled mt-4 mb-0">
                        <li className="hl"><span className="hl-icon">✓</span><div><p className="hl-title">ISO 9001, ISO 14001, ISO/IEC 27001 and more</p><p className="hl-text">An integrated management system for full compliance.</p></div></li>
                        <li className="hl"><span className="hl-icon">🔒</span><div><p className="hl-title">Secure access</p><p className="hl-text">SSO, MFA, and detailed access logs for every sign-in.</p></div></li>
                        <li className="hl"><span className="hl-icon">🏢</span><div><p className="hl-title">Built for business</p><p className="hl-text">Role-based access, audit trails, and EU data storage.</p></div></li>
                    </ul>
                </div>
                <p className="brand-foot">© 2026 Ledningssystemet.se · EU data storage</p>
            </aside>
            <main className="form-panel">
                <div className="form-wrap">
                        <form method="POST" action={activeProps.formAction} onSubmit={handleSubmit}>
                            <input type="hidden" name="_token" value={csrfToken} />
                            <div className="auth-card">
                                        <h1>{activeProps.screen === 'two-factor' ? 'Two-step verification' : activeProps.screen === 'password-reset' ? (activeProps.resettingPassword ? 'Choose a new password' : 'Reset password') : activeProps.screen === 'mfa-enforcement' ? 'Multi-factor authentication required' : 'Log in'}</h1>
                                        <p className="sub">{activeProps.screen === 'two-factor' ? 'Confirm your sign-in to continue.' : activeProps.screen === 'password-reset' ? (activeProps.resettingPassword ? 'The password must meet your organization\'s password policy.' : 'Enter your email address and we\'ll send a reset link.') : activeProps.screen === 'mfa-enforcement' ? 'Your organization requires MFA before you can continue.' : 'Use your organization account to continue.'}</p>
                                        <div className="mt-4">
                                        {!keepAliveOk && <div className="alert alert-warning" role="alert">{activeProps.texts.keepAliveWarning}</div>}
                                        <AlertList errors={errors} status={status} />
                                        {activeProps.screen === 'login' && activeProps.mfaEnabled && !activeProps.passwordLoginEnabled && activeProps.ssoProviders?.length === 0 && (
                                            <div className="alert alert-warning" role="alert">
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
    );
}
