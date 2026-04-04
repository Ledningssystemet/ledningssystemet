import axios from 'axios';
import { useCallback, useEffect, useRef, useState } from 'react';

const SESSION_EXPIRED_EVENT = 'session:expired';
const SESSION_PING_URL = '/api/session/ping';
const HEARTBEAT_INTERVAL_MS = 4 * 60 * 1000;
const HEARTBEAT_TICK_MS = 60 * 1000;
const ACTIVE_WINDOW_MS = 15 * 60 * 1000;

export default function SessionGuard() {
  const [sessionExpired, setSessionExpired] = useState(false);
  const [isVerifying, setIsVerifying] = useState(false);

  const lastActivityAtRef = useRef<number>(Date.now());
  const lastPingAtRef = useRef<number>(0);
  const inFlightPingRef = useRef<Promise<boolean> | null>(null);

  const pingSession = useCallback(async (force: boolean = false): Promise<boolean> => {
    if (inFlightPingRef.current) {
      return inFlightPingRef.current;
    }

    const now = Date.now();

    if (!force) {
      if (document.visibilityState !== 'visible') {
        return true;
      }

      if (now - lastActivityAtRef.current > ACTIVE_WINDOW_MS) {
        return true;
      }

      if (now - lastPingAtRef.current < HEARTBEAT_INTERVAL_MS) {
        return true;
      }
    }

    const pingPromise = axios
      .get(SESSION_PING_URL, {
        headers: {
          'X-Session-Heartbeat': '1',
        },
      })
      .then(() => {
        lastPingAtRef.current = Date.now();
        return true;
      })
      .catch((error) => {
        const status = error?.response?.status;

        if (status === 401 || status === 419) {
          setSessionExpired(true);
        }

        return false;
      })
      .finally(() => {
        inFlightPingRef.current = null;
      });

    inFlightPingRef.current = pingPromise;
    return pingPromise;
  }, []);

  useEffect(() => {
    const markActivity = (): void => {
      lastActivityAtRef.current = Date.now();
    };

    const activityEvents: Array<keyof WindowEventMap> = [
      'click',
      'keydown',
      'mousemove',
      'scroll',
      'touchstart',
      'input',
    ];

    const onVisibilityChange = (): void => {
      if (document.visibilityState === 'visible') {
        void pingSession(true);
      }
    };

    const onFocus = (): void => {
      markActivity();
      void pingSession(true);
    };

    const onSessionExpired = (): void => {
      setSessionExpired(true);
    };

    activityEvents.forEach((eventName) => {
      window.addEventListener(eventName, markActivity, { passive: true });
    });

    window.addEventListener('focus', onFocus);
    window.addEventListener(SESSION_EXPIRED_EVENT, onSessionExpired);
    document.addEventListener('visibilitychange', onVisibilityChange);

    const intervalId = window.setInterval(() => {
      void pingSession(false);
    }, HEARTBEAT_TICK_MS);

    void pingSession(true);

    return () => {
      activityEvents.forEach((eventName) => {
        window.removeEventListener(eventName, markActivity);
      });

      window.removeEventListener('focus', onFocus);
      window.removeEventListener(SESSION_EXPIRED_EVENT, onSessionExpired);
      document.removeEventListener('visibilitychange', onVisibilityChange);
      window.clearInterval(intervalId);
    };
  }, [pingSession]);

  const openLoginInNewTab = (): void => {
    window.open('/login', '_blank', 'noopener,noreferrer');
  };

  const verifyRestoredSession = async (): Promise<void> => {
    setIsVerifying(true);

    const alive = await pingSession(true);

    setIsVerifying(false);

    if (alive) {
      setSessionExpired(false);
    }
  };

  if (!sessionExpired) {
    return null;
  }

  return (
    <div className="fixed inset-0 z-[120] flex items-center justify-center bg-foreground/50 p-4">
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="session-expired-title"
        className="w-full max-w-lg rounded-xl border border-border bg-card p-6 shadow-2xl"
      >
        <h2 id="session-expired-title" className="text-lg font-semibold text-card-foreground">
          Din session har avslutats
        </h2>
        <p className="mt-2 text-sm text-muted-foreground">
          Du verkar vara utloggad. Oppna inloggningen i en ny flik och logga in dar for att undvika att
          forlora andringar du har gjort i den har fliken.
        </p>

        <div className="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-end">
          <button
            type="button"
            onClick={verifyRestoredSession}
            disabled={isVerifying}
            className="inline-flex items-center justify-center rounded-md border border-border px-4 py-2 text-sm font-medium text-card-foreground hover:bg-muted disabled:cursor-not-allowed disabled:opacity-60"
          >
            {isVerifying ? 'Kontrollerar...' : 'Jag har loggat in'}
          </button>

          <button
            type="button"
            onClick={openLoginInNewTab}
            className="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
          >
            Oppna inloggning i ny flik
          </button>
        </div>
      </div>
    </div>
  );
}

