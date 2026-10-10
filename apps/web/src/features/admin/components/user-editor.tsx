"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { useForm, type FieldValues, type Path, type UseFormRegister, type FieldErrors } from "react-hook-form";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { FormField } from "@/components/ui/form-field";
import { Input, Select } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { hasPermission, useCurrentUser } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";
import { ApiError } from "@/lib/api-client";
import { applyApiErrors } from "@/lib/form-errors";
import { adminService } from "@/services/admin-service";
import type { Role, RoleName, User } from "@/types/api";
import {
  adminUserCreateSchema,
  adminUserProfileSchema,
  US_TIMEZONES,
  type AdminUserCreateValues,
  type AdminUserProfileValues,
} from "@/validations/admin-user";
import { AdminError } from "./admin-error";

const PROFILE_FIELDS = ["first_name", "last_name", "email", "phone", "locale", "timezone"] as const;

/** Roles the current user may grant: strictly below their highest level (super admins: all). */
export function assignableRoles(roles: Role[], me: User | null | undefined): Role[] {
  const mine = new Set(me?.roles?.map((r) => r.name));
  if (mine.has("super_admin")) return roles;
  const top = Math.max(0, ...roles.filter((r) => mine.has(r.name)).map((r) => r.level));
  return roles.filter((r) => r.level < top);
}

function ProfileFields<T extends FieldValues>({ register, errors }: { register: UseFormRegister<T>; errors: FieldErrors<T> }) {
  const { t, tv } = useTranslation();
  const err = (k: string) => {
    const m = (errors as Record<string, { message?: string } | undefined>)[k]?.message;
    return m ? (tv(m) === `validation.${m}` ? m : tv(m)) : undefined;
  };
  const reg = (k: string) => register(k as Path<T>);
  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <FormField id="u-first" label={t("adminUsers.firstName")} error={err("first_name")}>
        {(a) => <Input {...a} {...reg("first_name")} autoComplete="off" />}
      </FormField>
      <FormField id="u-last" label={t("adminUsers.lastName")} error={err("last_name")}>
        {(a) => <Input {...a} {...reg("last_name")} autoComplete="off" />}
      </FormField>
      <FormField id="u-email" label={t("adminUsers.email")} error={err("email")}>
        {(a) => <Input {...a} {...reg("email")} type="email" autoComplete="off" />}
      </FormField>
      <FormField id="u-phone" label={t("adminUsers.phone")} error={err("phone")}>
        {(a) => <Input {...a} {...reg("phone")} type="tel" />}
      </FormField>
      <FormField id="u-locale" label={t("adminUsers.locale")} error={err("locale")}>
        {(a) => (
          <Select {...a} {...reg("locale")}>
            <option value="en">English</option>
            <option value="es">Español</option>
          </Select>
        )}
      </FormField>
      <FormField id="u-tz" label={t("adminUsers.timezone")} error={err("timezone")}>
        {(a) => (
          <Select {...a} {...reg("timezone")}>
            {US_TIMEZONES.map((z) => (
              <option key={z} value={z}>{z}</option>
            ))}
          </Select>
        )}
      </FormField>
    </div>
  );
}

function RolePicker({ roles, value, onChange, error }: { roles: Role[]; value: string[]; onChange: (v: string[]) => void; error?: string }) {
  const { t } = useTranslation();
  return (
    <fieldset className="space-y-2" aria-describedby="roles-hint">
      <legend className="text-sm font-medium">{t("adminUsers.roles")}</legend>
      <p id="roles-hint" className="text-xs text-muted-foreground">{t("adminUsers.rolesHint")}</p>
      <div className="flex flex-wrap gap-3">
        {roles.map((role) => (
          <label key={role.name} className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              className="size-4 accent-brand-700"
              checked={value.includes(role.name)}
              onChange={(e) => onChange(e.target.checked ? [...value, role.name] : value.filter((r) => r !== role.name))}
            />
            {role.display_name}
          </label>
        ))}
      </div>
      {error && <p role="alert" className="text-xs font-medium text-danger">{error}</p>}
    </fieldset>
  );
}

