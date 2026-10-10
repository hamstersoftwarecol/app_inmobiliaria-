import type { Metadata } from "next";
import { AccountSettings } from "@/features/account/components/account-settings";

export const metadata: Metadata = { title: "Account settings", robots: { index: false } };

export default function SettingsPage() {
  return <AccountSettings />;
}
