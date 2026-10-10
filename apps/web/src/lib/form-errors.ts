import type { FieldValues, Path, UseFormSetError } from "react-hook-form";
import { ApiError } from "@/lib/api-client";

/**
 * Copies API validation errors onto react-hook-form fields and returns the
 * message to show in the form-level alert (if any).
 */
export function applyApiErrors<T extends FieldValues>(
  error: unknown,
  setError: UseFormSetError<T>,
  fallback: string,
  fields: readonly Path<T>[] = [],
): string | null {
  if (!(error instanceof ApiError)) return fallback;

  let mapped = false;
  for (const [field, messages] of Object.entries(error.fieldErrors)) {
    if ((fields as readonly string[]).includes(field)) {
      setError(field as Path<T>, { type: "server", message: messages[0] });
      mapped = true;
    }
  }
  if (error.code === "validation_failed" && mapped) return null;
  if (error.code === "network_error") return error.message;
  return error.message || fallback;
}
