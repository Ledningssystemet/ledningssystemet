import React, { useState } from 'react';
import * as Label from '@radix-ui/react-label';
import { eyeClosedIcon, eyeOpenIcon, inputClass, labelClass, passwordToggleClass, primaryButtonClass } from './authStyles.jsx';

export function PasswordResetForm({ props, submitting }) {
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
                            <input className={`${inputClass} pr-11`} id="new-password" name="password" type={showPassword ? 'text' : 'password'} required autoComplete="new-password" />
                            <button className={passwordToggleClass} type="button" onClick={() => setShowPassword((prev) => !prev)} aria-label={showPassword ? 'Hide password' : 'Show password'}>
                                {showPassword ? eyeClosedIcon : eyeOpenIcon}
                            </button>
                        </div>
                    </div>
                    <div className="mb-4">
                        <Label.Root className={labelClass} htmlFor="password-confirm">{props.texts.repeatPassword}</Label.Root>
                        <input className={inputClass} id="password-confirm" name="password_confirmation" type={showPassword ? 'text' : 'password'} required autoComplete="new-password" />
                    </div>
                </>
            ) : (
                <div className="mb-4">
                    <Label.Root className={labelClass} htmlFor="reset-email">{props.texts.email}</Label.Root>
                    <input className={inputClass} id="reset-email" name="email" type="email" required placeholder="name@company.com" />
                </div>
            )}
            <button className={primaryButtonClass} type="submit" disabled={submitting}>{props.texts.resetPassword}</button>
        </>
    );
}
