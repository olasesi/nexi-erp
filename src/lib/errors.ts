import { ApiError } from "@/lib/api";

/**
 * Laravel returns validation failures as `{ message, errors: { field: [msg] } }`.
 * Surface the first field message so forms can show something actionable
 * instead of the generic "The given data was invalid.".
 */
export function errorMessage(error: unknown, fallback = "Something went wrong."): string {
  if (error instanceof ApiError) {
    const errors = (error.data as { errors?: Record<string, string[]> } | null)?.errors;
    const first = errors ? Object.values(errors).flat()[0] : undefined;
    if (first) return first;
  }

  if (error instanceof Error && error.message) return error.message;
  return fallback;
}
