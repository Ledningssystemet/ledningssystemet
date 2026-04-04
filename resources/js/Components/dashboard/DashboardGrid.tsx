import React, { useState, useCallback, useMemo } from "react";
import { ResponsiveGridLayout, useContainerWidth } from "react-grid-layout";

type LayoutItem = { i: string; x: number; y: number; w: number; h: number; minW?: number; minH?: number };
type Layouts = Record<string, LayoutItem[]>;
import Cookies from "js-cookie";
import { cn } from "@/Lib/utils";
import {
  GripVertical, X, Plus, Settings2, RotateCcw,
  ClipboardList, Target, AlertTriangle, GitBranch,
  BarChart3, TrendingUp,
} from "lucide-react";
import StatsRow from "./StatsRow";
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
  label: string;
  icon: React.ElementType;
  component: React.ComponentType;
  defaultSize: { w: number; h: number; minW?: number; minH?: number };
}

const allWidgets: WidgetConfig[] = [
  { id: "stats", label: "Nyckeltal", icon: TrendingUp, component: StatsRow, defaultSize: { w: 12, h: 3, minW: 6, minH: 3 } },
  { id: "tasks", label: "Uppgifter", icon: ClipboardList, component: TaskList, defaultSize: { w: 4, h: 8, minW: 3, minH: 4 } },
  { id: "goals", label: "Mål & Planer", icon: Target, component: GoalsCard, defaultSize: { w: 4, h: 5, minW: 3, minH: 4 } },
  { id: "process", label: "Processer", icon: GitBranch, component: ProcessCard, defaultSize: { w: 4, h: 5, minW: 3, minH: 4 } },
  { id: "toprisks", label: "Topprisker", icon: AlertTriangle, component: TopRisks, defaultSize: { w: 4, h: 5, minW: 3, minH: 4 } },
  { id: "riskmatrix", label: "Riskmatris", icon: BarChart3, component: RiskOverview, defaultSize: { w: 4, h: 5, minW: 3, minH: 4 } },
];

const defaultLayouts: Layouts = {
  lg: [
    { i: "stats", x: 0, y: 0, w: 12, h: 3, minW: 6, minH: 3 },
    { i: "tasks", x: 0, y: 3, w: 4, h: 8, minW: 3, minH: 4 },
    { i: "goals", x: 4, y: 3, w: 4, h: 5, minW: 3, minH: 4 },
    { i: "process", x: 4, y: 8, w: 4, h: 5, minW: 3, minH: 4 },
    { i: "toprisks", x: 8, y: 3, w: 4, h: 5, minW: 3, minH: 4 },
    { i: "riskmatrix", x: 8, y: 8, w: 4, h: 5, minW: 3, minH: 4 },
  ],
  md: [
    { i: "stats", x: 0, y: 0, w: 12, h: 3, minW: 6, minH: 3 },
    { i: "tasks", x: 0, y: 3, w: 6, h: 8, minW: 3, minH: 4 },
    { i: "goals", x: 6, y: 3, w: 6, h: 5, minW: 3, minH: 4 },
    { i: "process", x: 0, y: 11, w: 6, h: 5, minW: 3, minH: 4 },
    { i: "toprisks", x: 6, y: 8, w: 6, h: 5, minW: 3, minH: 4 },
    { i: "riskmatrix", x: 0, y: 16, w: 6, h: 5, minW: 3, minH: 4 },
  ],
  sm: [
    { i: "stats", x: 0, y: 0, w: 12, h: 4, minW: 12, minH: 3 },
    { i: "tasks", x: 0, y: 4, w: 12, h: 8, minW: 12, minH: 4 },
    { i: "goals", x: 0, y: 12, w: 12, h: 5, minW: 12, minH: 4 },
    { i: "process", x: 0, y: 17, w: 12, h: 5, minW: 12, minH: 4 },
    { i: "toprisks", x: 0, y: 22, w: 12, h: 5, minW: 12, minH: 4 },
    { i: "riskmatrix", x: 0, y: 27, w: 12, h: 5, minW: 12, minH: 4 },
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
  } catch { /* ignore */ }
  return null;
}

