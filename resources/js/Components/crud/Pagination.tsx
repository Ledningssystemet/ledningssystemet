import { Button } from "@/components/ui/button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { ChevronLeft, ChevronRight } from "lucide-react";

const PER_PAGE_OPTIONS = [10, 25, 50, 100, 250];

interface PaginationProps {
  page: number;
  totalPages: number;
  total: number;
  perPage: number;
  onPageChange: (page: number) => void;
  onPerPageChange: (perPage: number) => void;
}

export function CrudPagination({ page, totalPages, total, perPage, onPageChange, onPerPageChange }: PaginationProps) {
  if (total === 0) return null;

  return (
    <div className="flex items-center justify-between px-1 pt-3">
      <div className="flex items-center gap-3">
        <span className="text-sm text-muted-foreground">
          Totalt {total} poster
        </span>
        <Select
          value={String(perPage)}
          onValueChange={(v) => onPerPageChange(Number(v))}
        >
          <SelectTrigger className="w-[90px] h-8 text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {PER_PAGE_OPTIONS.map((n) => (
              <SelectItem key={n} value={String(n)}>
                {n} / sida
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
      {totalPages > 1 && (
        <div className="flex items-center gap-1">
          <Button
            variant="outline"
            size="icon"
            className="h-8 w-8"
            disabled={page <= 1}
            onClick={() => onPageChange(page - 1)}
          >
            <ChevronLeft className="h-4 w-4" />
          </Button>
          <span className="text-sm px-3 text-muted-foreground">
            {page} / {totalPages}
          </span>
          <Button
            variant="outline"
            size="icon"
            className="h-8 w-8"
            disabled={page >= totalPages}
            onClick={() => onPageChange(page + 1)}
          >
            <ChevronRight className="h-4 w-4" />
          </Button>
        </div>
      )}
    </div>
  );
}
