import { useEffect, useRef, useState, forwardRef, useImperativeHandle } from 'react';
import { MaterialSymbol } from "@/components/ui/material-symbol";
import EditorJS, { EditorConfig, ToolConstructable } from '@editorjs/editorjs';
import Header from '@editorjs/header';
import List from '@editorjs/list';
import Paragraph from '@editorjs/paragraph';
import { useTranslations } from '@/hooks/useTranslations';

interface DocumentEditorProps {
    initialContent?: string;
    readOnly?: boolean;
    onChange?: (content: EditorContent) => void;
}

export interface EditorContent {
    blocks: EditorBlock[];
    version?: string;
    time?: number;
}

export interface EditorBlock {
    id?: string;
    type: 'paragraph' | 'header' | 'list' | string;
    data: Record<string, any>;
}

interface EditorListItem {
    content: string;
    items?: EditorListItem[];
}

const normalizeListItem = (item: unknown): EditorListItem => {
    if (item == null || typeof item !== 'object') {
        return { content: '', items: [] };
    }

    const obj = item as Record<string, any>;
    return {
        content: obj.content ?? '',
        items: Array.isArray(obj.items) ? obj.items.map(normalizeListItem) : [],
    };
};

const normalizeBlocks = (blocks: unknown): EditorBlock[] => {
    if (!Array.isArray(blocks)) return [];

    return blocks
        .filter((block) => block != null && typeof block === 'object')
        .map((block: Record<string, any>) => {
            const normalized: EditorBlock = {
                type: block.type ?? 'paragraph',
                data: block.data ?? {},
            };

            const { data } = normalized;

            switch (normalized.type) {
                case 'paragraph':
                    data.text = data.text ?? '';
                    break;
                case 'header':
                    data.text = data.text ?? '';
                    data.level = data.level ?? 1;
                    break;
                case 'list':
                    data.items = Array.isArray(data.items) ? data.items.map(normalizeListItem) : [];
                    data.style = data.style ?? 'unordered';
                    break;
            }

            return normalized;
        });
};

