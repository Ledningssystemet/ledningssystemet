import React, { useState } from 'react';
import * as Checkbox from '@radix-ui/react-checkbox';
import * as Label from '@radix-ui/react-label';
import { Check } from 'lucide-react';
import { checkboxClass, eyeClosedIcon, eyeOpenIcon, inputClass, labelClass, outlineButtonClass, passwordToggleClass, primaryButtonClass } from './authStyles.jsx';

export function LoginForm({ props, submitting, onShowPasswordReset }) {
    const passwordLoginEnabled = !!props.passwordLoginEnabled;
    const ssoProviders = Array.isArray(props.ssoProviders) ? props.ssoProviders : [];
    const [showPassword, setShowPassword] = useState(false);

    return (
        <>
            {passwordLoginEnabled && (
                <>
                    <div className="mb-4">
                        <Label.Root className={labelClass} htmlFor="email">{props.texts.email}</Label.Root>
                        <input className={inputClass} id="email" name="email" type="email" required autoComplete="username" placeholder="name@company.com" />
                    </div>
                    <div className="mb-4">
                        <div className="mb-1 flex items-center justify-between">
                            <Label.Root className={labelClass} htmlFor="password">{props.texts.password}</Label.Root>
                            <button type="button" className="mb-1 pw-reset border-0 p-0 text-xs font-medium text-muted-foreground hover:text-foreground hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" onClick={onShowPasswordReset}>
                                {props.texts.forgotPassword}
                            </button>
                        </div>
                        <div className="relative">
                            <input className={`${inputClass} pr-11`} id="password" name="password" type={showPassword ? 'text' : 'password'} required autoComplete="current-password" placeholder="••••••••••••" />
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
                        <a key={provider.name} className={`${outlineButtonClass} w-full justify-center text-foreground no-underline`} href={provider.url}>
                            {provider.buttonText}
                        </a>
                    ))}
                </div>
            )}
        </>
    );
}
