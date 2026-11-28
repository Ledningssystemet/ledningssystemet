import React from "react";
import * as Label from "@radix-ui/react-label";
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from "lucide-react";
import { RSelect } from "./RSelect";
import { btnOutline } from "../helpers/styles";
import { t } from "../../../lib/i18n";
import { translate } from "../helpers/translate";

type Props = {
  range: { from: number; to: number; total: number };
  page: number;
  pageSize: number;
  totalPages: number;
  pageSizeOptions: number[];
  onPageChange: (page: number) => void;
  onPageSizeChange: (pageSize: number) => void;
};

const options = (values: (string | number)[]) => values.map((value) => ({ value: String(value), label: String(value) }));

export function TablePagination({ range, page, pageSize, totalPages, pageSizeOptions, onPageChange, onPageSizeChange }: Props) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-2 border-t px-3 py-2 text-sm">
      <span className="text-muted-foreground">{translate("Showing {from}–{to} of {total}", range)}</span>
      <div className="flex flex-wrap items-center gap-2">
        {pageSizeOptions.length > 0 && (
          <div className="flex items-center gap-2">
            <Label.Root className="text-muted-foreground">{t("Items per page")}</Label.Root>
            <RSelect label={t("Items per page")} value={String(pageSize)} onChange={(value) => onPageSizeChange(Number(value))} placeholder="" options={options(pageSizeOptions)} />
          </div>
        )}
        <div className="flex items-center gap-1">
          <button className={`${btnOutline} px-2`} aria-label={t("First page")} disabled={page <= 1} onClick={() => onPageChange(1)}><ChevronsLeft className="h-4 w-4" /></button>
          <button className={`${btnOutline} px-2`} aria-label={t("Previous page")} disabled={page <= 1} onClick={() => onPageChange(page - 1)}><ChevronLeft className="h-4 w-4" /></button>
          <span className="flex items-center gap-1 px-1 text-muted-foreground">{t("Page")}
            <span className="w-20"><RSelect label={t("Go to page")} value={String(page)} onChange={(value) => onPageChange(Number(value))} placeholder="" options={options(Array.from({ length: totalPages }, (_, index) => index + 1))} /></span>
            {translate("of {total}", { total: totalPages })}</span>
          <button className={`${btnOutline} px-2`} aria-label={t("Next page")} disabled={page >= totalPages} onClick={() => onPageChange(page + 1)}><ChevronRight className="h-4 w-4" /></button>
          <button className={`${btnOutline} px-2`} aria-label={t("Last page")} disabled={page >= totalPages} onClick={() => onPageChange(totalPages)}><ChevronsRight className="h-4 w-4" /></button>
        </div>
      </div>
    </div>
  );
}
