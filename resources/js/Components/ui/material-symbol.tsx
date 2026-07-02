import * as React from "react";

import { cn } from "@/Lib/utils";

const HEIGHT_CLASS_PATTERN = /(?:^|\s)h-([\d.]+)/;

function classHeightToPx(className?: string): number | undefined {
  if (!className) {
    return undefined;
  }

  const match = className.match(HEIGHT_CLASS_PATTERN);
  if (!match) {
    return undefined;
  }

  const value = Number.parseFloat(match[1]);
  if (Number.isNaN(value)) {
    return undefined;
  }

  return value * 4;
}

export interface MaterialSymbolProps extends React.HTMLAttributes<HTMLSpanElement> {
  name: string;
  size?: number;
}

export const MaterialSymbol = React.forwardRef<HTMLSpanElement, MaterialSymbolProps>(
  ({ name, size, className, style, children, ...props }, ref) => {
    const resolvedSize = size ?? classHeightToPx(className) ?? 16;

    return (
      <span
        ref={ref}
        className={cn("material-symbols-outlined inline-flex select-none items-center justify-center", className)}
        style={{
          fontSize: resolvedSize,
          width: resolvedSize,
          height: resolvedSize,
          lineHeight: 1,
          ...style,
        }}
        {...props}
      >
        {name}
        {children}
      </span>
    );
  }
);

MaterialSymbol.displayName = "MaterialSymbol";

