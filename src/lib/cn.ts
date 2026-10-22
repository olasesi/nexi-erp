/**
 * Combines conditional class names into a single string.
 *
 * Previously this also merged Tailwind utilities via `tailwind-merge`. Now
 * that styling is plain BEM CSS, class names are static strings plus optional
 * modifier flags, so plain `clsx` is all that is needed.
 */
import { clsx, type ClassValue } from "clsx";

export function cn(...inputs: ClassValue[]): string {
  return clsx(inputs);
}
