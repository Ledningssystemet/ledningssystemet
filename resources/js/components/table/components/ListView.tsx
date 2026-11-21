import React from "react";
import * as Accordion from "@radix-ui/react-accordion";
import { ChevronDown } from "lucide-react";
import type { ReactNode } from "react";
import type { Item, ModuleConfig } from "../helpers/types";
import { t } from "../../../lib/i18n";

type Props = {
  items: Item[];
  config: ModuleConfig;
  renderStatus: (item: Item) => ReactNode;
  renderActions: (item: Item) => ReactNode;
  renderDetails: (item: Item) => ReactNode;
};

export function ListView({ items, config, renderStatus, renderActions, renderDetails }: Props) {
  return (
    <Accordion.Root type="multiple">
      {items.map((item) => {
        const status = renderStatus(item);
        return (
          <Accordion.Item key={item.id} value={item.id} className="ls-list-item">
            <Accordion.Header className="ls-list-header">
              <Accordion.Trigger className="ls-list-title">
                <span className="ls-list-copy">
                  <span className="ls-list-name">{item[config.titleKey]}</span>
                  {config.subtitleKeys.length > 0 && (
                    <span className="ls-list-subtitle">{config.subtitleKeys.map((key) => item[key]).filter(Boolean).join(" · ")}</span>
                  )}
                </span>
              </Accordion.Trigger>
              {status && <div className="ls-list-status">{status}</div>}
              <div className="ls-list-actions">{renderActions(item)}</div>
              <Accordion.Trigger className="ls-list-expand" aria-label={t("Show details")}>
                <ChevronDown aria-hidden="true" />
              </Accordion.Trigger>
            </Accordion.Header>
            <Accordion.Content className="ls-list-content">{renderDetails(item)}</Accordion.Content>
          </Accordion.Item>
        );
      })}
    </Accordion.Root>
  );
}
