import { cn } from "@/Lib/utils";

const riskData = [
  [0, 3, 0, 0, 0],
  [16, 4, 0, 0, 0],
  [22, 38, 14, 0, 1],
  [10, 12, 60, 14, 2],
];

const rowColors = [
  ["risk-cell-green", "risk-cell-yellow", "risk-cell-orange", "risk-cell-red", "risk-cell-darkred"],
  ["risk-cell-green", "risk-cell-green", "risk-cell-yellow", "risk-cell-orange", "risk-cell-red"],
  ["risk-cell-green", "risk-cell-green", "risk-cell-green", "risk-cell-yellow", "risk-cell-orange"],
  ["risk-cell-green", "risk-cell-green", "risk-cell-green", "risk-cell-green", "risk-cell-yellow"],
];

const consequences = ["Obetydlig", "Lindrig", "Måttlig", "Betydande", "Allvarlig"];
const likelihoods = ["Mycket troligt", "Troligt", "Möjligt", "Osannolikt"];

export default function RiskOverview() {
  return (
    <div className="bg-card rounded-xl border border-border card-shine">
      <div className="p-4 pb-3">
        <h3 className="font-heading font-semibold text-card-foreground">Risköversikt</h3>
      </div>
      <div className="px-4 pb-4">
        <div className="grid grid-cols-6 gap-px bg-border rounded-lg overflow-hidden text-xs">
          {/* Header row */}
          <div className="bg-muted p-2 flex items-center justify-center font-medium text-muted-foreground" />
          {consequences.map((c) => (
            <div key={c} className="bg-muted p-1.5 flex items-center justify-center font-medium text-muted-foreground text-center text-[10px] leading-tight">
              {c}
            </div>
          ))}
          {/* Data rows */}
          {riskData.map((row, ri) => (
            <>
              <div key={`label-${ri}`} className="bg-muted p-1.5 flex items-center justify-center font-medium text-muted-foreground text-[10px] text-center leading-tight">
                {likelihoods[ri]}
              </div>
              {row.map((val, ci) => (
                <div
                  key={`${ri}-${ci}`}
                  className={cn(
                    "p-2 flex items-center justify-center font-bold text-sm transition-transform hover:scale-105 cursor-pointer",
                    rowColors[ri][ci]
                  )}
                >
                  {val > 0 ? val : ""}
                </div>
              ))}
            </>
          ))}
        </div>
      </div>
    </div>
  );
}
