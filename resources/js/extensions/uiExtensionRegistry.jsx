import React from 'react';
import ReactDOM from 'react-dom/client';

/**
 * UI extension registry.
 *
 * Allows the base application and any consuming package (e.g. the
 * proprietary customer application) to contribute one or more React
 * components to a named "slot" in the DOM, e.g. a slot in the header.
 *
 * Unlike componentRegistry.jsx (which mounts exactly one component per
 * DOM element), this registry combines contributions from several
 * independent packages into the same slot, in a deterministic order.
 *
 * Registration must happen before mountUiExtensionSlots() is called
 * (typically on 'DOMContentLoaded'), i.e. during module import of the
 * consuming package. Extensions registered after the slots have been
 * mounted still work: registerUiExtension() re-renders the affected
 * slot immediately, e.g. when a module is loaded later via dynamic
 * import().
 */

const extensions = new Map();
const mountedSlots = new Map();

/**
 * Registers a React component for a given slot.
 *
 * @param {string} slot - Name of the slot, matching a `data-ui-slot` attribute.
 * @param {object} extension
 * @param {string} extension.id - Unique id within the slot. Prevents accidental duplicate registration.
 * @param {React.ComponentType} extension.component - The React component to render.
 * @param {object} [extension.props] - Props passed to the component.
 * @param {number} [extension.order=0] - Determines placement relative to other extensions in the same slot (ascending).
 * @param {() => boolean} [extension.enabled] - Optional predicate controlling client-side visibility.
 *   This is not a substitute for backend authorization: the backend must still enforce
 *   permissions/feature flags, since this only hides the UI on the client.
 */
export function registerUiExtension(slot, extension) {
    if (!extension?.id || !extension?.component) {
        throw new Error(`Invalid UI extension for slot "${slot}"`);
    }

    const slotExtensions = extensions.get(slot) ?? new Map();

    if (slotExtensions.has(extension.id)) {
        throw new Error(`UI extension "${extension.id}" is already registered in "${slot}"`);
    }

    slotExtensions.set(extension.id, {
        order: 0,
        ...extension,
    });

    extensions.set(slot, slotExtensions);
    renderSlot(slot);
}

function renderSlot(slot) {
    const mounted = mountedSlots.get(slot);

    if (!mounted) {
        return;
    }

    const items = [...(extensions.get(slot)?.values() ?? [])]
        .filter(extension => extension.enabled?.() ?? true)
        .sort((left, right) => left.order - right.order);

    mounted.root.render(
        <React.StrictMode>
            {items.map(extension => {
                const Component = extension.component;

                return <Component key={extension.id} {...extension.props} />;
            })}
        </React.StrictMode>
    );
}

/**
 * Mounts a React root for every `[data-ui-slot]` element currently in the
 * document, and renders any extensions already registered for that slot.
 * Must be called once, after the DOM is ready.
 */
export function mountUiExtensionSlots() {
    document.querySelectorAll('[data-ui-slot]').forEach(element => {
        const slot = element.dataset.uiSlot;

        if (mountedSlots.has(slot)) {
            throw new Error(`UI slot "${slot}" occurs more than once`);
        }

        mountedSlots.set(slot, {
            element,
            root: ReactDOM.createRoot(element),
        });

        renderSlot(slot);
    });
}
