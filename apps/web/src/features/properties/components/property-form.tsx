"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useQuery } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { Controller, useForm } from "react-hook-form";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { CheckboxGroup } from "@/components/ui/checkbox-group";
import { FormField } from "@/components/ui/form-field";
import { Input, Select } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { hasPermission, useCurrentUser } from "@/hooks/use-auth";
import { useTranslation } from "@/hooks/use-translation";
import { applyApiErrors } from "@/lib/form-errors";
import { adminAgencyService } from "@/services/agent-service";
import { managePropertyService, type PropertyInput } from "@/services/property-service";
import type { ManagedProperty } from "@/types/api";
import { AMENITIES, propertySchema, toPropertyPayload, type PropertyValues } from "@/validations/property";

const STATUSES = ["active", "coming_soon", "active_under_contract", "pending", "closed", "withdrawn", "expired", "canceled"] as const;
const FIELDS = [
  "title", "listing_type", "status", "property_type_id", "list_price", "street_address", "unit", "city", "county",
  "postal_code", "bedrooms", "bathrooms", "living_area_sqft", "year_built", "description", "description_es", "amenities",
] as const;

const str = (v: number | string | null | undefined) => (v === null || v === undefined ? "" : String(v));

export function propertyDefaults(p?: ManagedProperty): PropertyValues {
  return {
    title: p?.title ?? "",
    listing_type: p?.listing_type ?? "sale",
    status: (STATUSES as readonly string[]).includes(p?.status ?? "") ? (p!.status as PropertyValues["status"]) : "active",
    property_type_id: str(p?.property_type_id),
    list_price: str(p?.list_price),
    street_address: p?.street_address ?? "",
    unit: p?.unit ?? "",
    city: p?.city ?? "",
    county: p?.county ?? "",
    postal_code: p?.postal_code ?? "",
    address_display: p?.address_display ?? true,
    bedrooms: str(p?.bedrooms),
    bathrooms: str(p?.bathrooms),
    living_area_sqft: str(p?.living_area_sqft),
    year_built: str(p?.year_built),
    description: p?.description ?? "",
    description_es: p?.description_es ?? "",
    amenities: p?.amenities ?? [],
  };
}

/** Listing agents a broker may assign (needs `agents.view_any`); agents always list under themselves. */
function useAssignableAgents(enabled: boolean) {
  return useQuery({
    queryKey: ["admin", "agents", "assignable"],
    queryFn: () => adminAgencyService.agents({ status: "active", page: 1 }),
    enabled,
    retry: false,
  });
}

interface Props {
  property?: ManagedProperty;
  onSaved: (property: ManagedProperty) => void;
}

