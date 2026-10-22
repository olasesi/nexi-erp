import { defineConfig, type PluginOption } from "vite";
import react from "@vitejs/plugin-react";
import path from "path";

export default defineConfig(async ({ mode }) => {
  const plugins: PluginOption[] = [react()];

  // `rollup-plugin-visualizer` is ESM-only, so it is imported lazily. This keeps
  // the config loadable under CJS and pulls the plugin in only when asked for.
  if (process.env.ANALYZE) {
    const { visualizer } = await import("rollup-plugin-visualizer");
    plugins.push(
      visualizer({
        filename: "stats.html",
        title: "Nexi ERP bundle analysis",
        template: "treemap",
        gzipSize: true,
        brotliSize: true,
      }),
    );
  }

  return {
    plugins,
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
    extensions: [".ts", ".tsx", ".js", ".jsx", ".json"],
  },
  define: {
    __APP_VERSION__: JSON.stringify(process.env.npm_package_version ?? "0.1.0"),
    __BUILD_MODE__: JSON.stringify(mode),
  },
  server: {
    port: 3000,
    proxy: {
      "/api": {
        target: "http://localhost:8000",
        changeOrigin: true,
      },
    },
  },
  build: {
    outDir: "dist",
    sourcemap: true,
    chunkSizeWarningLimit: 700,
    rollupOptions: {
      output: {
        manualChunks(id: string) {
          if (!id.includes("node_modules")) return undefined;
          if (
            id.includes("recharts") ||
            id.includes("/d3-") ||
            id.includes("victory-vendor") ||
            id.includes("internmap") ||
            id.includes("delaunator") ||
            id.includes("robust-predicates") ||
            id.includes("decimal.js")
          ) {
            return "vendor-charts";
          }
          if (id.includes("@sentry")) return "vendor-monitoring";
          if (id.includes("@tanstack")) return "vendor-query";
          if (
            id.includes("react-router") ||
            id.includes("/react/") ||
            id.includes("/react-dom/") ||
            id.includes("/scheduler/") ||
            id.includes("use-sync-external-store")
          ) {
            return "vendor-react";
          }
          return "vendor";
        },
      },
    },
  },
  };
});
