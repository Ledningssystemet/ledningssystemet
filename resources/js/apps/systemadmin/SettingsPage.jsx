import React from 'react';
import { SystemSettingsForm } from './SystemSettingsForm.tsx';
import { useSystemAdmin } from './HydrationProvider';

export function Component() {
    const { data, csrfToken, updateSettings, updateModule } = useSystemAdmin();
    return <SystemSettingsForm
        {...data.groups.settings}
        action={data.urls.settings}
        modulesAction={`${data.urls.settings}/modules`}
        csrfToken={csrfToken}
        onSaved={updateSettings}
        onModuleChanged={result => { void updateModule(result); }}
    />;
}
