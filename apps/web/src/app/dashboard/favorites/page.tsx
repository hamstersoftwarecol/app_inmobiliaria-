import type { Metadata } from "next";
import { MyFavorites } from "@/features/properties/components/my-favorites";

export const metadata: Metadata = { title: "Favorite homes", robots: { index: false } };

export default function FavoritesPage() {
  return <MyFavorites />;
}
