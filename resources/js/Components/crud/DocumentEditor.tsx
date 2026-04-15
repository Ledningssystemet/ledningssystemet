import { useEffect, useRef, useState, forwardRef, useImperativeHandle } from 'react';
import EditorJS, { API, EditorConfig } from '@editorjs/editorjs';
import Header from '@editorjs/header';
import List from '@editorjs/list';
import Paragraph from '@editorjs/paragraph';
import { AlertCircle, Loader2 } from 'lucide-react';

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
    const editorRef = useRef<HTMLDivElement>(null);
    const editorInstanceRef = useRef<EditorJS | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const saveContent = async (): Promise<EditorContent | null> => {
        if (!editorInstanceRef.current || readOnly) {
            return null;
        }

        try {
            const content = await editorInstanceRef.current.save();
            return content as EditorContent;
        } catch (err) {
            console.error('Error saving content:', err);
            setError(err instanceof Error ? err.message : 'Failed to save content');
            return null;
        }
    };

    useImperativeHandle(ref, () => ({
        __saveContent: saveContent,
    }), [readOnly]);

    useEffect(() => {
        if (!editorRef.current) return;

        const initializeEditor = async () => {
            try {
                setLoading(true);
                setError(null);

                let initialData: EditorContent = { blocks: [] };

                if (initialContent) {
                    try {
                        const parsed = JSON.parse(initialContent);
                        initialData = {
                            blocks: normalizeBlocks(parsed.blocks || []),
                            version: parsed.version,
                            time: parsed.time,
                        };
                    } catch (parseError) {
                        console.error('Failed to parse initial content:', parseError);
                        initialData = { blocks: [] };
                    }
                }

                const editorConfig: EditorConfig = {
                    holder: editorRef.current,
                    readOnly: readOnly,
                    data: initialData,
                    tools: {
                        paragraph: {
                            class: Paragraph,
                            inlineToolbar: true,
                        },
                        header: {
                            class: Header,
                            config: {
                                placeholder: 'Enter a header',
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
                        if (!editorInstanceRef.current || readOnly) return;

                        try {
                            const content = await editorInstanceRef.current.save();
                            onChange?.(content as EditorContent);
                        } catch (err) {
                            console.error('Error saving editor content:', err);
                        }
                    },
                };

                const editor = new EditorJS(editorConfig);
                editorInstanceRef.current = editor;

                await editor.isReady;
                setLoading(false);
            } catch (err) {
                console.error('Error initializing editor:', err);
                setError(err instanceof Error ? err.message : 'Failed to initialize editor');
                setLoading(false);
            }
        };

        void initializeEditor();

        return () => {
            if (editorInstanceRef.current && editorInstanceRef.current.destroy) {
                editorInstanceRef.current.destroy();
                editorInstanceRef.current = null;
            }
        };
    }, [readOnly, onChange]);

    // Update initial content when it changes
    useEffect(() => {
        if (!editorInstanceRef.current || !initialContent || readOnly) return;

        const updateContent = async () => {
            try {
                const parsed = JSON.parse(initialContent);
                const blocks = normalizeBlocks(parsed.blocks || []);
                await editorInstanceRef.current?.render({ blocks });
            } catch (err) {
                console.error('Error updating editor content:', err);
            }
        };

        void updateContent();
    }, [initialContent, readOnly]);

    if (loading) {
        return (
            <div className="flex items-center justify-center py-8">
                <Loader2 className="h-6 w-6 animate-spin text-primary" />
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {error && (
                <div className="flex items-center gap-2 rounded-md border border-destructive/50 bg-destructive/10 p-3">
                    <AlertCircle className="h-4 w-4 text-destructive" />
                    <p className="text-sm text-destructive">{error}</p>
                </div>
            )}

            <div
                ref={editorRef}
                className={`rounded-lg border border-border bg-background p-4 ${
                    readOnly ? 'bg-muted/30' : ''
                }`}
                style={{
                    minHeight: '400px',
                }}
            />
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

