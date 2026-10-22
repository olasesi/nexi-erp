import { Component, type ErrorInfo, type ReactNode } from "react";

import { logger } from "@/lib/logger";
import { Button } from "@/components/ui/button";

interface Props {
  children: ReactNode;
}

interface State {
  hasError: boolean;
}

export class ErrorBoundary extends Component<Props, State> {
  state: State = { hasError: false };

  static getDerivedStateFromError(): State {
    return { hasError: true };
  }

  componentDidCatch(error: Error, info: ErrorInfo): void {
    logger.error("Unhandled application error", {
      name: error.name,
      message: error.message,
      componentStack: info.componentStack,
    });
  }

  render(): ReactNode {
    if (this.state.hasError) {
      return (
        <div className="crash">
          <h1 className="crash__title">Something went wrong</h1>
          <p className="crash__description">An unexpected error occurred. Please try again.</p>
          <div className="crash__action">
            <Button variant="primary" onClick={() => this.setState({ hasError: false })}>
              Try again
            </Button>
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}
