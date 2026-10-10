"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError } from "@/lib/api-client";
import { authService } from "@/services/auth-service";
import type { User } from "@/types/api";

export const authKeys = {
  me: ["auth", "me"] as const,
  sessions: ["auth", "sessions"] as const,
};

/**
 * Current user from the API session. `null` means "signed out"; the query
 * never retries 401s so guests aren't spammed with requests.
 */
export function useCurrentUser() {
  return useQuery<User | null>({
    queryKey: authKeys.me,
    queryFn: async () => {
      try {
        return await authService.me();
      } catch (error) {
        if (error instanceof ApiError && (error.status === 401 || error.status === 419)) return null;
        throw error;
      }
    },
    staleTime: 60_000,
    retry: (count, error) => !(error instanceof ApiError && error.status < 500) && count < 2,
  });
}

/**
 * Signs out, then performs a full navigation so every piece of in-memory
 * client state (query cache, stores) belonging to the session is discarded.
 */
export function useLogout(redirectTo = "/") {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: authService.logout,
    onSettled: () => {
      queryClient.cancelQueries();
      window.location.assign(redirectTo);
    },
  });
}

export function hasPermission(user: User | null | undefined, permission: string): boolean {
  return Boolean(user?.permissions?.includes(permission));
}

export function canAccessAdmin(user: User | null | undefined): boolean {
  return Boolean(user?.is_staff) || hasPermission(user, "users.view_any");
}
