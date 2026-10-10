import { describe, expect, it } from "vitest";
import { appleAppSiteAssociation, assetLinks } from "@/lib/app-links";

const SHA = Array.from({ length: 32 }, () => "AB").join(":");

describe("mobile app links", () => {
  it("serves nothing until the apps are configured", () => {
    expect(assetLinks({})).toBeNull();
    expect(assetLinks({ ANDROID_APP_CERT_SHA256: "not-a-fingerprint" })).toBeNull();
    expect(appleAppSiteAssociation({ APPLE_TEAM_ID: "short" })).toBeNull();
  });

  it("builds the Android statement from valid fingerprints", () => {
    const body = assetLinks({ ANDROID_APP_CERT_SHA256: `${SHA.toLowerCase()}, junk` });
    expect(body?.[0].target).toEqual({
      namespace: "android_app",
      package_name: "com.appinmobiliaria.app_inmobiliaria_mobile",
      sha256_cert_fingerprints: [SHA],
    });
  });

  it("builds the iOS association with the team-prefixed bundle id", () => {
    const body = appleAppSiteAssociation({ APPLE_TEAM_ID: "ABCDE12345" });
    expect(body?.applinks.details[0].appIDs).toEqual(["ABCDE12345.com.appinmobiliaria.appInmobiliariaMobile"]);
    expect(body?.applinks.details[0].components).toContainEqual({ "/": "/reset-password" });
  });
});