export function NewUserForm() {
  const { t, tv } = useTranslation();
  const router = useRouter();
  const queryClient = useQueryClient();
  const { data: me } = useCurrentUser();
  const roles = useQuery({ queryKey: ["admin", "roles"], queryFn: adminService.roles, retry: false });
  const [error, setError] = useState<string | null>(null);
  const form = useForm<AdminUserCreateValues>({
    resolver: zodResolver(adminUserCreateSchema),
    defaultValues: { first_name: "", last_name: "", email: "", phone: "", locale: "en", timezone: "America/New_York", password: "", roles: [] },
  });
  const selected = form.watch("roles");

  if (!hasPermission(me, "users.create")) return <Alert variant="danger">{t("admin.forbidden")}</Alert>;
  if (roles.isError) return <AdminError error={roles.error} />;

  const submit = form.handleSubmit(async (values) => {
    setError(null);
    try {
      const user = await adminService.createUser({ ...values, phone: values.phone || null, roles: values.roles as RoleName[] });
      await queryClient.invalidateQueries({ queryKey: ["admin", "users"] });
      router.push(`/admin/users/${user.id}?created=1`);
    } catch (e) {
      const message = applyApiErrors(e, form.setError, t("common.error"), [...PROFILE_FIELDS, "password", "roles"]);
      if (message) setError(message);
    }
  });

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t("adminUsers.createTitle")}</CardTitle>
      </CardHeader>
      <CardContent>
        <form method="post" onSubmit={submit} noValidate className="space-y-5" aria-label={t("adminUsers.createTitle")}>
          {error && <Alert variant="danger">{error}</Alert>}
          <ProfileFields register={form.register} errors={form.formState.errors} />
          <FormField
            id="u-password"
            label={t("adminUsers.password")}
            hint={t("adminUsers.passwordHint")}
            error={form.formState.errors.password?.message ? tv(form.formState.errors.password.message) : undefined}
          >
            {(a) => <Input {...a} {...form.register("password")} type="password" autoComplete="new-password" />}
          </FormField>
          {roles.isLoading ? (
            <Skeleton className="h-16 w-full" />
          ) : (
            <RolePicker
              roles={assignableRoles(roles.data ?? [], me)}
              value={selected}
              onChange={(v) => form.setValue("roles", v, { shouldValidate: form.formState.isSubmitted })}
              error={form.formState.errors.roles?.message ? tv(form.formState.errors.roles.message) ?? form.formState.errors.roles.message : undefined}
            />
          )}
          <Button type="submit" loading={form.formState.isSubmitting}>
            {t("adminUsers.create")}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}

const profileDefaults = (u: User): AdminUserProfileValues => ({
  first_name: u.first_name,
  last_name: u.last_name,
  email: u.email,
  phone: u.phone ?? "",
  locale: u.locale,
  timezone: u.timezone,
});

function ProfileCard({ user, onSaved }: { user: User; onSaved: (u: User) => void }) {
  const { t } = useTranslation();
  const [status, setStatus] = useState<{ ok: boolean; message: string } | null>(null);
  const form = useForm<AdminUserProfileValues>({ resolver: zodResolver(adminUserProfileSchema), defaultValues: profileDefaults(user) });
  useEffect(() => form.reset(profileDefaults(user)), [user, form]);

  const submit = form.handleSubmit(async (values) => {
    setStatus(null);
    try {
      onSaved(await adminService.updateUser(user.id, { ...values, phone: values.phone || null }));
      setStatus({ ok: true, message: t("adminUsers.saved") });
    } catch (e) {
      const message = applyApiErrors(e, form.setError, t("common.error"), PROFILE_FIELDS);
      if (message) setStatus({ ok: false, message });
    }
  });

  return (
    <form method="post" onSubmit={submit} noValidate className="space-y-4" aria-label={t("adminUsers.profile")}>
      {status && <Alert variant={status.ok ? "success" : "danger"}>{status.message}</Alert>}
      <ProfileFields register={form.register} errors={form.formState.errors} />
      <Button type="submit" loading={form.formState.isSubmitting}>
        {t("adminUsers.save")}
      </Button>
    </form>
  );
}

