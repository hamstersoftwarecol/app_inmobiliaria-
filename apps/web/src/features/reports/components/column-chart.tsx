"use client";

import { useEffect, useRef, useState } from "react";

export interface ColumnDatum {
  key: string;
  label: string;
  value: number;
}

/** Rounds up to a clean axis maximum (1, 2, 5 × 10^n). */
export function niceMax(value: number): number {
  if (value <= 0) return 1;
  const exp = 10 ** Math.floor(Math.log10(value));
  const f = value / exp;
  return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 5 ? 5 : 10) * exp;
}

/** Column with a 4px rounded data-end and a square baseline (radius shrinks for thin columns). */
export function columnPath(x: number, y: number, w: number, h: number): string {
  if (h <= 0) return "";
  const r = Math.min(4, w / 2, h);
  return `M${x},${y + h} V${y + r} Q${x},${y} ${x + r},${y} H${x + w - r} Q${x + w},${y} ${x + w},${y + r} V${y + h} Z`;
}

function useWidth<T extends HTMLElement>() {
  const ref = useRef<T>(null);
  const [width, setWidth] = useState(0);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    setWidth(el.clientWidth);
    if (typeof ResizeObserver === "undefined") return;
    const ro = new ResizeObserver(([entry]) => setWidth(entry.contentRect.width));
    ro.observe(el);
    return () => ro.disconnect();
  }, []);
  return [ref, width] as const;
}

const HEIGHT = 160;
const PAD = { top: 18, right: 8, bottom: 22, left: 32 };

interface Props {
  title: string;
  data: ColumnDatum[];
  formatValue?: (v: number) => string;
  /** Text for the tooltip row, e.g. "{value} leads". */
  describe: (d: ColumnDatum) => string;
}

/**
 * Single-series column chart: thin columns from one baseline, hairline grid, the peak labelled,
 * and a hover/keyboard tooltip (←/→ move between columns). Values also appear in the table view.
 */
export function ColumnChart({ title, data, formatValue = (v) => String(v), describe }: Props) {
  const [ref, width] = useWidth<HTMLDivElement>();
  const [active, setActive] = useState<number | null>(null);
  const innerW = Math.max(0, width - PAD.left - PAD.right);
  const innerH = HEIGHT - PAD.top - PAD.bottom;
  const max = niceMax(Math.max(0, ...data.map((d) => d.value)));
  const band = data.length ? innerW / data.length : 0;
  const barW = Math.max(1, Math.min(24, band - 2));
  const y = (v: number) => PAD.top + innerH - (v / max) * innerH;
  const peak = data.reduce((best, d, i) => (d.value > (data[best]?.value ?? -1) ? i : best), 0);
  const total = data.reduce((s, d) => s + d.value, 0);

  const indexAt = (clientX: number, rect: DOMRect) => {
    const i = Math.floor((clientX - rect.left - PAD.left) / band);
    return i >= 0 && i < data.length ? i : null;
  };

  const current = active !== null ? data[active] : null;
  const tipLeft = active !== null ? Math.min(Math.max(PAD.left + band * (active + 0.5), 60), Math.max(60, width - 60)) : 0;

  return (
    <figure className="relative" ref={ref}>
      <figcaption className="mb-1 flex items-baseline justify-between gap-2 text-sm">
        <span className="font-semibold text-brand-800">{title}</span>
        <span className="text-muted-foreground">{formatValue(total)}</span>
      </figcaption>
      {width > 0 && (
        <svg
          width={width}
          height={HEIGHT}
          role="img"
          aria-label={`${title}: ${formatValue(total)}`}
          tabIndex={0}
          className="block touch-pan-y outline-none focus-visible:ring-2 focus-visible:ring-ring"
          onPointerMove={(e) => setActive(indexAt(e.clientX, e.currentTarget.getBoundingClientRect()))}
          onPointerLeave={() => setActive(null)}
          onBlur={() => setActive(null)}
          onFocus={() => setActive((a) => a ?? data.length - 1)}
          onKeyDown={(e) => {
            if (e.key === "ArrowLeft") setActive((a) => Math.max(0, (a ?? data.length) - 1));
            else if (e.key === "ArrowRight") setActive((a) => Math.min(data.length - 1, (a ?? -1) + 1));
            else return;
            e.preventDefault();
          }}
        >
          {(Number.isInteger(max / 2) ? [0, max / 2, max] : [0, max]).map((t) => (
            <g key={t}>
              <line x1={PAD.left} x2={width - PAD.right} y1={y(t)} y2={y(t)} className="stroke-border" strokeWidth={1} />
              <text x={PAD.left - 6} y={y(t)} dy="0.32em" textAnchor="end" className="fill-muted-foreground text-[10px] tabular-nums">
                {formatValue(t)}
              </text>
            </g>
          ))}
          {data.map((d, i) => {
            if (d.value <= 0) return null; // nothing to draw for an empty day
            const x = PAD.left + band * i + (band - barW) / 2;
            return (
              <path
                key={d.key}
                d={columnPath(x, y(d.value), barW, PAD.top + innerH - y(d.value))}
                className="fill-chart"
                opacity={active === null || active === i ? 1 : 0.55}
              />
            );
          })}
          {data[peak] && data[peak].value > 0 && (
            <text x={PAD.left + band * (peak + 0.5)} y={y(data[peak].value) - 5} textAnchor="middle" className="fill-foreground text-[10px] font-semibold">
              {formatValue(data[peak].value)}
            </text>
          )}
          {data.length > 0 && (
            <>
              <text x={PAD.left} y={HEIGHT - 6} className="fill-muted-foreground text-[10px]">
                {data[0].label}
              </text>
              <text x={width - PAD.right} y={HEIGHT - 6} textAnchor="end" className="fill-muted-foreground text-[10px]">
                {data[data.length - 1].label}
              </text>
            </>
          )}
          {active !== null && (
            <line x1={PAD.left + band * (active + 0.5)} x2={PAD.left + band * (active + 0.5)} y1={PAD.top} y2={PAD.top + innerH} className="stroke-muted-foreground" strokeWidth={1} opacity={0.4} />
          )}
        </svg>
      )}
      {current && (
        <div role="status" className="pointer-events-none absolute top-6 z-10 -translate-x-1/2 rounded-md border border-border bg-white px-2 py-1 text-xs shadow-md" style={{ left: tipLeft }}>
          <p className="font-semibold text-foreground">{describe(current)}</p>
          <p className="text-muted-foreground">{current.label}</p>
        </div>
      )}
    </figure>
  );
}
