"use client";

import * as Sentry from "@sentry/nextjs";
import { useEffect } from "react";

import { logger } from "@/lib/logger";

export default function GlobalErrorBoundary({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    logger.error("Unhandled application error", { name: error.name, message: error.message });
    if (error.digest) {
      Sentry.captureException(error, { tags: { digest: error.digest } });
    } else {
      Sentry.captureException(error);
    }
  }, [error]);

  return (
    <div className="flex min-h-screen flex-col items-center justify-center bg-gray-50 px-4 text-center">
      <h1 className="text-3xl font-bold text-gray-900">Something went wrong</h1>
      <p className="mt-2 text-gray-600">An unexpected error occurred. Please try again.</p>
      <button
        type="button"
        onClick={reset}
        className="mt-6 rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
      >
        Try again
      </button>
    </div>
  );
}
