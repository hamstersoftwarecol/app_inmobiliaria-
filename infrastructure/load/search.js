// k6 load test for the public search path (the busiest, cache-free endpoints).
//
//   k6 run -e BASE_URL=https://api.staging.example.com infrastructure/load/search.js
//   docker run --rm -i --network host -e BASE_URL=http://localhost:8000 grafana/k6:0.54.0 run - < infrastructure/load/search.js
//
// Run against staging (never production without a change window). Each virtual user carries a
// distinct X-App-Inmobiliaria-Client-IP only when SSR_KEY is set (simulating server-rendered traffic);
// otherwise all traffic comes from one IP and the per-IP search limiter (60/min) is what you test.
import http from "k6/http";
import { check, sleep } from "k6";

const BASE = (__ENV.BASE_URL || "http://localhost:8000").replace(/\/$/, "") + "/api/v1";
const SSR_KEY = __ENV.SSR_KEY || "";
const CITIES = (__ENV.CITIES || "Hoboken,Jersey City,Newark,Montclair,Princeton").split(",");

export const options = {
  scenarios: {
    browse: {
      executor: "ramping-vus",
      startVUs: 0,
      stages: [
        { duration: __ENV.RAMP || "30s", target: Number(__ENV.VUS || 20) },
        { duration: __ENV.HOLD || "1m", target: Number(__ENV.VUS || 20) },
        { duration: "15s", target: 0 },
      ],
    },
  },
  thresholds: {
    // Targets for the release candidate (see docs/operations/runbooks.md#load-testing).
    "http_req_duration{endpoint:search}": ["p(95)<500"],
    "http_req_duration{endpoint:detail}": ["p(95)<400"],
    "http_req_duration{endpoint:locations}": ["p(95)<300"],
    "checks": ["rate>0.99"],
  },
};

function headers(vu) {
  const h = { Accept: "application/json" };
  if (SSR_KEY) {
    h["X-App-Inmobiliaria-SSR-Key"] = SSR_KEY;
    h["X-App-Inmobiliaria-Client-IP"] = `10.0.${Math.floor(vu / 250)}.${vu % 250}`;
  }
  return { headers: h };
}

export default function () {
  const params = headers(__VU);
  const city = CITIES[Math.floor(Math.random() * CITIES.length)];
  const sorts = ["newest", "price_asc", "price_desc"];

  const loc = http.get(`${BASE}/locations?q=${encodeURIComponent(city.slice(0, 3))}`, { ...params, tags: { endpoint: "locations" } });
  check(loc, { "locations 200": (r) => r.status === 200 });

  const search = http.get(
    `${BASE}/properties?city=${encodeURIComponent(city)}&min_beds=${1 + Math.floor(Math.random() * 3)}&sort=${sorts[__ITER % sorts.length]}`,
    { ...params, tags: { endpoint: "search" } },
  );
  check(search, { "search 200": (r) => r.status === 200 });

  const items = search.status === 200 ? search.json("data") : [];
  if (items && items.length) {
    const slug = items[Math.floor(Math.random() * items.length)].slug;
    const detail = http.get(`${BASE}/properties/${slug}`, { ...params, tags: { endpoint: "detail" } });
    check(detail, { "detail 200": (r) => r.status === 200 });
  }
  sleep(1 + Math.random() * 2);
}
