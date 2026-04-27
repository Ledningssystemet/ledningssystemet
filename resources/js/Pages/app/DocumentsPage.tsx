import { useState, useMemo } from 'react';
import { MaterialSymbol } from "@/components/ui/material-symbol";
import { useNavigate } from 'react-router-dom';
import AppLayout from '@/layouts/AppLayout';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import { APP_DOCUMENT_EDITOR_PATH } from '@/app/routes';
import { PageHeader } from '@/components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface DocumentsPageProps {
    route: AppSectionRoute;
}

export default function DocumentsPage({ route }: DocumentsPageProps) {
    const { t } = useTranslations();
    const navigate = useNavigate();
    const [formData, setFormData] = useState<Record<string, any>>({});

    // Get the current document_type from form data for conditional field visibility
    const documentTypeValue = formData.document_type || 'file';
    const isEditorMode = documentTypeValue === 'editor';

    // Create config with useMemo to prevent unnecessary re-renders
    const config: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/library_documents',
        perPage: 25,
        defaultSort: 'name',
        selectFields: [
            'id',
            'name',
            'description',
            'filename',
            'contenttype',
            'contentlength',
            'responsible_user_id',
            'created_at',
            'updated_at',
        ],
        createTitle: t('pages.documents.create_title'),
        editTitle: t('pages.documents.edit_title'),
        fields: [
            {
                key: 'name',
                label: t('pages.documents.column_name'),
                type: 'text',
                sortable: true,
                editable: true,
                required: true,
                masterLabel: true,
                category: t('pages.documents.category_general'),
            },
            {
                key: 'description',
                label: t('pages.documents.column_description'),
                type: 'textarea',
                editable: true,
                masterDescription: true,
                category: t('pages.documents.category_general'),
            },
            {
                key: 'document_type',
                label: t('pages.documents.column_document_type'),
                type: 'select',
                editable: true,
                editableOnCreate: true,
                editableOnUpdate: false,
                required: true,
                options: [
                    { value: 'file', label: t('pages.documents.document_type_file') },
                    { value: 'editor', label: t('pages.documents.document_type_editor') },
                ],
                placeholder: t('pages.documents.column_document_type'),
                category: t('pages.documents.category_general'),
            },
            {
                key: 'responsible_user_id',
                label: t('pages.documents.column_responsible_user'),
                type: 'select',
                sortable: true,
                editable: true,
                filterable: true,
                optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.documents.none_assigned'),
                category: t('pages.documents.category_general'),
            },
            {
                key: 'filename',
                label: t('pages.documents.column_filename'),
                type: 'text',
                editable: false,
                sortable: true,
                hiddenInTable: false,
                renderCell: (_, row) => {
                    if (row.contenttype === 'ledningssystemet/document') {
                        return (
                            <span className="inline-flex items-center gap-2 rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 border border-blue-200">
                                <MaterialSymbol name="description" className="h-3.5 w-3.5" />
                                {t('pages.documents.ledningssystemet_document')}
                            </span>
                        );
                    }
                    return row.filename || '—';
                },
                renderDetail: (_, row) => {
                    if (row.contenttype === 'ledningssystemet/document') {
                        return (
                            <span className="inline-flex items-center gap-2 rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 border border-blue-200">
                                <MaterialSymbol name="description" className="h-3.5 w-3.5" />
                                {t('pages.documents.ledningssystemet_document')}
                            </span>
                        );
                    }
                    return row.filename || '—';
                },
                category: t('pages.documents.category_file'),
            },
            {
                key: 'filecontent',
                label: t('pages.documents.column_file'),
                type: 'file',
                editable: true,
                editableOnCreate: true,
                editableOnUpdate: true,
                sortable: false,
                hiddenInTable: true,
                category: t('pages.documents.category_file'),
                hidden: isEditorMode,
                required: !isEditorMode,
            },
            {
                key: 'download_file',
                label: t('pages.documents.column_download'),
                type: 'text',
                editable: false,
                sortable: false,
                renderCell: (_, row) => {
                    if (row.contenttype === 'ledningssystemet/document') {
                        return (
                            <button
                                onClick={() => navigate(APP_DOCUMENT_EDITOR_PATH.replace(':libraryDocumentId', String(row.id)))}
                                className="inline-flex items-center gap-1 rounded-md border border-border px-2 py-1 text-xs font-medium text-foreground transition-colors hover:bg-muted"
                            >
                                <MaterialSymbol name="edit" className="h-3.5 w-3.5" />
                                {t('pages.documents.edit')}
                            </button>
                        );
                    }

                    if (!row.filename || row.contentlength === 0) {
                        return '—';
                    }

                    return (
                        <a
                            href={`/api/v1/LibraryDocument/${row.id}/download`}
                            className="inline-flex items-center gap-1 rounded-md border border-border px-2 py-1 text-xs font-medium text-foreground transition-colors hover:bg-muted"
                        >
                            <MaterialSymbol name="download" className="h-3.5 w-3.5" />
                            {t('pages.documents.download')}
                        </a>
                    );
                },
                renderDetail: (_, row) => {
                    if (row.contenttype === 'ledningssystemet/document') {
                        return (
                            <button
                                onClick={() => navigate(APP_DOCUMENT_EDITOR_PATH.replace(':libraryDocumentId', String(row.id)))}
                                className="inline-flex items-center gap-1 rounded-md border border-border px-2 py-1 text-xs font-medium text-foreground transition-colors hover:bg-muted"
                            >
                                <MaterialSymbol name="edit" className="h-3.5 w-3.5" />
                                {t('pages.documents.edit')}
                            </button>
                        );
                    }

                    if (!row.filename || row.contentlength === 0) {
                        return '—';
                    }

                    return (
                        <a
                            href={`/api/v1/LibraryDocument/${row.id}/download`}
                            className="inline-flex items-center gap-1 rounded-md border border-border px-2 py-1 text-xs font-medium text-foreground transition-colors hover:bg-muted"
                        >
                            <MaterialSymbol name="download" className="h-3.5 w-3.5" />
                            {t('pages.documents.download')}
                        </a>
                    );
                },
            },
            {
                key: 'created_at',
                label: t('pages.documents.column_created_at'),
                type: 'text',
                editable: false,
                sortable: true,
                hiddenInTable: false,
                category: t('pages.documents.category_metadata'),
            },
            {
                key: 'updated_at',
                label: t('pages.documents.column_updated_at'),
                type: 'text',
                editable: false,
                sortable: true,
                hiddenInTable: true,
                category: t('pages.documents.category_metadata'),
            },
        ],
    }), [t, isEditorMode]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.documents.title')}
                    description={t('pages.documents.description')}
                    icon={<MaterialSymbol name="description" className="h-6 w-6 text-primary" />}
                    route={route}
                />

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule
                        config={config}
                        onEditFormDataChange={setFormData}
                    />
                </section>
            </div>
        </AppLayout>
    );
}
