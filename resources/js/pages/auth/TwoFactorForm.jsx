import React, { useState } from 'react';
import * as Checkbox from '@radix-ui/react-checkbox';
import * as Label from '@radix-ui/react-label';
import { Check } from 'lucide-react';
import { inputCls } from '../../components/table/helpers/styles.ts';
import { checkboxClass, labelClass, primaryButtonClass } from './authStyles.jsx';

export function TwoFactorForm({ props, submitting }) {
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

