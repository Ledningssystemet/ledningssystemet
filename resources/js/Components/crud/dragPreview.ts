import { DragEvent } from "react";

const DRAG_ITEM_SELECTOR = "[data-crud-drag-item]";

/**
 * Creates a drag preview that mirrors the dragged item so the user can track movement.
 */
export function setupDragPreview(event: DragEvent<HTMLElement>) {
  if (typeof document === "undefined") {
    return () => {};
  }

  const source = event.currentTarget as HTMLElement;
  const previewSource = source.closest<HTMLElement>(DRAG_ITEM_SELECTOR) ?? source;
  previewSource.setAttribute("data-crud-dragging", "true");
  const preview = previewSource.cloneNode(true) as HTMLElement;
  const sourceRect = previewSource.getBoundingClientRect();

  preview.style.position = "fixed";
  preview.style.top = "-10000px";
  preview.style.left = "-10000px";
  preview.style.width = `${Math.max(sourceRect.width, 180)}px`;
  preview.style.maxWidth = "min(90vw, 960px)";
  preview.style.pointerEvents = "none";
  preview.style.opacity = "0.98";
  preview.style.transform = "scale(1.01)";
  preview.style.boxShadow = "0 16px 40px rgba(0, 0, 0, 0.28)";
  preview.style.background = "var(--background)";
  preview.style.border = "1px solid color-mix(in srgb, var(--primary) 55%, var(--border) 45%)";
  preview.style.borderRadius = "0.5rem";
  preview.style.zIndex = "9999";

  document.body.appendChild(preview);

  const x = Number.isFinite(event.clientX) ? event.clientX - sourceRect.left : 16;
  const y = Number.isFinite(event.clientY) ? event.clientY - sourceRect.top : 16;
  const dragX = Math.max(8, Math.min(x, sourceRect.width - 8 || 8));
  const dragY = Math.max(8, Math.min(y, sourceRect.height - 8 || 8));

  event.dataTransfer.setDragImage(preview, dragX, dragY);

  return () => {
    previewSource.removeAttribute("data-crud-dragging");
    if (preview.parentNode) {
      preview.parentNode.removeChild(preview);
    }
  };
}

