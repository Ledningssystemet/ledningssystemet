import * as React from "react";
import Select from "react-select";
import CreatableSelect from "react-select/creatable";
import { SelectOption } from "./types";
import { useTranslations } from "@/hooks/useTranslations";

interface Select2FieldProps {
  options: SelectOption[];
  value: any;
  onChange: (value: any) => void;
  placeholder?: string;
  isMulti?: boolean;
  isDisabled?: boolean;
  isCreatable?: boolean;
  onCreateOption?: (inputValue: string) => void;
  optionsUrl?: string;
  createOptionUrl?: string;
  optionValueKey?: string;
  optionLabelKey?: string;
  createOptionPayloadKey?: string;
  tags?: boolean;
  withinDialog?: boolean;
}

const customStyles = {
  control: (base: any, state: any) => ({
    ...base,
    backgroundColor: "hsl(var(--background))",
    borderColor: state.isFocused ? "hsl(var(--ring))" : "hsl(var(--border))",
    borderRadius: "calc(var(--radius) - 2px)",
    minHeight: "2.5rem",
    boxShadow: state.isFocused ? "0 0 0 2px hsl(var(--ring) / 0.2)" : "none",
    "&:hover": { borderColor: "hsl(var(--ring))" },
  }),
  menu: (base: any) => ({
    ...base,
    backgroundColor: "hsl(var(--popover))",
    border: "1px solid hsl(var(--border))",
    borderRadius: "var(--radius)",
    zIndex: 99999,
  }),
  menuPortal: (base: any) => ({
    ...base,
    zIndex: 99999,
  }),
  option: (base: any, state: any) => ({
    ...base,
    backgroundColor: state.isSelected
      ? "hsl(var(--primary))"
      : state.isFocused
        ? "hsl(var(--accent))"
        : "hsl(var(--popover))",
    color: state.isSelected
      ? "hsl(var(--primary-foreground))"
      : "hsl(var(--popover-foreground))",
    cursor: "pointer",
    fontSize: "0.875rem",
  }),
  multiValue: (base: any) => ({
    ...base,
    backgroundColor: "hsl(var(--accent))",
    borderRadius: "calc(var(--radius) - 4px)",
  }),
  multiValueLabel: (base: any) => ({
    ...base,
    color: "hsl(var(--accent-foreground))",
    fontSize: "0.8rem",
  }),
  multiValueRemove: (base: any) => ({
    ...base,
    color: "hsl(var(--muted-foreground))",
    "&:hover": {
      backgroundColor: "hsl(var(--destructive))",
      color: "hsl(var(--destructive-foreground))",
    },
  }),
  singleValue: (base: any) => ({
    ...base,
    color: "hsl(var(--foreground))",
  }),
  placeholder: (base: any) => ({
    ...base,
    color: "hsl(var(--muted-foreground))",
    fontSize: "0.875rem",
  }),
  input: (base: any) => ({
    ...base,
    color: "hsl(var(--foreground))",
  }),
  menuList: (base: any) => ({
    ...base,
    backgroundColor: "hsl(var(--popover))",
  }),
};

function toSelectOption(
  raw: Record<string, any>,
  valueKey: string,
  labelKey: string,
): SelectOption | null {
  const value = raw[valueKey];
  if (value === undefined || value === null) {
    return null;
  }

  const labelRaw = raw[labelKey] ?? raw.name ?? raw.label ?? value;
  return {
    value,
    label: String(labelRaw),
  };
}

function uniqOptions(options: SelectOption[]): SelectOption[] {
  const map = new Map<string, SelectOption>();
  for (const option of options) {
    map.set(String(option.value), option);
  }
  return Array.from(map.values());
}

