import { create } from "zustand";
import { createJSONStorage, persist } from "zustand/middleware";

export type Locale = "en" | "es";

interface LocaleState {
  locale: Locale;
  setLocale: (locale: Locale) => void;
}

/** UI language preference (not sensitive, safe to persist in localStorage). */
export const useLocaleStore = create<LocaleState>()(
  persist(
    (set) => ({
      locale: "en",
      setLocale: (locale) => set({ locale }),
    }),
    { name: "hitlease-locale", storage: createJSONStorage(() => localStorage) },
  ),
);
