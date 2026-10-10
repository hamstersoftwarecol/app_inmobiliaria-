import { describe, expect, it } from "vitest";
import en from "@/lib/i18n/en";
import es from "@/lib/i18n/es";
import { translate } from "@/lib/i18n";

function keys(obj: object, prefix = ""): string[] {
  return Object.entries(obj).flatMap(([k, v]) => (typeof v === "string" ? [`${prefix}${k}`] : keys(v, `${prefix}${k}.`)));
}

describe("i18n", () => {
  it("Spanish dictionary covers every English key", () => {
    expect(keys(es).sort()).toEqual(keys(en).sort());
  });

  it("translates with interpolation and falls back to the key", () => {
    expect(translate("es", "dashboard.welcome", { name: "Ana" })).toBe("Bienvenido, Ana");
    expect(translate("en", "admin.pageOf", { page: 2, total: 5 })).toBe("Page 2 of 5");
    expect(translate("en", "does.not.exist")).toBe("does.not.exist");
  });
});
