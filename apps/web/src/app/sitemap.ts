import type { MetadataRoute } from "next";
import { env } from "@/lib/env";

export default function sitemap(): MetadataRoute.Sitemap {
  return [{ url: `${env.siteUrl}/`, changeFrequency: "weekly", priority: 1 }];
}