export function PropertyForm({ property, onSaved }: Props) {
  const { t, locale } = useTranslation();
  const { data: me } = useCurrentUser();
  const [status, setStatus] = useState<{ ok: boolean; message: string } | null>(null);
  const canAssign = hasPermission(me, "properties.update_any") && hasPermission(me, "agents.view_any");
  const [agentId, setAgentId] = useState(str(property?.listing_agent?.id));
  const agents = useAssignableAgents(canAssign);
  const types = useQuery({ queryKey: ["property-types"], queryFn: managePropertyService.types, staleTime: 3_600_000 });

  const form = useForm<PropertyValues>({ resolver: zodResolver(propertySchema), defaultValues: propertyDefaults(property) });
  const { register, control, formState: { errors } } = form;
  const err = (key: keyof PropertyValues) => {
    const message = errors[key]?.message;
    return message ? (t(`validation.${message}`) === `validation.${message}` ? message : t(`validation.${message}`)) : undefined;
  };

  useEffect(() => {
    form.reset(propertyDefaults(property));
    setAgentId(str(property?.listing_agent?.id));
  }, [property, form]);

  const submit = form.handleSubmit(async (values) => {
    setStatus(null);
    const payload: PropertyInput = toPropertyPayload(values);
    if (canAssign) payload.listing_agent_id = agentId ? Number(agentId) : null;
    try {
      const saved = property ? await managePropertyService.update(property.id, payload) : await managePropertyService.create(payload);
      setStatus({ ok: true, message: t("manageListings.saved") });
      onSaved(saved);
    } catch (error) {
      const message = applyApiErrors(error, form.setError, t("common.error"), FIELDS);
      if (message) setStatus({ ok: false, message });
    }
  });

  return (
    <form onSubmit={submit} noValidate className="space-y-5" aria-label={property ? t("manageListings.editTitle") : t("manageListings.createTitle")}>
      {status && <Alert variant={status.ok ? "success" : "danger"}>{status.message}</Alert>}

      <FormField id="p-title" label={t("manageListings.titleLabel")} error={err("title")}>
        {(a) => <Input {...a} {...register("title")} maxLength={200} />}
      </FormField>

      <div className="grid gap-4 sm:grid-cols-3">
        <FormField id="p-listing-type" label={t("manageListings.listingType")} error={err("listing_type")}>
          {(a) => (
            <Select {...a} {...register("listing_type")}>
              <option value="sale">{t("properties.forSale")}</option>
              <option value="rent">{t("properties.forRent")}</option>
            </Select>
          )}
        </FormField>
        <FormField id="p-status" label={t("manageListings.status")} error={err("status")}>
          {(a) => (
            <Select {...a} {...register("status")}>
              {STATUSES.map((s) => (
                <option key={s} value={s}>
                  {t(`listingStatus.${s}`)}
                </option>
              ))}
            </Select>
          )}
        </FormField>
        <FormField id="p-type" label={t("manageListings.propertyType")} error={err("property_type_id")}>
          {(a) => (
            <Select {...a} {...register("property_type_id")}>
              <option value="">{t("manageListings.selectType")}</option>
              {types.data?.map((type) => (
                <option key={type.id} value={type.id}>
                  {locale === "es" ? type.name_es : type.name_en}
                </option>
              ))}
            </Select>
          )}
        </FormField>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <FormField id="p-price" label={t("manageListings.price")} error={err("list_price")}>
          {(a) => <Input {...a} {...register("list_price")} inputMode="decimal" />}
        </FormField>
        {canAssign && (
          <FormField id="p-agent" label={t("manageListings.agent")}>
            {(a) => (
              <Select {...a} value={agentId} onChange={(e) => setAgentId(e.target.value)} disabled={agents.isLoading}>
                <option value="">{t("manageListings.unassigned")}</option>
                {property?.listing_agent && !agents.data?.data.some((ag) => ag.id === property.listing_agent!.id) && (
                  <option value={property.listing_agent.id}>{property.listing_agent.full_name}</option>
                )}
                {agents.data?.data.map((ag) => (
                  <option key={ag.id} value={ag.id}>
                    {ag.user?.full_name ?? ag.slug}
                  </option>
                ))}
              </Select>
            )}
          </FormField>
        )}
      </div>

      <div className="grid gap-4 sm:grid-cols-6">
        <div className="sm:col-span-4">
          <FormField id="p-street" label={t("manageListings.street")} error={err("street_address")}>
            {(a) => <Input {...a} {...register("street_address")} autoComplete="street-address" />}
          </FormField>
        </div>
        <div className="sm:col-span-2">
          <FormField id="p-unit" label={t("manageListings.unit")} error={err("unit")}>
            {(a) => <Input {...a} {...register("unit")} />}
          </FormField>
        </div>
        <div className="sm:col-span-2">
          <FormField id="p-city" label={t("manageListings.city")} error={err("city")}>
            {(a) => <Input {...a} {...register("city")} />}
          </FormField>
        </div>
        <div className="sm:col-span-2">
          <FormField id="p-county" label={t("manageListings.county")} error={err("county")}>
            {(a) => <Input {...a} {...register("county")} />}
          </FormField>
        </div>
        <div className="sm:col-span-2">
          <FormField id="p-zip" label={t("manageListings.zip")} error={err("postal_code")}>
            {(a) => <Input {...a} {...register("postal_code")} inputMode="numeric" autoComplete="postal-code" />}
          </FormField>
        </div>
      </div>
      <label className="flex items-center gap-2 text-sm">
        <input type="checkbox" {...register("address_display")} className="size-4 accent-brand-700" />
        {t("manageListings.showAddress")}
      </label>

      <div className="grid gap-4 sm:grid-cols-4">
        <FormField id="p-beds" label={t("manageListings.bedrooms")} error={err("bedrooms")}>
          {(a) => <Input {...a} {...register("bedrooms")} inputMode="numeric" />}
        </FormField>
        <FormField id="p-baths" label={t("manageListings.bathrooms")} error={err("bathrooms")}>
          {(a) => <Input {...a} {...register("bathrooms")} inputMode="decimal" />}
        </FormField>
        <FormField id="p-area" label={t("manageListings.livingArea")} error={err("living_area_sqft")}>
          {(a) => <Input {...a} {...register("living_area_sqft")} inputMode="numeric" />}
        </FormField>
        <FormField id="p-year" label={t("manageListings.yearBuilt")} error={err("year_built")}>
          {(a) => <Input {...a} {...register("year_built")} inputMode="numeric" />}
        </FormField>
      </div>

      <FormField id="p-desc" label={t("manageListings.descriptionEn")} error={err("description")}>
        {(a) => <Textarea {...a} {...register("description")} rows={5} />}
      </FormField>
      <FormField id="p-desc-es" label={t("manageListings.descriptionEs")} error={err("description_es")}>
        {(a) => <Textarea {...a} {...register("description_es")} rows={5} lang="es" />}
      </FormField>

      <Controller
        control={control}
        name="amenities"
        render={({ field }) => (
          <CheckboxGroup
            name="amenities"
            legend={t("manageListings.amenities")}
            options={AMENITIES}
            value={field.value}
            onChange={field.onChange}
            label={(o) => t(`amenities.${o}`)}
            error={err("amenities")}
          />
        )}
      />

      <Button type="submit" loading={form.formState.isSubmitting}>
        {t("manageListings.save")}
      </Button>
    </form>
  );
}
