import { GripVertical } from "lucide-react";
import type { ComponentProps } from "react";
import {
  Group as ResizableGroup,
  Panel as ResizablePanelPrimitive,
  Separator as ResizableSeparator,
} from "react-resizable-panels";

import { cn } from "@/lib/utils";

const ResizablePanelGroup = ({ className, ...props }: ComponentProps<typeof ResizableGroup>) => (
  <ResizableGroup
    className={cn("flex h-full w-full data-[panel-group-direction=vertical]:flex-col", className)}
    {...props}
  />
);

const ResizablePanel = ResizablePanelPrimitive;

const ResizableHandle = ({
  withHandle,
  className,
  ...props
}: ComponentProps<typeof ResizableSeparator> & {
  withHandle?: boolean;
}) => (
  <ResizableSeparator
    className={cn(
      "group relative flex w-1 items-center justify-center bg-border/60 hover:bg-border data-[panel-group-direction=horizontal]:cursor-col-resize data-[panel-group-direction=vertical]:h-1 data-[panel-group-direction=vertical]:w-full data-[panel-group-direction=vertical]:cursor-row-resize after:absolute after:inset-y-0 after:left-1/2 after:w-3 after:-translate-x-1/2 data-[panel-group-direction=vertical]:after:left-0 data-[panel-group-direction=vertical]:after:h-3 data-[panel-group-direction=vertical]:after:w-full data-[panel-group-direction=vertical]:after:-translate-y-1/2 data-[panel-group-direction=vertical]:after:translate-x-0 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring focus-visible:ring-offset-1 [&[data-panel-group-direction=vertical]>div]:rotate-90",
      className,
    )}
    {...props}
  >
    {withHandle && (
      <div className="z-10 flex h-4 w-3 items-center justify-center rounded-sm border bg-border/90 transition-colors group-hover:bg-border">
        <GripVertical className="h-2.5 w-2.5" />
      </div>
    )}
  </ResizableSeparator>
);

export { ResizablePanelGroup, ResizablePanel, ResizableHandle };
