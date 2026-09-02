import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { acquireCsrfCookie, apiRequest } from '@/shared/api/http-client'
import type { ResourceResponse, User } from '@/shared/api/contracts'

export const authQueryKey = ['auth', 'me'] as const

export const useCurrentUser = () => useQuery({
  queryKey: authQueryKey,
  queryFn: () => apiRequest<ResourceResponse<User>>('/api/v1/auth/me'),
  retry: false,
  staleTime: 30_000,
})

export const useLogin = () => {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (credentials: { email: string; password: string }) => {
      await acquireCsrfCookie()
      return apiRequest<ResourceResponse<User>>('/api/v1/auth/login', {
        method: 'POST',
        body: JSON.stringify(credentials),
      })
    },
    onSuccess: (user) => queryClient.setQueryData(authQueryKey, user),
  })
}

export const useLogout = () => {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: () => apiRequest<void>('/api/v1/auth/logout', { method: 'POST' }),
    onSuccess: () => queryClient.removeQueries({ queryKey: authQueryKey }),
  })
}
