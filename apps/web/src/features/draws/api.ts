import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiRequest } from '@/shared/api/http-client'

export type DrawNumbers = { id: number; date: string | null; p1: string; p2: string; p3: string; received_at: string | null }
export type LatestRun = { uuid: string; status: 'queued' | 'running' | 'succeeded' | 'partial' | 'failed'; created_at: string | null }
export type LotterySchedule = { draw_time_local: string | null; sales_close_time_local: string | null }
export type LotterySyncStatus = 'updated' | 'pending' | 'syncing' | 'error' | 'never_checked' | 'disabled'
export type LotterySync = { id: number; external_id: number; name: string; status: LotterySyncStatus; today_draw: DrawNumbers | null; latest_draw: DrawNumbers | null; latest_run: LatestRun | null; schedule: LotterySchedule | null; open_error_count: number; open_quarantine_count: number }
export type SyncStatusData = { automatic_sync_enabled: boolean; provider: string; local_date: string; status_refresh_seconds: number; last_successful_sync_at: string | null; queued_runs: number; running_runs: number; open_errors: number; open_quarantines: number; lotteries: LotterySync[] }
export type SyncStatus = { data: SyncStatusData }
export type SyncRun = { uuid: string; lottery_id: number | null; trigger: string; status: string; items_inserted: number; items_updated: number; items_unchanged: number; items_quarantined: number; started_at: string | null; finished_at: string | null }
export type SyncError = { id: number; message: string }
export type DrawQuarantine = { id: number; error_code: string }
export type ManualSyncResponse = { data: { sync_run_uuids: string[] } }

export const useSyncStatus = () => useQuery({ queryKey: ['sync-status'], queryFn: () => apiRequest<SyncStatus>('/api/v1/sync-status'), refetchInterval: (query) => {
  const data = query.state.data?.data
  return (data?.queued_runs ?? 0) + (data?.running_runs ?? 0) > 0 ? 3_000 : (data?.status_refresh_seconds ?? 30) * 1_000
} })

export const useRecentOperations = (hasActiveRuns: boolean) => useQuery({ queryKey: ['draw-operations'], queryFn: async () => Promise.all([apiRequest<{ data: SyncRun[] }>('/api/v1/sync-runs?per_page=5'), apiRequest<{ data: SyncError[] }>('/api/v1/sync-errors?per_page=5'), apiRequest<{ data: DrawQuarantine[] }>('/api/v1/draw-quarantines?per_page=5')]), refetchInterval: hasActiveRuns ? 3_000 : false })

export function useManualSync() {
  const queryClient = useQueryClient()
  return useMutation({ mutationFn: (ids?: number[]) => apiRequest<ManualSyncResponse>('/api/v1/sync-runs', { method: 'POST', body: JSON.stringify(ids ? { lottery_external_ids: ids } : {}) }), onSuccess: () => Promise.all([queryClient.invalidateQueries({ queryKey: ['sync-status'] }), queryClient.invalidateQueries({ queryKey: ['draw-operations'] })]) })
}
