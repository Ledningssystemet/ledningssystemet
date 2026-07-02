import React, {useState, useCallback, useMemo} from "react";
import {MaterialSymbol} from "@/Components/ui/material-symbol";
import {ResponsiveGridLayout, useContainerWidth} from "react-grid-layout";

type LayoutItem = { i: string; x: number; y: number; w: number; h: number; minW?: number; minH?: number };
type Layouts = Record<string, LayoutItem[]>;
import Cookies from "js-cookie";
import {cn} from "@/Lib/utils";
import {useTranslations} from "@/hooks/useTranslations";
import {useDashboardData} from "@/hooks/useDashboardData";
import {DashboardWidgetProps} from "@/types/dashboard";
import TaskList from "./TaskList";
import GoalsCard from "./GoalsCard";
import TopRisks from "./TopRisks";
import RiskOverview from "./RiskOverview";
import ProcessCard from "./ProcessCard";
import "react-grid-layout/css/styles.css";
import "react-resizable/css/styles.css";

const COOKIE_KEY = "dashboard_layout";

interface WidgetConfig {
    id: string;
    labelKey: string;
    icon: string;
    component: React.ComponentType<DashboardWidgetProps>;
    defaultSize: { w: number; h: number; minW?: number; minH?: number };
}

const allWidgets: WidgetConfig[] = [
    {
        id: "tasks",
        labelKey: "pages.dashboard.widgets.tasks",
        icon: "assignment",
        component: TaskList,
        defaultSize: {w: 4, h: 20, minW: 3, minH: 5}
    },
    {
        id: "goals",
        labelKey: "pages.dashboard.widgets.goals",
        icon: "target",
        component: GoalsCard,
        defaultSize: {w: 4, h: 10, minW: 3, minH: 4}
    },
    {
        id: "process",
        labelKey: "pages.dashboard.widgets.process",
        icon: "account_tree",
        component: ProcessCard,
        defaultSize: {w: 8, h: 10, minW: 3, minH: 4}
    },
    {
        id: "toprisks",
        labelKey: "pages.dashboard.widgets.top_risks",
        icon: "warning",
        component: TopRisks,
        defaultSize: {w: 4, h: 5, minW: 3, minH: 4}
    },
    {
        id: "riskmatrix",
        labelKey: "pages.dashboard.widgets.risk_matrix",
        icon: "bar_chart",
        component: RiskOverview,
        defaultSize: {w: 4, h: 5, minW: 3, minH: 4}
    },
];

const defaultLayouts: Layouts = {
    lg: [
        {i: "tasks", x: 0, y: 0, w: 4, h: 20, minW: 3, minH: 4},
        {i: "goals", x: 4, y: 0, w: 4, h: 10, minW: 3, minH: 4},
        {i: "riskmatrix", x: 8, y: 0, w: 4, h: 5, minW: 3, minH: 4},
        {i: "toprisks", x: 8, y: 5, w: 4, h: 5, minW: 3, minH: 4},
        {i: "process", x: 4, y: 10, w: 8, h: 10, minW: 3, minH: 4},
    ],
    md: [
        {i: "tasks", x: 0, y: 0, w: 6, h: 10, minW: 3, minH: 4},
        {i: "goals", x: 6, y: 0, w: 6, h: 10, minW: 3, minH: 4},
        {i: "riskmatrix", x: 0, y: 10, w: 6, h: 10, minW: 3, minH: 4},
        {i: "toprisks", x: 6, y: 10, w: 6, h: 10, minW: 3, minH: 4},
        {i: "process", x: 0, y: 20, w: 12, h: 15, minW: 3, minH: 12},
    ],
    sm: [
        {i: "tasks", x: 0, y: 0, w: 12, h: 10, minW: 12, minH: 4},
        {i: "goals", x: 0, y: 10, w: 12, h: 10, minW: 12, minH: 4},
        {i: "riskmatrix", x: 0, y: 20, w: 12, h: 6, minW: 12, minH: 4},
        {i: "toprisks", x: 0, y: 26, w: 12, h: 5, minW: 12, minH: 4},
        {i: "process", x: 0, y: 31, w: 12, h: 20, minW: 12, minH: 10},
    ],
};

interface SavedState {
    layouts: Layouts;
    visibleWidgets: string[];
}

function loadState(): SavedState | null {
    try {
        const raw = Cookies.get(COOKIE_KEY);
        if (raw) return JSON.parse(raw) as SavedState;
    } catch { /* ignore */
    }
    return null;
}

function saveState(state: SavedState) {
    Cookies.set(COOKIE_KEY, JSON.stringify(state), {expires: 365});
}