export const Select2Field = React.forwardRef<any, Select2FieldProps>(function Select2Field(
  {
    options,
    value,
    onChange,
    placeholder,
    isMulti = false,
    isDisabled,
    isCreatable = false,
    onCreateOption,
    optionsUrl,
    createOptionUrl,
    optionValueKey = "id",
    optionLabelKey = "name",
    createOptionPayloadKey = "name",
    tags = false,
    withinDialog = false,
  },
  ref,
) {
  const { t } = useTranslations();
  const [remoteOptions, setRemoteOptions] = React.useState<SelectOption[]>([]);

  React.useEffect(() => {
    if (!optionsUrl) {
      setRemoteOptions([]);
      return;
    }

    let cancelled = false;

    fetch(optionsUrl, {
      headers: { Accept: "application/json" },
    })
      .then((res) => (res.ok ? res.json() : Promise.resolve([])))
      .then((json) => {
        if (cancelled) return;

        const rows = Array.isArray(json)
          ? json
          : Array.isArray(json?.data)
            ? json.data
            : [];

        const normalized = rows
          .map((row: Record<string, any>) => toSelectOption(row, optionValueKey, optionLabelKey))
          .filter((item: SelectOption | null): item is SelectOption => item !== null);

        setRemoteOptions(normalized);
      })
      .catch(() => {
        if (!cancelled) {
          setRemoteOptions([]);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [optionsUrl, optionValueKey, optionLabelKey]);

  const mergedOptions = React.useMemo(() => {
    const base = options.map((o) => ({ value: o.value, label: o.label }));
    return uniqOptions([...base, ...remoteOptions]);
  }, [options, remoteOptions]);

  const selectedValue = React.useMemo(() => {
    if (isMulti) {
      const selectedArray = Array.isArray(value) ? value : value ? [value] : [];
      const selectedSet = new Set(selectedArray.map((v) => String(v)));

      const existing = mergedOptions.filter((o) => selectedSet.has(String(o.value)));
      const missing = selectedArray
        .filter((v) => !existing.some((o) => String(o.value) === String(v)))
        .map((v) => ({ value: v, label: String(v) }));

      return [...existing, ...missing];
    }

    if (value === undefined || value === null || value === "") {
      return null;
    }

    return mergedOptions.find((o) => String(o.value) === String(value)) || { value, label: String(value) };
  }, [isMulti, mergedOptions, value]);

  const handleChange = (selected: any) => {
    if (isMulti) {
      onChange(selected ? selected.map((s: any) => s.value) : []);
    } else {
      onChange(selected ? selected.value : "");
    }
  };

  const canCreate = Boolean(isCreatable || tags || onCreateOption || createOptionUrl || optionsUrl);

  const handleCreate = async (inputValue: string) => {
    const trimmed = inputValue.trim();
    if (!trimmed) return;

    if (onCreateOption) {
      onCreateOption(trimmed);
      return;
    }

    const endpoint = createOptionUrl || optionsUrl;
    if (!endpoint) {
      if (isMulti) {
        const current = Array.isArray(value) ? value : value ? [value] : [];
        onChange([...current, trimmed]);
      } else {
        onChange(trimmed);
      }
      return;
    }

    try {
      const response = await fetch(endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({
          [createOptionPayloadKey]: trimmed,
        }),
      });

      if (!response.ok) {
        return;
      }

      const json = await response.json();
      const raw = (json?.data && !Array.isArray(json.data)) ? json.data : json;
      const created = toSelectOption(raw, optionValueKey, optionLabelKey) ?? {
        value: trimmed,
        label: trimmed,
      };

      setRemoteOptions((prev) => uniqOptions([...prev, created]));

      if (isMulti) {
        const current = Array.isArray(value) ? value : value ? [value] : [];
        onChange([...current, created.value]);
      } else {
        onChange(created.value);
      }
    } catch {
      // Silently ignore creation errors and keep current selection unchanged.
    }
  };

  const Component = canCreate ? CreatableSelect : Select;

  return (
    <Component
      ref={ref}
      isMulti={isMulti}
      options={mergedOptions}
      value={selectedValue}
      onChange={handleChange}
      onCreateOption={canCreate ? handleCreate : undefined}
      placeholder={placeholder || t("ui.crud.select_placeholder")}
      styles={customStyles}
      isDisabled={isDisabled}
      noOptionsMessage={() => t("ui.crud.select.no_options")}
      formatCreateLabel={(input: string) => t("ui.crud.select.create_label", { input })}
      isClearable
      closeMenuOnSelect={!isMulti}
      menuPortalTarget={typeof document !== "undefined" ? document.body : undefined}
      menuPosition="fixed"
      menuPlacement="bottom"
      menuShouldScrollIntoView={false}
      menuShouldBlockScroll={withinDialog}
      classNamePrefix="select2"
    />
  );
});
