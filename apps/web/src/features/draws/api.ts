import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiRequest } from '@/shared/api/http-client'

export type LotterySync = { id: number; external_id: number; name: string; status: string; today_draw: { p1: string; p2: string; p3: string } | null }
export type SyncStatus = { data: { automatic_sync_enabled: boolean; provider: string; local_date: string; status_refresh_seconds: number; open_errors: number; open_quarantines: number; lotteries: LotterySync[] } }

export const useSyncStatus = () => useQuery({ queryKey: ['sync-status'], queryFn: () => apiRequest<SyncStatus>('/api/v1/sync-status'), refetchInterval: (query) => (query.state.data?.data.status_refresh_seconds ?? 30) * 1_000 })

export const useRecentOperations = () => useQuery({ queryKey: ['draw-operations'], queryFn: async () => Promise.all([apiRequest<{ data: { uuid: string; status: string }[] }>('/api/v1/sync-runs?per_page=5'), apiRequest<{ data: { id: number; message: string }[] }>('/api/v1/sync-errors?per_page=5'), apiRequest<{ data: { id: number; error_code: string }[] }>('/api/v1/draw-quarantines?per_page=5')]) })

export function useManualSync() {
  const queryClient = useQueryClient()
  return useMutation({ mutationFn: (ids?: number[]) => apiRequest('/api/v1/sync-runs', { method: 'POST', body: JSON.stringify(ids ? { lottery_external_ids: ids } : {}) }), onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['sync-status'] }) })
}