function saveState(state: SavedState) {
  Cookies.set(COOKIE_KEY, JSON.stringify(state), { expires: 365 });
}

export default function DashboardGrid() {
  const { containerRef, width } = useContainerWidth();
  const saved = useMemo(() => loadState(), []);
  const [layouts, setLayouts] = useState<Layouts>(saved?.layouts ?? defaultLayouts);
  const [visibleWidgets, setVisibleWidgets] = useState<string[]>(
    saved?.visibleWidgets ?? allWidgets.map((w) => w.id)
  );
  const [editing, setEditing] = useState(false);
  const [showAddMenu, setShowAddMenu] = useState(false);

  const persist = useCallback((newLayouts: Layouts, newVisible: string[]) => {
    saveState({ layouts: newLayouts, visibleWidgets: newVisible });
  }, []);

  const handleLayoutChange = useCallback((_layout: any, allLayouts: Partial<Record<string, any>>) => {
    setLayouts(allLayouts as Layouts);
    persist(allLayouts as Layouts, visibleWidgets);
  }, [persist, visibleWidgets]);

  const removeWidget = useCallback((id: string) => {
    const newVisible = visibleWidgets.filter((w) => w !== id);
    setVisibleWidgets(newVisible);
    const newLayouts = { ...layouts };
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
    const newLayouts = { ...layouts };
    for (const bp of Object.keys(newLayouts)) {
      const maxY = newLayouts[bp].reduce((max, l) => Math.max(max, l.y + l.h), 0);
      newLayouts[bp] = [
        ...newLayouts[bp],
        { i: id, x: 0, y: maxY, ...config.defaultSize },
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
              <Plus className="h-3.5 w-3.5" />
              Lägg till widget
            </button>
            {showAddMenu && (
              <div className="absolute right-0 top-full mt-1 z-50 bg-card border border-border rounded-lg shadow-lg p-2 min-w-[200px]">
                {hiddenWidgets.map((w) => (
                  <button
                    key={w.id}
                    onClick={() => addWidget(w.id)}
                    className="w-full flex items-center gap-2 px-3 py-2 text-sm rounded-md hover:bg-muted transition-colors text-left"
                  >
                    <w.icon className="h-4 w-4 text-muted-foreground" />
                    {w.label}
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
            <RotateCcw className="h-3.5 w-3.5" />
            Återställ
          </button>
        )}
        <button
          onClick={() => { setEditing(!editing); setShowAddMenu(false); }}
          className={cn(
            "inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-colors",
            editing
              ? "bg-primary text-primary-foreground hover:bg-primary/90"
              : "border border-border text-muted-foreground hover:text-foreground hover:bg-muted"
          )}
        >
          <Settings2 className="h-3.5 w-3.5" />
          {editing ? "Klar" : "Anpassa"}
        </button>
      </div>

      <div ref={containerRef}>
        <ResponsiveGridLayout
          className="layout"
          width={width}
          layouts={layouts}
          breakpoints={{ lg: 1024, md: 768, sm: 0 }}
          cols={{ lg: 12, md: 12, sm: 12 }}
          rowHeight={40}
          dragConfig={{ enabled: editing, handle: ".widget-drag-handle" }}
          resizeConfig={{ enabled: editing }}
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
                <div className="absolute top-0 left-0 right-0 z-10 flex items-center justify-between px-2 py-1 bg-primary/10 backdrop-blur-sm rounded-t-xl">
                  <div className="widget-drag-handle flex items-center gap-1.5 cursor-grab active:cursor-grabbing text-xs font-medium text-primary">
                    <GripVertical className="h-3.5 w-3.5" />
                    {config.label}
                  </div>
                  <button
                    onClick={() => removeWidget(id)}
                    className="p-0.5 rounded hover:bg-destructive/20 text-muted-foreground hover:text-destructive transition-colors"
                  >
                    <X className="h-3.5 w-3.5" />
                  </button>
                </div>
              )}
              <div className={cn("h-full overflow-auto", editing && "pt-7")}>
                <Widget />
              </div>
            </div>
          );
        })}
      </ResponsiveGridLayout>
      </div>
    </div>
  );
}
