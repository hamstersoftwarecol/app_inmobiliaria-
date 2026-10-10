import { Card, CardContent, CardDescription, CardHeader } from "@/components/ui/card";

export function AuthCard({ title, description, children }: { title: string; description?: string; children: React.ReactNode }) {
  return (
    <div className="mx-auto flex w-full max-w-md flex-col px-4 py-12">
      <Card>
        <CardHeader>
          <h1 className="text-2xl font-semibold text-brand-800">{title}</h1>
          {description && <CardDescription>{description}</CardDescription>}
        </CardHeader>
        <CardContent>{children}</CardContent>
      </Card>
    </div>
  );
}
