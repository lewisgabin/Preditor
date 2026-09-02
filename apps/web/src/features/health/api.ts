import { useQuery } from '@tanstack/react-query'
import { apiRequest } from '@/shared/api/http-client'
import type { HealthResponse } from '@/shared/api/contracts'

export const useHealth = () => useQuery({
  queryKey: ['health'],
  queryFn: () => apiRequest<HealthResponse>('/api/health', { acceptedStatuses: [503] }),
  refetchInterval: 30_000,
  retry: 1,
})
