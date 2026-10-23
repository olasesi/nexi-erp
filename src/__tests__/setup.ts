import "@testing-library/jest-dom/vitest";

// Recharts' ResponsiveContainer observes its parent; jsdom ships no
// ResizeObserver, so provide a no-op implementation for chart tests.
if (!("ResizeObserver" in globalThis)) {
  class ResizeObserverStub {
    observe(): void {}
    unobserve(): void {}
    disconnect(): void {}
  }
  Object.defineProperty(globalThis, "ResizeObserver", {
    configurable: true,
    writable: true,
    value: ResizeObserverStub,
  });
}
