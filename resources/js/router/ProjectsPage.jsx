import React, { useEffect, useState } from 'react';
import { useBlocker, useRouteLoaderData, useLocation } from 'react-router-dom';
import { ProjectsView } from '../apps/projects/index.tsx';
import { t } from '../lib/i18n';

export function Component() {
    const access = useRouteLoaderData('app-root');
    const permissions = React.useMemo(() => ({
        currentUserId: access.currentUserId,
        canCreateProject: access.pages.projects.canCreate,
    }), [access]);
    const location = useLocation();
    const [dirty, setDirty] = useState(false);
    const blocker = useBlocker(dirty);

    useEffect(() => {
        if (blocker.state !== 'blocked') return;
        let confirmed = false;
        window.confirmDialog(t('Unsaved changes'), t('Discard unsaved meeting changes?'), () => {
            confirmed = true;
            blocker.proceed();
        }, {
            warning: true,
            afterClose: () => { if (!confirmed) blocker.reset(); },
        });
    }, [blocker]);

    return <ProjectsView key={location.key} config={permissions} onDirty={setDirty} />;
}
