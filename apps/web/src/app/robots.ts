import type { MetadataRoute } from "next";
import { env } from "@/lib/env";

export default function robots(): MetadataRoute.Robots {
  return {
    rules: { userAgent: "*", allow: "/", disallow: ["/dashboard", "/admin", "/login", "/register", "/reset-password", "/verify-email"] },
    sitemap: `${env.siteUrl}/sitemap.xml`,
  };
}
