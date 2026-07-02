import { Badge } from "@/Components/ui/badge";
import { useMemo, useState } from "react";
import type { SelectOption } from "./types";

interface CollapsedMultiValueBadgesProps {
  values: any[];
  options: SelectOption[];
  maxVisible?: number;
  moreLabel: (remaining: number) => string;
}

export function CollapsedMultiValueBadges({
  values,
  options,
  maxVisible = 2,
  moreLabel,
}: CollapsedMultiValueBadgesProps) {
  const [expanded, setExpanded] = useState(false);

  const resolvedValues = useMemo(
    () => values.map((value) => ({
      key: String(value),
      label: options.find((option) => String(option.value) === String(value))?.label ?? String(value),
    })),
    [options, values],
  );

  const visibleValues = expanded ? resolvedValues : resolvedValues.slice(0, maxVisible);
  const remaining = resolvedValues.length - visibleValues.length;

  return (
    <div className="flex flex-wrap items-center gap-1">
      {visibleValues.map((value, index) => (
        <Badge key={`${value.key}-${index}`} variant="secondary" className="text-xs">
          {value.label}
        </Badge>
      ))}
      {!expanded && remaining > 0 && (
        <button
          type="button"
          className="text-xs text-muted-foreground underline-offset-2 hover:underline"
          onClick={() => setExpanded(true)}
        >
          {moreLabel(remaining)}
        </button>
      )}
    </div>
  );
}

