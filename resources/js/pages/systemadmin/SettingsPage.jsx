import React from 'react';
import { useRouteLoaderData } from 'react-router-dom';
import { SystemSettingsForm } from '../settings/SystemSettingsForm';

export function Component() {
    const props = useRouteLoaderData('systemadmin-root');
    return <SystemSettingsForm {...props} />;
}
