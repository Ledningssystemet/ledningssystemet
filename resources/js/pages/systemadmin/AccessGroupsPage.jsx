import React from 'react';
import { useRouteLoaderData } from 'react-router-dom';
import { TableModule } from '../../components/table/TableModule';

export function Component() {
    const { config } = useRouteLoaderData('systemadmin-root');
    return <TableModule config={config} data={[]} />;
}
