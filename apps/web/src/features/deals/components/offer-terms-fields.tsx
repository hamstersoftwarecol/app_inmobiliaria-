"use client";

import { Controller, type Control, type FieldErrors, type UseFormRegister } from "react-hook-form";
import { CheckboxGroup } from "@/components/ui/checkbox-group";
import { FormField } from "@/components/ui/form-field";
import { Input, Select } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { useTranslation } from "@/hooks/use-translation";
import { CONTINGENCIES, FINANCING, todayIso, type OfferTermsValues } from "@/validations/offer";

export const TERM_FIELDS = ["amount", "earnest_money", "financing", "down_payment_percent", "closing_date", "contingencies", "terms", "expires_at"] as const;

interface Props {
  prefix: string;
  register: UseFormRegister<OfferTermsValues>;
  control: Control<OfferTermsValues>;
  errors: FieldErrors<OfferTermsValues>;
  financing: string;
}

/** Price, financing, dates and contingencies — shared by the offer and counter-offer forms. */
export function OfferTermsFields({ prefix, register, control, errors, financing }: Props) {
  const { t } = useTranslation();
  const err = (key: keyof OfferTermsValues) => {
    const message = errors[key]?.message;
    if (!message) return undefined;
    const translated = t(`validation.${message}`);
    return translated === `validation.${message}` ? message : translated;
  };

  return (
    <>
      <div className="grid gap-4 sm:grid-cols-2">
        <FormField id={`${prefix}-amount`} label={t("offers.amount")} error={err("amount")}>
          {(a) => <Input {...a} {...register("amount")} inputMode="decimal" />}
        </FormField>
        <FormField id={`${prefix}-earnest`} label={t("offers.earnestMoney")} error={err("earnest_money")}>
          {(a) => <Input {...a} {...register("earnest_money")} inputMode="decimal" />}
        </FormField>
        <FormField id={`${prefix}-financing`} label={t("offers.financing")} error={err("financing")}>
          {(a) => (
            <Select {...a} {...register("financing")}>
              {FINANCING.map((f) => (
                <option key={f} value={f}>
                  {t(`offers.financingTypes.${f}`)}
                </option>
              ))}
            </Select>
          )}
        </FormField>
        {financing !== "cash" && (
          <FormField id={`${prefix}-down`} label={t("offers.downPayment")} error={err("down_payment_percent")}>
            {(a) => <Input {...a} {...register("down_payment_percent")} inputMode="decimal" />}
          </FormField>
        )}
        <FormField id={`${prefix}-closing`} label={t("offers.closingDate")} error={err("closing_date")}>
          {(a) => <Input {...a} {...register("closing_date")} type="date" min={todayIso()} />}
        </FormField>
        <FormField id={`${prefix}-expires`} label={t("offers.expiresAt")} error={err("expires_at")}>
          {(a) => <Input {...a} {...register("expires_at")} type="datetime-local" />}
        </FormField>
      </div>
      <Controller
        control={control}
        name="contingencies"
        render={({ field }) => (
          <CheckboxGroup
            name={`${prefix}-contingencies`}
            legend={t("offers.contingencies")}
            options={CONTINGENCIES}
            value={field.value}
            onChange={field.onChange}
            label={(o) => t(`offers.contingencyTypes.${o}`)}
            error={err("contingencies")}
          />
        )}
      />
      <FormField id={`${prefix}-terms`} label={t("offers.terms")} error={err("terms")}>
        {(a) => <Textarea {...a} {...register("terms")} rows={3} maxLength={5000} />}
      </FormField>
    </>
  );
}
