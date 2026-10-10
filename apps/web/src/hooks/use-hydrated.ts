import { useSyncExternalStore } from "react";

const subscribe = () => () => {};

/**
 * `false` during server rendering and before hydration, `true` afterwards. Submit buttons of
 * JavaScript-driven forms stay disabled until then, so an early click cannot fall back to a
 * native form submission that would silently reload the page.
 */
export function useHydrated(): boolean {
  return useSyncExternalStore(subscribe, () => true, () => false);
}
