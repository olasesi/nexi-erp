import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { BrowserRouter } from "react-router-dom";
import { QueryClientProvider } from "@tanstack/react-query";

import App from "./app";
import "@/styles/index.css";
import { createQueryClient } from "@/lib/query";
import { initMonitoring } from "@/lib/sentry";
import { reportWebVitals } from "@/lib/vitals";

initMonitoring();
reportWebVitals();

const queryClient = createQueryClient();

createRoot(document.getElementById("root")!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <App />
      </BrowserRouter>
    </QueryClientProvider>
  </StrictMode>,
);
