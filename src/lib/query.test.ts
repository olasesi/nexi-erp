import { describe, expect, it } from "vitest";

import { STALE_TIME_MS, createQueryClient } from "@/lib/query";

type RetryFn = (failureCount: number, error: unknown) => boolean;

function retryOf(client: ReturnType<typeof createQueryClient>): RetryFn {
  return client.getDefaultOptions().queries?.retry as RetryFn;
}

describe("createQueryClient", () => {
  it("uses a 30s staleness window", () => {
    expect(STALE_TIME_MS).toBe(30_000);
    expect(createQueryClient().getDefaultOptions().queries?.staleTime).toBe(STALE_TIME_MS);
  });

  it("never retries 4xx client errors", () => {
    const retry = retryOf(createQueryClient());
    expect(retry(0, { status: 401 })).toBe(false);
    expect(retry(0, { status: 403 })).toBe(false);
    expect(retry(0, { status: 404 })).toBe(false);
  });

  it("retries server errors twice before giving up", () => {
    const retry = retryOf(createQueryClient());
    expect(retry(0, { status: 500 })).toBe(true);
    expect(retry(1, { status: 503 })).toBe(true);
    expect(retry(2, { status: 500 })).toBe(false);
  });

  it("retries unknown errors up to the limit", () => {
    const retry = retryOf(createQueryClient());
    expect(retry(0, new Error("network down"))).toBe(true);
    expect(retry(2, new Error("network down"))).toBe(false);
  });
});
