import { SelectOption } from "./types";

interface NativeSelectProps {
  options: SelectOption[];
  value: string | number | undefined;
  onChange: (value: string) => void;
  placeholder?: string;
  disabled?: boolean;
  className?: string;
}

export function NativeSelect({
  options,
  value,
  onChange,
  placeholder,
  disabled,
  className = "",
}: NativeSelectProps) {
  return (
    <select
      value={value != null ? String(value) : ""}
      onChange={(e) => onChange(e.target.value)}
      disabled={disabled}
      className={`flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 ${className}`}
    >
      {placeholder && (
        <option value="" disabled>
          {placeholder}
        </option>
      )}
      {options.map((opt) => (
        <option key={opt.value} value={String(opt.value)}>
          {opt.label}
        </option>
      ))}
    </select>
  );
}

interface NativeMultiSelectProps {
  options: SelectOption[];
  value: (string | number)[];
  onChange: (values: (string | number)[]) => void;
  placeholder?: string;
  disabled?: boolean;
  size?: number;
}

export function NativeMultiSelect({
  options,
  value,
  onChange,
  placeholder,
  disabled,
  size = 5,
}: NativeMultiSelectProps) {
  const selected = new Set(value?.map(String) ?? []);

  const handleChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const selectedOptions = Array.from(e.target.selectedOptions).map((o) => o.value);
    onChange(selectedOptions);
  };

  return (
    <select
      multiple
      size={Math.min(size, options.length || 1)}
      value={Array.from(selected)}
      onChange={handleChange}
      disabled={disabled}
      className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
    >
      {options.map((opt) => (
        <option key={opt.value} value={String(opt.value)}>
          {opt.label}
        </option>
      ))}
    </select>
  );
}
