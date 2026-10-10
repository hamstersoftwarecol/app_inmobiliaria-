import type { MetadataRoute } from "next";
import { env } from "@/lib/env";

export default function sitemap(): MetadataRoute.Sitemap {
  return [
    { url: `${env.siteUrl}/`, changeFrequency: "weekly", priority: 1 },
    { url: `${env.siteUrl}/agents`, changeFrequency: "daily", priority: 0.8 },
  ];
}
