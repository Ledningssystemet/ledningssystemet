import React, { type ReactNode } from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { t } from "../../../lib/i18n";
import { ApiError, crud, type DataSource, type ListQuery } from "./api";
import type { Item } from "./types";

export function csvCell(value: string) {
  const safe = /^[\s\uFEFF]*[=+\-@]|^[\s\uFEFF]*[\t\r\n]/.test(value) ? `'${value}` : value;
  return `"${safe.replace(/"/g, '""')}"`;
}

export function createCsv(columns: string[], rows: Item[], label: (key: string) => string, value: (key: string, item: Item) => string) {
  return [
    columns.map((key) => csvCell(label(key))).join(";"),
    ...rows.map((item) => columns.map((key) => csvCell(value(key, item))).join(";")),
  ].join("\r\n");
}

interface ExportOptions {
  dataSource?: DataSource;
  query: ListQuery;
  rows: Item[];
  columns: string[];
  label: (key: string) => string;
  renderValue: (key: string, item: Item) => ReactNode;
  filename: string;
}

export async function exportTableCsv({ dataSource, query, rows, columns, label, renderValue, filename }: ExportOptions) {
  if (dataSource) {
    const result = await crud.list(dataSource, query);
    if ((result.lastPage !== null && result.lastPage > 1) || (result.total !== null && result.total > result.items.length)) {
      throw new ApiError(0, t("The server did not return all rows for export."));
    }
    rows = result.items;
  }

  const text = (node: ReactNode) => {
    const template = document.createElement("template");
    template.innerHTML = renderToStaticMarkup(<>{node}</>);
    return template.content.textContent ?? "";
  };
  const csv = createCsv(columns, rows, label, (key, item) => text(renderValue(key, item)));
  const url = URL.createObjectURL(new Blob(["\uFEFF", csv], { type: "text/csv;charset=utf-8" }));
  const anchor = document.createElement("a");
  try {
    anchor.href = url;
    anchor.download = `${(filename || "export").replace(/[<>:"/\\|?*\x00-\x1f]/g, "_")}.csv`;
    document.body.appendChild(anchor);
    anchor.click();
  } finally {
    anchor.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  }
}
