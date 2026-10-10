import { assetLinks } from "@/lib/app-links";

export const dynamic = "force-dynamic";

export function GET() {
  const body = assetLinks();
  return body ? Response.json(body) : new Response("Not found", { status: 404 });
}
