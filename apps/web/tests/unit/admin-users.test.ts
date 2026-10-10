import { describe, expect, it } from "vitest";
import { formatAuditTime } from "@/features/admin/components/audit-log";
import { assignableRoles } from "@/features/admin/components/user-editor";
import type { Role, User } from "@/types/api";
import { adminUserCreateSchema } from "@/validations/admin-user";

const role = (name: Role["name"], level: number): Role => ({ id: level, name, display_name: name, description: null, level, is_staff: level >= 60, is_system: true });
const ROLES = [role("super_admin", 100), role("broker", 80), role("office_manager", 60), role("agent", 40), role("seller", 20), role("buyer", 10)];
const me = (...names: Role["name"][]) => ({ roles: names.map((name) => ({ name, display_name: name })) }) as User;

describe("admin users", () => {
  it("offers only roles below the current user's highest level", () => {
    expect(assignableRoles(ROLES, me("broker")).map((r) => r.name)).toEqual(["office_manager", "agent", "seller", "buyer"]);
    expect(assignableRoles(ROLES, me("office_manager", "agent")).map((r) => r.name)).toEqual(["agent", "seller", "buyer"]);
    expect(assignableRoles(ROLES, me("super_admin"))).toHaveLength(6);
    expect(assignableRoles(ROLES, null)).toEqual([]);
  });

  it("validates new users with the password policy and at least one role", () => {
    const base = { first_name: "Ana", last_name: "Ruiz", email: "ana@example.com", phone: "", locale: "en", timezone: "America/New_York", password: "Very-Secure-123!", roles: ["agent"] };
    expect(adminUserCreateSchema.safeParse(base).success).toBe(true);
    expect(adminUserCreateSchema.safeParse({ ...base, password: "short" }).error?.issues[0]?.message).toBe("passwordMin");
    expect(adminUserCreateSchema.safeParse({ ...base, roles: [] }).error?.issues[0]?.message).toBe("required");
    expect(adminUserCreateSchema.safeParse({ ...base, phone: "abc" }).error?.issues[0]?.message).toBe("phone");
  });

  it("shows audit times in the agency time zone", () => {
    // 03:30 UTC on March 11 is still March 10 in New Jersey.
    expect(formatAuditTime("2027-03-11T03:30:00Z", "en")).toMatch(/Mar 10, 2027/);
  });
});
