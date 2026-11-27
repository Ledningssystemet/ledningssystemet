import React from 'react';
import { useRouteLoaderData } from 'react-router-dom';
import { TableModule } from '../../components/table/TableModule';
import { t } from '../../lib/i18n';

function createToken(userOptions) {
    const $ = window.$;
    const name = $('<input />').attr({ type: 'text', maxlength: 255, id: 'api-token-name' }).addClass('form-control');
    const user = $('<select />').attr('id', 'api-token-user').addClass('form-select')
        .append($('<option />').val('').text(`-- ${t('User')} --`));
    userOptions.forEach(option => user.append($('<option />').val(option.value).text(option.label)));
    const error = $('<p />').addClass('text-danger').attr('role', 'alert').hide();
    const content = $('<div />')
        .append($('<label />').attr('for', 'api-token-name').addClass('form-label').text(t('Name')), name)
        .append($('<label />').attr('for', 'api-token-user').addClass('form-label mt-3').text(t('User')), user, error);
    let pending = false;
    let created = false;
    let result = null;

    window.showDialog(t('Create API token'), content, {
        actionLabel: t('Create'),
        beforeClose: () => pending,
        onAction: (data, dialog) => {
            if (pending) return true;
            if (!name.val().trim() || !user.val()) {
                error.text(t('Required')).show();
                return true;
            }
            error.hide();
            pending = true;
            dialog.find('.action-button').prop('disabled', true);
            const url = `/api/v1/items/User/${encodeURIComponent(user.val())}/issuetoken`;
            const complete = (event, xhr, settings) => {
                if (settings.type !== 'POST' || settings.url.split('?')[0] !== url) return;
                window.$(document).off('ajaxComplete', complete);
                if (pending) {
                    pending = false;
                    dialog.find('.action-button').prop('disabled', false);
                }
            };
            window.$(document).on('ajaxComplete', complete);
            window.ajaxPost(url, { name: name.val() }, response => {
                result = response;
                created = true;
                pending = false;
                dialog.find('.dismiss-button').first().trigger('click');
            });
            return true;
        },
        afterClose: () => {
            if (!created) return;
            const message = $('<div />');
            if (typeof result?.plainTextToken === 'string' && result.plainTextToken) {
                const copyStatus = $('<span />').attr('aria-live', 'polite').addClass('ms-2');
                const copyButton = $('<button />').attr('type', 'button').addClass('btn btn-outline-secondary mt-2').text(t('Copy'));
                copyButton.on('click', () => {
                    if (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
                        copyStatus.removeClass('text-success').addClass('text-danger').text(t('Unable to copy to clipboard'));
                        return;
                    }
                    navigator.clipboard.writeText(result.plainTextToken).then(() => {
                        copyStatus.removeClass('text-danger').addClass('text-success').text(t('Copied to clipboard'));
                    }).catch(() => {
                        copyStatus.removeClass('text-success').addClass('text-danger').text(t('Unable to copy to clipboard'));
                    });
                });
                message.text(t('A new token has been created, and it will be displayed only this time. A new token needs to be created if this gets lost.'))
                    .append($('<div />').text(result.plainTextToken).css({ fontWeight: 'bold', marginTop: '20px', overflowWrap: 'anywhere' }))
                    .append(copyButton, copyStatus);
            } else {
                message.text(t('Due to an unknown reason, a requested action failed'));
            }
            window.showDialog(t('API token'), message, {
                afterClose: () => window.location.reload(),
                modalClass: 'modal-xl',
            });
        },
    });
}

export function Component() {
    const { config, userOptions, canCreate } = useRouteLoaderData('systemadmin-root');

    return <>
        <div className="mb-3">
            <span className="ls-table inline-flex">
                <a href="/systemadmin/swagger" className="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring border bg-card hover:bg-surface-hover text-decoration-none">
                    {t('To the API documentation')}
                </a>
            </span>
            {canCreate && <span className="ls-table inline-flex">
                <button type="button" className="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring bg-primary text-primary-foreground hover:bg-primary/90" onClick={() => createToken(userOptions)}>
                    {t('Create API token')}
                </button>
            </span>}
        </div>
        <TableModule config={config} data={[]} />
    </>;
}
