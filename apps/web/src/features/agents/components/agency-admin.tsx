"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { AdminError } from "@/features/admin/components/admin-error";
import { hasPermission, useCurrentUser } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";
import { applyApiErrors } from "@/lib/form-errors";
import { adminAgencyService } from "@/services/agent-service";
import type { Agency } from "@/types/api";
import { agencySchema, officeSchema, type AgencyValues, type OfficeValues } from "@/validations/agency";

function AgencyForm({ agency, canManage, onSaved }: { agency: Agency | null; canManage: boolean; onSaved: (a: Agency) => void }) {
  const { t, tv } = useTranslation();
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const defaults = (a: Agency | null): AgencyValues => ({
    name: a?.name ?? "",
    legal_name: a?.legal_name ?? "",
    license_number: a?.license_number ?? "",
    email: a?.email ?? "",
    phone: a?.phone ?? "",
    website: a?.website ?? "",
  });
  const form = useForm<AgencyValues>({ resolver: zodResolver(agencySchema), defaultValues: defaults(agency) });
  useEffect(() => form.reset(defaults(agency)), [agency, form]);
  const { errors, isSubmitting } = form.formState;
  const err = (m?: string) => tv(m) ?? m;

  const submit = form.handleSubmit(async (values) => {
    setMessage(null);
    const payload = Object.fromEntries(Object.entries(values).map(([k, v]) => [k, v === "" && k !== "name" ? null : v]));
    try {
      const saved = agency ? await adminAgencyService.updateAgency(agency.id, payload) : await adminAgencyService.createAgency(payload);
      onSaved(saved);
      setMessage({ ok: true, text: t("agency.saved") });
    } catch (error) {
      const text = applyApiErrors(error, form.setError, t("common.error"), ["name", "legal_name", "license_number", "email", "phone", "website"]);
      if (text) setMessage({ ok: false, text });
    }
  });

  return (
    <form onSubmit={submit} noValidate className="space-y-4">
      {message && <Alert variant={message.ok ? "success" : "danger"}>{message.text}</Alert>}
      <fieldset disabled={!canManage} className="grid gap-4 sm:grid-cols-2">
        <FormField id="agency-name" label={t("agency.name")} error={err(errors.name?.message)}>
          {(a11y) => <Input {...a11y} {...form.register("name")} />}
        </FormField>
        <FormField id="agency-legal" label={t("agency.legalName")} error={err(errors.legal_name?.message)}>
          {(a11y) => <Input {...a11y} {...form.register("legal_name")} />}
        </FormField>
        <FormField id="agency-license" label={t("agency.licenseNumber")} hint={t("agency.licenseNote")} error={err(errors.license_number?.message)}>
          {(a11y) => <Input {...a11y} {...form.register("license_number")} />}
        </FormField>
        <FormField id="agency-website" label={t("agency.website")} error={err(errors.website?.message)}>
          {(a11y) => <Input {...a11y} type="url" placeholder="https://" {...form.register("website")} />}
        </FormField>
        <FormField id="agency-email" label={t("agency.email")} error={err(errors.email?.message)}>
          {(a11y) => <Input {...a11y} type="email" {...form.register("email")} />}
        </FormField>
        <FormField id="agency-phone" label={t("agency.phone")} error={err(errors.phone?.message)}>
          {(a11y) => <Input {...a11y} type="tel" {...form.register("phone")} />}
        </FormField>
      </fieldset>
      {canManage && (
        <Button type="submit" loading={isSubmitting}>
          {agency ? t("agency.save") : t("agency.create")}
        </Button>
      )}
    </form>
  );
}

const emptyOffice: OfficeValues = {
  name: "",
  street_address: "",
  unit: "",
  city: "",
  county: "",
  postal_code: "",
  phone: "",
  email: "",
  latitude: "",
  longitude: "",
  is_headquarters: false,
};