export default function DashboardGrid() {
    const {t} = useTranslations();
    const {data, loading, error, setSelectedProcessId} = useDashboardData();
    const {containerRef, width} = useContainerWidth();
    const saved = useMemo(() => loadState(), []);
    const [layouts, setLayouts] = useState<Layouts>(saved?.layouts ?? defaultLayouts);
    const [visibleWidgets, setVisibleWidgets] = useState<string[]>(
        saved?.visibleWidgets ?? allWidgets.map((w) => w.id)
    );
    const [editing, setEditing] = useState(false);
    const [showAddMenu, setShowAddMenu] = useState(false);

    const persist = useCallback((newLayouts: Layouts, newVisible: string[]) => {
        saveState({layouts: newLayouts, visibleWidgets: newVisible});
    }, []);

    const handleLayoutChange = useCallback((_layout: any, allLayouts: Partial<Record<string, any>>) => {
        setLayouts(allLayouts as Layouts);
        persist(allLayouts as Layouts, visibleWidgets);
    }, [persist, visibleWidgets]);

    const removeWidget = useCallback((id: string) => {
        const newVisible = visibleWidgets.filter((w) => w !== id);
        setVisibleWidgets(newVisible);
        const newLayouts = {...layouts};
        for (const bp of Object.keys(newLayouts)) {
            newLayouts[bp] = newLayouts[bp].filter((l) => l.i !== id);
        }
        setLayouts(newLayouts);
        persist(newLayouts, newVisible);
    }, [visibleWidgets, layouts, persist]);

    const addWidget = useCallback((id: string) => {
        const config = allWidgets.find((w) => w.id === id);
        if (!config) return;
        const newVisible = [...visibleWidgets, id];
        setVisibleWidgets(newVisible);
        const newLayouts = {...layouts};
        for (const bp of Object.keys(newLayouts)) {
            const maxY = newLayouts[bp].reduce((max, l) => Math.max(max, l.y + l.h), 0);
            newLayouts[bp] = [
                ...newLayouts[bp],
                {i: id, x: 0, y: maxY, ...config.defaultSize},
            ];
        }
        setLayouts(newLayouts);
        persist(newLayouts, newVisible);
        setShowAddMenu(false);
    }, [visibleWidgets, layouts, persist]);

    const resetLayout = useCallback(() => {
        const defaultVisible = allWidgets.map((w) => w.id);
        setLayouts(defaultLayouts);
        setVisibleWidgets(defaultVisible);
        persist(defaultLayouts, defaultVisible);
    }, [persist]);

    const hiddenWidgets = allWidgets.filter((w) => !visibleWidgets.includes(w.id));

    return (
        <div className="relative">
            {/* Toolbar */}
            <div className="flex items-center justify-end gap-2 mb-4">
                {editing && hiddenWidgets.length > 0 && (
                    <div className="relative">
                        <button
                            onClick={() => setShowAddMenu(!showAddMenu)}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium bg-accent text-accent-foreground hover:bg-accent/90 transition-colors"
                        >
                            <MaterialSymbol name="add" className="h-3.5 w-3.5"/>
                            {t('ui.widget.add')}
                        </button>
                        {showAddMenu && (
                            <div
                                className="absolute right-0 top-full mt-1 z-50 bg-card border border-border rounded-lg shadow-lg p-2 min-w-[200px]">
                                {hiddenWidgets.map((w) => (
                                    <button
                                        key={w.id}
                                        onClick={() => addWidget(w.id)}
                                        className="w-full flex items-center gap-2 px-3 py-2 text-sm rounded-md hover:bg-muted transition-colors text-left"
                                    >
                                        <MaterialSymbol name={w.icon} className="h-4 w-4 text-muted-foreground"/>
                                        {t(w.labelKey)}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                )}
                {editing && (
                    <button
                        onClick={resetLayout}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium border border-border text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
                    >
                        <MaterialSymbol name="undo" className="h-3.5 w-3.5"/>
                        {t('ui.widget.reset')}
                    </button>
                )}
                <button
                    onClick={() => {
                        setEditing(!editing);
                        setShowAddMenu(false);
                    }}
                    className={cn(
                        "inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-colors",
                        editing
                            ? "bg-primary text-primary-foreground hover:bg-primary/90"
                            : "border border-border text-muted-foreground hover:text-foreground hover:bg-muted"
                    )}
                >
                    <MaterialSymbol name="settings" className="h-3.5 w-3.5"/>
                    {editing ? t('ui.widget.done') : t('ui.widget.customize')}
                </button>
            </div>

            <div ref={containerRef}>
                <ResponsiveGridLayout
                    className="layout"
                    width={width}
                    layouts={layouts}
                    breakpoints={{lg: 1024, md: 768, sm: 0}}
                    cols={{lg: 12, md: 12, sm: 12}}
                    rowHeight={40}
                    dragConfig={{enabled: editing, handle: ".widget-drag-handle"}}
                    resizeConfig={{enabled: editing}}
                    onLayoutChange={handleLayoutChange}
                    containerPadding={[0, 0]}
                    margin={[16, 16]}
                >
                    {visibleWidgets.map((id) => {
                        const config = allWidgets.find((w) => w.id === id);
                        if (!config) return null;
                        const Widget = config.component;
                        return (
                            <div
                                key={id}
                                className={cn(
                                    "rounded-xl overflow-hidden",
                                    editing && "ring-2 ring-primary/20 ring-offset-2 ring-offset-background"
                                )}
                            >
                                {editing && (
                                    <div
                                        className="absolute top-0 left-0 right-0 z-10 flex items-center justify-between px-2 py-1 bg-primary/10 backdrop-blur-sm rounded-t-xl">
                                        <div
                                            className="widget-drag-handle flex items-center gap-1.5 cursor-grab active:cursor-grabbing text-xs font-medium text-primary">
                                            <MaterialSymbol name="drag_indicator" className="h-3.5 w-3.5"/>
                                            {t(config.labelKey)}
                                        </div>
                                        <button
                                            onClick={() => removeWidget(id)}
                                            className="p-0.5 rounded hover:bg-destructive/20 text-muted-foreground hover:text-destructive transition-colors"
                                        >
                                            <MaterialSymbol name="close" className="h-3.5 w-3.5"/>
                                        </button>
                                    </div>
                                )}
                                <div className={cn("h-full overflow-auto", editing && "pt-7")}>
                                    <Widget
                                        data={data}
                                        loading={loading}
                                        error={error ? t('pages.dashboard.shared.load_error') : null}
                                        setSelectedProcessId={setSelectedProcessId}
                                    />
                                </div>
                            </div>
                        );
                    })}
                </ResponsiveGridLayout>
            </div>
        </div>
    );
}