function RolesCard({ user, onSaved }: { user: User; onSaved: (u: User) => void }) {
  const { t } = useTranslation();
  const { data: me } = useCurrentUser();
  const roles = useQuery({ queryKey: ["admin", "roles"], queryFn: adminService.roles, retry: false });
  const [value, setValue] = useState<string[]>(user.roles?.map((r) => r.name) ?? []);
  const [status, setStatus] = useState<{ ok: boolean; message: string } | null>(null);
  const [pending, setPending] = useState(false);

  if (roles.isError) return <AdminError error={roles.error} />;
  if (roles.isLoading) return <Skeleton className="h-16 w-full" />;

  const save = async () => {
    if (!value.length) return setStatus({ ok: false, message: t("validation.required") });
    setPending(true);
    setStatus(null);
    try {
      onSaved(await adminService.syncRoles(user.id, value as RoleName[]));
      setStatus({ ok: true, message: t("adminUsers.rolesSaved") });
    } catch (e) {
      setStatus({ ok: false, message: e instanceof ApiError ? e.message : t("common.error") });
    } finally {
      setPending(false);
    }
  };

  return (
    <div className="space-y-4">
      {status && <Alert variant={status.ok ? "success" : "danger"}>{status.message}</Alert>}
      <RolePicker roles={assignableRoles(roles.data ?? [], me)} value={value} onChange={setValue} />
      <Button type="button" onClick={save} loading={pending}>
        {t("adminUsers.saveRoles")}
      </Button>
    </div>
  );
}

export function UserEditor({ id, created }: { id: number; created: boolean }) {
  const { t, locale } = useTranslation();
  const queryClient = useQueryClient();
  const { data: me } = useCurrentUser();
  const key = ["admin", "user", id] as const;
  const query = useQuery({ queryKey: key, queryFn: () => adminService.user(id), retry: false });
  const onSaved = (u: User) => {
    queryClient.setQueryData(key, u);
    void queryClient.invalidateQueries({ queryKey: ["admin", "users"] });
  };

  if (query.isError) return <AdminError error={query.error} />;
  if (query.isLoading || !query.data) return <Skeleton className="h-96 w-full" />;
  const user = query.data;
  const self = user.id === me?.id;

  return (
    <div className="space-y-6">
      <Link href="/admin/users" className="text-sm font-medium text-brand-600 hover:underline">
        ← {t("adminUsers.backToUsers")}
      </Link>
      {created && <Alert variant="success">{t("adminUsers.created")}</Alert>}
      <div className="flex flex-wrap items-center gap-2">
        <h2 className="text-2xl font-semibold text-brand-800">{user.full_name}</h2>
        <Badge className={user.status === "active" ? "bg-success-50 text-success" : "bg-danger-50 text-danger"}>
          {user.status === "active" ? t("admin.active") : t("admin.deactivated")}
        </Badge>
      </div>
      <dl className="grid gap-2 text-sm sm:grid-cols-3">
        <div>
          <dt className="text-muted-foreground">{t("adminUsers.lastLogin")}</dt>
          <dd>{user.last_login_at ? new Date(user.last_login_at).toLocaleString(locale) : t("adminUsers.never")}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground">{t("adminUsers.mfa")}</dt>
          <dd>{user.mfa_enabled ? t("adminUsers.enabled") : t("adminUsers.disabled")}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground">{t("adminUsers.emailVerified")}</dt>
          <dd>{user.email_verified ? t("adminUsers.enabled") : t("adminUsers.disabled")}</dd>
        </div>
      </dl>
      {hasPermission(me, "users.update") && !self && (
        <Card>
          <CardHeader>
            <CardTitle>{t("adminUsers.profile")}</CardTitle>
          </CardHeader>
          <CardContent>
            <ProfileCard user={user} onSaved={onSaved} />
          </CardContent>
        </Card>
      )}
      {hasPermission(me, "users.assign_roles") && !self && (
        <Card>
          <CardHeader>
            <CardTitle>{t("adminUsers.roles")}</CardTitle>
          </CardHeader>
          <CardContent>
            <RolesCard key={user.roles?.map((r) => r.name).join()} user={user} onSaved={onSaved} />
          </CardContent>
        </Card>
      )}
      {hasPermission(me, "audit_logs.view") && (
        <Link href={`/admin/audit?user_id=${user.id}`} className="text-sm font-medium text-brand-600 hover:underline">
          {t("adminUsers.activity")} →
        </Link>
      )}
    </div>
  );
}
