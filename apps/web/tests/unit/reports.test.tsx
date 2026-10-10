import { fireEvent, render, screen } from "@testing-library/react";
import { afterAll, beforeAll, describe, expect, it } from "vitest";
import { ColumnChart, columnPath, niceMax } from "@/features/reports/components/column-chart";
import { canExport, presetRange } from "@/features/reports/components/agency-dashboard";
import type { User } from "@/types/api";

describe("chart helpers", () => {
  it("rounds axis maxima to clean numbers", () => {
    expect([0, 1, 3, 7, 12, 180, 999].map(niceMax)).toEqual([1, 1, 5, 10, 20, 200, 1000]);
  });

  it("draws columns with a rounded data-end and a square baseline", () => {
    expect(columnPath(10, 20, 12, 40)).toBe("M10,60 V24 Q10,20 14,20 H18 Q22,20 22,24 V60 Z");
    expect(columnPath(0, 0, 10, 0)).toBe("");
    expect(columnPath(0, 0, 2, 10)).toContain("Q0,0 1,0"); // radius shrinks for thin columns
  });

  it("computes preset ranges in local calendar days", () => {
    expect(presetRange(7, new Date(2027, 2, 14, 23, 0))).toEqual({ from: "2027-03-08", to: "2027-03-14" });
  });

  it("offers only the exports the user may take", () => {
    const user = { permissions: ["reports.view", "leads.view_any"] } as unknown as User;
    expect(canExport(user, "leads")).toBe(true);
    expect(canExport(user, "transactions")).toBe(false);
    expect(canExport({ permissions: ["leads.view_any"] } as unknown as User, "leads")).toBe(false);
  });
});

describe("ColumnChart", () => {
  const original = Object.getOwnPropertyDescriptor(HTMLElement.prototype, "clientWidth");
  beforeAll(() => Object.defineProperty(HTMLElement.prototype, "clientWidth", { configurable: true, get: () => 400 }));
  afterAll(() => original && Object.defineProperty(HTMLElement.prototype, "clientWidth", original));

  it("labels the peak and reads values with the keyboard", () => {
    render(
      <ColumnChart
        title="New leads"
        data={[
          { key: "a", label: "Jun 14", value: 2 },
          { key: "b", label: "Jun 15", value: 5 },
          { key: "c", label: "Jun 16", value: 0 },
        ]}
        describe={(d) => `${d.value} lead(s)`}
      />,
    );
    const chart = screen.getByRole("img", { name: "New leads: 7" });
    expect(chart.querySelectorAll("path")).toHaveLength(2); // a zero day draws no column
    expect(screen.getAllByText("5").length).toBeGreaterThan(0); // the peak is labelled

    fireEvent.focus(chart);
    expect(screen.getByRole("status")).toHaveTextContent("0 lead(s)Jun 16");
    fireEvent.keyDown(chart, { key: "ArrowLeft" });
    expect(screen.getByRole("status")).toHaveTextContent("5 lead(s)Jun 15");
    fireEvent.blur(chart);
    expect(screen.queryByRole("status")).toBeNull();
  });
});
