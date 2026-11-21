import * as Accordion from "@radix-ui/react-accordion";
import { ChevronRight } from "lucide-react";
import type { ReactNode } from "react";
import type { Item, ModuleConfig } from "../helpers/types";

type Props = {
  items: Item[];
  config: ModuleConfig;
  renderStatus: (item: Item) => ReactNode;
  renderActions: (item: Item) => ReactNode;
  renderDetails: (item: Item) => ReactNode;
};

export function AccordionView({ items, config, renderStatus, renderActions, renderDetails }: Props) {
  return (
    <Accordion.Root type="multiple">
      {items.map((item) => (
        <Accordion.Item key={item.id} value={item.id} className="border-b">
          <Accordion.Header className="flex items-center">
            <Accordion.Trigger className="group flex flex-1 items-center gap-3 px-4 py-3 text-left hover:bg-surface-hover">
              <ChevronRight className="h-4 w-4 transition-transform group-data-[state=open]:rotate-90" />
              <div><div className="font-medium">{item[config.titleKey]}{renderStatus(item)}</div><div className="text-xs text-muted-foreground">{config.subtitleKeys.map((key) => item[key]).filter(Boolean).join(" · ")}</div></div>
            </Accordion.Trigger>
            <div className="pr-3">{renderActions(item)}</div>
          </Accordion.Header>
          <Accordion.Content className="bg-background px-6 py-4">{renderDetails(item)}</Accordion.Content>
        </Accordion.Item>
      ))}
    </Accordion.Root>
  );
}
