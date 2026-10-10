"use client";

import { cn } from "@/lib/utils";

interface CheckboxGroupProps {
  legend: string;
  options: readonly string[];
  value: string[];
  onChange: (value: string[]) => void;
  label: (option: string) => string;
  error?: string;
  name: string;
}

/** Accessible multi-select rendered as a fieldset of checkbox "chips". */
export function CheckboxGroup({ legend, options, value, onChange, label, error, name }: CheckboxGroupProps) {
  const toggle = (option: string) =>
    onChange(value.includes(option) ? value.filter((v) => v !== option) : [...value, option]);

  return (
    <fieldset className="space-y-1.5" aria-describedby={error ? `${name}-error` : undefined}>
      <legend className="text-sm font-medium">{legend}</legend>
      <div className="flex flex-wrap gap-2">
        {options.map((option) => {
          const checked = value.includes(option);
          return (
            <label
              key={option}
              className={cn(
                "cursor-pointer rounded-full border px-3 py-1 text-xs font-medium transition-colors has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-ring",
                checked ? "border-brand-700 bg-brand-700 text-white" : "border-border bg-white text-foreground hover:bg-brand-50",
              )}
            >
              <input type="checkbox" className="sr-only" name={name} value={option} checked={checked} onChange={() => toggle(option)} />
              {label(option)}
            </label>
          );
        })}
      </div>
      {error && (
        <p id={`${name}-error`} role="alert" className="text-xs font-medium text-danger">
          {error}
        </p>
      )}
    </fieldset>
  );
}