function OfficeForm({ agencyId, onSaved }: { agencyId: number; onSaved: () => void }) {
  const { t, tv } = useTranslation();
  const [message, setMessage] = useState<string | null>(null);
  const form = useForm<OfficeValues>({ resolver: zodResolver(officeSchema), defaultValues: emptyOffice });
  const { errors, isSubmitting } = form.formState;
  const err = (m?: string) => tv(m) ?? m;

  const submit = form.handleSubmit(async (v) => {
    setMessage(null);
    try {
      await adminAgencyService.createOffice({
        agency_id: agencyId,
        name: v.name,
        street_address: v.street_address,
        unit: v.unit || null,
        city: v.city,
        county: v.county || null,
        postal_code: v.postal_code,
        phone: v.phone || null,
        email: v.email || null,
        latitude: v.latitude ? Number(v.latitude) : null,
        longitude: v.longitude ? Number(v.longitude) : null,
        is_headquarters: v.is_headquarters,
      });
      form.reset(emptyOffice);
      onSaved();
    } catch (error) {
      setMessage(applyApiErrors(error, form.setError, t("common.error"), ["name", "street_address", "unit", "city", "county", "postal_code", "phone", "email", "latitude", "longitude"]));
    }
  });

  const field = (name: keyof OfficeValues, label: string, type = "text") => (
    <FormField id={`office-${name}`} label={label} error={err(errors[name]?.message)}>
      {(a11y) => <Input {...a11y} type={type} {...form.register(name)} />}
    </FormField>
  );

  return (
    <form onSubmit={submit} noValidate className="space-y-4" aria-label={t("agency.addOffice")}>
      <h3 className="font-semibold text-brand-800">{t("agency.addOffice")}</h3>
      {message && <Alert variant="danger">{message}</Alert>}
      <div className="grid gap-4 sm:grid-cols-2">
        {field("name", t("agency.officeName"))}
        {field("street_address", t("agency.street"))}
        {field("unit", t("agency.unit"))}
        {field("city", t("agency.cityLabel"))}
        {field("county", t("agency.county"))}
        {field("postal_code", t("agency.zip"))}
        {field("phone", t("agency.phone"), "tel")}
        {field("email", t("agency.email"), "email")}
        {field("latitude", t("agency.latitude"))}
        {field("longitude", t("agency.longitude"))}
      </div>
      <label className="flex items-center gap-2 text-sm">
        <input type="checkbox" className="size-4 accent-brand-700" {...form.register("is_headquarters")} />
        {t("agency.headquarters")}
      </label>
      <Button type="submit" loading={isSubmitting}>
        {t("agency.saveOffice")}
      </Button>
    </form>
  );
}

export function AgencyAdmin() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const { data: me } = useCurrentUser();
  const canManage = hasPermission(me, "agency.manage");
  const agencies = useQuery({ queryKey: ["admin", "agencies"], queryFn: adminAgencyService.agencies, retry: false });
  const agencyId = agencies.data?.[0]?.id;
  const agency = useQuery({
    queryKey: ["admin", "agency", agencyId],
    queryFn: () => adminAgencyService.agency(agencyId!),
    enabled: Boolean(agencyId),
  });
  const refresh = () => queryClient.invalidateQueries({ queryKey: ["admin"] });
  const remove = useMutation({ mutationFn: adminAgencyService.deleteOffice, onSuccess: refresh });

  if (agencies.isError) return <AdminError error={agencies.error} />;
  if (agencies.isLoading || (agencyId && agency.isLoading)) return <Skeleton className="h-96 w-full" />;
  const current = agency.data ?? null;

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>{t("agency.details")}</CardTitle>
          {!current && <CardDescription>{t("agency.none")}</CardDescription>}
        </CardHeader>
        <CardContent>
          <AgencyForm agency={current} canManage={canManage} onSaved={refresh} />
        </CardContent>
      </Card>

      {current && (
        <Card>
          <CardHeader>
            <CardTitle>{t("agency.offices")}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-6">
            {remove.isError && <AdminError error={remove.error} />}
            {!current.offices?.length ? (
              <EmptyState title={t("agency.noOffices")} />
            ) : (
              <ul className="divide-y divide-border rounded-md border border-border">
                {current.offices.map((o) => (
                  <li key={o.id} className="flex flex-wrap items-start justify-between gap-2 p-4 text-sm">
                    <div>
                      <p className="flex items-center gap-2 font-medium">
                        {o.name}
                        {o.is_headquarters && <Badge>{t("agency.headquarters")}</Badge>}
                        {!o.is_active && <Badge className="bg-muted text-muted-foreground">{t("agency.inactive")}</Badge>}
                      </p>
                      <p className="text-muted-foreground">
                        {o.street_address}
                        {o.unit ? `, ${o.unit}` : ""}, {o.city}, {o.state} {o.postal_code}
                      </p>
                      <p className="text-muted-foreground">{t("agency.agentsCount", { count: o.agents_count ?? 0 })}</p>
                    </div>
                    {canManage && (
                      <Button size="sm" variant="outline" loading={remove.isPending && remove.variables === o.id} onClick={() => remove.mutate(o.id)}>
                        {t("agency.delete")}
                        <span className="sr-only">: {o.name}</span>
                      </Button>
                    )}
                  </li>
                ))}
              </ul>
            )}
            {canManage && <OfficeForm agencyId={current.id} onSaved={refresh} />}
          </CardContent>
        </Card>
      )}
    </div>
  );
}
