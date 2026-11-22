import React, { useState } from 'react';
import * as Label from '@radix-ui/react-label';
import { inputCls } from '../../components/table/helpers/styles.ts';
import { AlertList } from './AlertList.jsx';
import { flattenErrors } from './authHelpers.js';
import { labelClass, primaryButtonClass, warningClass } from './authStyles.jsx';

export function MfaEnforcementForm({ props }) {
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

