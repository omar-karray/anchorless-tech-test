import { useEffect, useState } from "react";
import type Echo from "laravel-echo";
import type Pusher from "pusher-js";

import { ensureEchoInstance } from "../lib/echo.client";

type ConnectionStatus =
  | "idle"
  | "connecting"
  | "connected"
  | "unavailable"
  | "error";

export function useEchoConnection() {
  const [status, setStatus] = useState<ConnectionStatus>("idle");
  const [echo, setEcho] = useState<Echo | null>(null);
  const [lastError, setLastError] = useState<unknown>(null);

  useEffect(() => {
    let isMounted = true;

    setStatus("connecting");

    let cleanup: (() => void) | undefined;

    ensureEchoInstance().then((instance) => {
      if (!isMounted) return;

      if (!instance) {
        setStatus("unavailable");
        return;
      }

      setEcho(instance);

      const pusher = (instance.connector as any).pusher as Pusher | undefined;
      const connection = pusher?.connection;

      const handleConnected = () => setStatus("connected");
      const handleDisconnected = () => setStatus("error");
      const handleError = (error: unknown) => {
        console.error("[Anchorless] Reverb connection error:", error);
        setLastError(error);
        setStatus("error");
      };

      if (connection?.state === "connected") {
        setStatus("connected");
      }

      connection?.bind("connected", handleConnected);
      connection?.bind("disconnected", handleDisconnected);
      connection?.bind("error", handleError);

      cleanup = () => {
        connection?.unbind("connected", handleConnected);
        connection?.unbind("disconnected", handleDisconnected);
        connection?.unbind("error", handleError);
      };

    });

    return () => {
      isMounted = false;
       cleanup?.();
    };
  }, []);

  return { status, echo, error: lastError };
}
