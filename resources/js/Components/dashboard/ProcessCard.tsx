import { Maximize2, Settings } from "lucide-react";

const processSteps = [
  { label: "Kundförfrågan", color: "bg-secondary" },
  { label: "Behovsanalys", color: "bg-secondary" },
  { label: "Offert", color: "bg-accent" },
  { label: "Order", color: "bg-accent" },
  { label: "Leverans", color: "bg-primary" },
  { label: "Uppföljning", color: "bg-primary" },
];

export default function ProcessCard() {
  return (
    <div className="bg-card rounded-xl border border-border card-shine">
      <div className="p-4 pb-3 flex items-center justify-between">
        <h3 className="font-heading font-semibold text-card-foreground">Process</h3>
        <div className="flex items-center gap-1">
          <button className="p-1.5 rounded-md hover:bg-muted transition-colors">
            <Settings className="h-3.5 w-3.5 text-muted-foreground" />
          </button>
          <button className="p-1.5 rounded-md hover:bg-muted transition-colors">
            <Maximize2 className="h-3.5 w-3.5 text-muted-foreground" />
          </button>
        </div>
      </div>
      <div className="px-4 pb-4">
        <div className="flex items-center gap-1 mb-3">
          <select className="text-sm border border-border rounded-lg px-3 py-1.5 bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-ring">
            <option>Svestra</option>
            <option>Källekullen</option>
          </select>
        </div>
        {/* Simple process flow visualization */}
        <div className="flex items-center gap-0 overflow-x-auto pb-2">
          {processSteps.map((step, i) => (
            <div key={i} className="flex items-center flex-shrink-0">
              <div
                className={`${step.color} text-primary-foreground text-[11px] font-medium px-3 py-2 rounded-md shadow-sm`}
              >
                {step.label}
              </div>
              {i < processSteps.length - 1 && (
                <div className="w-4 h-px bg-border" />
              )}
            </div>
          ))}
        </div>
        <p className="text-xs text-muted-foreground mt-3 italic leading-relaxed">
          Svestra är sammansättningen av namnen Svenningsson och Strandberg, detta utifrån verksamhetsinnehavarnas efternamn.
        </p>
      </div>
    </div>
  );
}
