import { Fragment } from 'react';
import { createPluginSlotContext, getPluginSlotContributions } from '@/plugins/runtime';
import type { PluginSlotContext } from '@/types/plugins';

interface PluginSlotProps {
    name: string;
    context?: PluginSlotContext;
}

export function PluginSlot({ name, context = {} }: PluginSlotProps) {
    const contributions = getPluginSlotContributions(name);

    if (contributions.length === 0) {
        return null;
    }

    const slotContext = createPluginSlotContext(context);

    return (
        <>
            {contributions.map((contribution) => (
                <Fragment key={contribution.id}>
                    {contribution.render(slotContext)}
                </Fragment>
            ))}
        </>
    );
}

