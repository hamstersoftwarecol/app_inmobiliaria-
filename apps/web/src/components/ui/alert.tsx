import { cva, type VariantProps } from "class-variance-authority";
import * as React from "react";
import { cn } from "@/lib/utils";

const alertVariants = cva("rounded-md border px-4 py-3 text-sm", {
  variants: {
    variant: {
      info: "border-brand-200 bg-brand-50 text-brand-800",
      success: "border-green-200 bg-success-50 text-success",
      warning: "border-yellow-200 bg-warning-50 text-warning",
      danger: "border-red-200 bg-danger-50 text-danger",
    },
  },
  defaultVariants: { variant: "info" },
});

export function Alert({
  className,
  variant,
  ...props
}: React.HTMLAttributes<HTMLDivElement> & VariantProps<typeof alertVariants>) {
  const role = variant === "danger" || variant === "warning" ? "alert" : "status";
  return <div role={role} className={cn(alertVariants({ variant }), className)} {...props} />;
}
