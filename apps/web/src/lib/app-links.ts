/**
 * Domain-verification files that let the mobile apps open this website's links (Android App
 * Links, iOS Universal Links). Server-only configuration; nothing is served until it is set:
 *  - ANDROID_APP_CERT_SHA256: release signing certificate fingerprint(s), comma-separated.
 *  - APPLE_TEAM_ID: Apple Developer team id.
 */
export const ANDROID_PACKAGE = "com.appinmobiliaria.app_inmobiliaria_mobile";
export const IOS_BUNDLE_ID = "com.appinmobiliaria.appInmobiliariaMobile";

/** Website paths the apps handle (keep in sync with the Android intent filter and the app router). */
export const APP_LINK_PATHS = ["/reset-password", "/verify-email", "/unsubscribe", "/properties", "/properties/*", "/dashboard", "/dashboard/*"];

export function assetLinks(env: Record<string, string | undefined> = process.env) {
  const fingerprints = (env.ANDROID_APP_CERT_SHA256 ?? "")
    .split(",")
    .map((f) => f.trim().toUpperCase())
    .filter((f) => /^([0-9A-F]{2}:){31}[0-9A-F]{2}$/.test(f));
  if (!fingerprints.length) return null;
  return [
    {
      relation: ["delegate_permission/common.handle_all_urls"],
      target: { namespace: "android_app", package_name: ANDROID_PACKAGE, sha256_cert_fingerprints: fingerprints },
    },
  ];
}

export function appleAppSiteAssociation(env: Record<string, string | undefined> = process.env) {
  const team = (env.APPLE_TEAM_ID ?? "").trim();
  if (!/^[A-Z0-9]{10}$/.test(team)) return null;
  return {
    applinks: {
      details: [{ appIDs: [`${team}.${IOS_BUNDLE_ID}`], components: APP_LINK_PATHS.map((p) => ({ "/": p })) }],
    },
  };
}