export const DocumentEditor = forwardRef<any, DocumentEditorProps>(function DocumentEditor(
    { initialContent, readOnly = false, onChange },
    ref
) {
    const { t } = useTranslations();
    const editorRef = useRef<HTMLDivElement>(null);
    const editorInstanceRef = useRef<EditorJS | null>(null);
    const initGenerationRef = useRef(0);
    const onChangeRef = useRef(onChange);
    const readOnlyRef = useRef(readOnly);
    const lastAppliedContentRef = useRef<string | null>(null);
    const isApplyingExternalContentRef = useRef(false);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        onChangeRef.current = onChange;
    }, [onChange]);

    useEffect(() => {
        readOnlyRef.current = readOnly;
    }, [readOnly]);

    const saveContent = async (): Promise<EditorContent | null> => {
        if (!editorInstanceRef.current || readOnly) {
            return null;
        }

        try {
            const content = await editorInstanceRef.current.save();
            if (!content || !content.blocks) {
                console.warn('Editor save returned empty content:', content);
                return { blocks: [] };
            }
            return content as EditorContent;
        } catch (err) {
            console.error('Error saving content:', err);
            const errorMsg = err instanceof Error ? err.message : t('ui.crud.document_editor.save_failed');
            console.error('Save error details:', { err, errorMsg });
            setError(errorMsg);
            return null;
        }
    };

    useImperativeHandle(ref, () => ({
        __saveContent: saveContent,
    }), [readOnly]);

    useEffect(() => {
        const holder = editorRef.current;
        if (!holder) return;

        const generation = ++initGenerationRef.current;
        let cancelled = false;

        const initializeEditor = async () => {
            try {
                setLoading(true);
                setError(null);

                if (editorInstanceRef.current?.destroy) {
                    editorInstanceRef.current.destroy();
                    editorInstanceRef.current = null;
                }
                holder.innerHTML = '';

                const editor = new EditorJS({
                    holder,
                    readOnly: false,
                    data: { blocks: [] },
                    tools: {
                        paragraph: {
                            class: Paragraph as unknown as ToolConstructable,
                            inlineToolbar: true,
                        },
                        header: {
                            class: Header as unknown as ToolConstructable,
                            config: {
                                placeholder: t('ui.crud.document_editor.header_placeholder'),
                                levels: [1, 2, 3],
                                defaultLevel: 1,
                            },
                        },
                        list: {
                            class: List,
                            inlineToolbar: true,
                            config: {
                                defaultStyle: 'unordered',
                            },
                        },
                    },
                    onChange: async () => {
                        if (!editorInstanceRef.current || readOnlyRef.current || isApplyingExternalContentRef.current) return;
                        try {
                            const content = await editorInstanceRef.current.save();
                            onChangeRef.current?.(content as EditorContent);
                        } catch (err) {
                            console.error('Error saving editor content:', err);
                        }
                    },
                });

                if (cancelled || generation !== initGenerationRef.current) {
                    if (editor.destroy) {
                        editor.destroy();
                    }
                    return;
                }

                editorInstanceRef.current = editor;
                await editor.isReady;

                if (cancelled || generation !== initGenerationRef.current || editorInstanceRef.current !== editor) {
                    if (editor.destroy) {
                        editor.destroy();
                    }
                    if (editorInstanceRef.current === editor) {
                        editorInstanceRef.current = null;
                    }
                    return;
                }

                await (editor as unknown as {
                    readOnly?: { toggle?: (state: boolean) => void | Promise<void> };
                }).readOnly?.toggle?.(readOnlyRef.current);
                setLoading(false);
            } catch (err) {
                console.error('Error initializing editor:', err);
                if (!cancelled && generation === initGenerationRef.current) {
                    setError(err instanceof Error ? err.message : t('ui.crud.document_editor.initialize_failed'));
                    setLoading(false);
                }
            }
        };

        void initializeEditor();

        return () => {
            cancelled = true;
            initGenerationRef.current += 1;
            if (editorInstanceRef.current?.destroy) {
                editorInstanceRef.current.destroy();
                editorInstanceRef.current = null;
            }
            lastAppliedContentRef.current = null;
            isApplyingExternalContentRef.current = false;
            holder.innerHTML = '';
        };
    }, []);

    useEffect(() => {
        const updateContent = async () => {
            const editor = editorInstanceRef.current;
            if (!editor) return;

            try {
                await editor.isReady;
                if (editorInstanceRef.current !== editor) {
                    return;
                }

                const incomingContent = initialContent ?? JSON.stringify({ blocks: [] });
                if (lastAppliedContentRef.current === incomingContent) {
                    return;
                }

                let blocks: EditorBlock[] = [];
                try {
                    const parsed = JSON.parse(incomingContent);
                    blocks = normalizeBlocks(parsed.blocks || []);
                } catch {
                    blocks = [];
                }

                isApplyingExternalContentRef.current = true;

                await editor.blocks.render({ blocks });
                lastAppliedContentRef.current = incomingContent;
            } catch (err) {
                console.error('Error updating editor content:', err);
            } finally {
                isApplyingExternalContentRef.current = false;
            }
        };

        void updateContent();
    }, [initialContent]);

    useEffect(() => {
        const editor = editorInstanceRef.current as unknown as {
            isReady?: Promise<void>;
            readOnly?: { toggle?: (state: boolean) => void | Promise<void> };
        } | null;

        if (!editor) return;

        const applyReadOnly = async () => {
            try {
                if (editor.isReady) {
                    await editor.isReady;
                }
                await editor.readOnly?.toggle?.(readOnly);
            } catch {
                // Ignore toggle errors during destroy/re-init races.
            }
        };

        void applyReadOnly();
    }, [readOnly]);

    return (
        <div className="space-y-4">
            {error && (
                <div className="flex items-center gap-2 rounded-md border border-destructive/50 bg-destructive/10 p-3">
                    <MaterialSymbol name="error" className="h-4 w-4 text-destructive" />
                    <p className="text-sm text-destructive">{error}</p>
                </div>
            )}

            <div className="relative">
                <div
                    ref={editorRef}
                    className={`rounded-lg border border-border bg-background p-4 ${
                        readOnly ? 'bg-muted/30' : ''
                    }`}
                    style={{
                        minHeight: '400px',
                    }}
                />

                {loading && (
                    <div className="absolute inset-0 flex items-center justify-center rounded-lg bg-background/70">
                        <MaterialSymbol name="progress_activity" className="h-6 w-6 animate-spin text-primary" />
                    </div>
                )}
            </div>
        </div>
    );
});
// Helper to access the editor's save method
export async function saveDocumentEditor(editor: any): Promise<EditorContent | null> {
    if (!editor || !editor.__saveContent) {
        return null;
    }
    return editor.__saveContent();
}

