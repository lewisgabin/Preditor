import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiRequest } from '@/shared/api/http-client'

export type MethodVersion = { version: number; is_active: boolean; valid_from: string; valid_to: string | null; target: { id: number; name: string }; source: { id: number; name: string; relation: 'same_day' | 'previous_day' }; rule: string }
export type Method = { id: number; code: string; name: string; description: string; category: 'primary' | 'alternative'; is_active: boolean; versions: MethodVersion[] }
export type Signal = { id: number; method: { code: string; name: string; version: number }; target: { lottery_id: number; external_id: number; lottery_name: string; date: string }; recommended_number: string; status: 'generated' | 'expired' | 'cancelled'; sources: { draw_id: number; lottery_name: string; date: string; p1: string; p2: string; p3: string }[]; explanation: string; generated_at: string; observed_result: { draw_id: number; p1: string; p2: string; p3: string; matching_positions: ('P1' | 'P2' | 'P3')[] } | null }
export type GenerationSummary = { generated: number; already_exists: number; missing_source: number; timing_blocked: number; error: number; signals: Signal[] }
export const localToday = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Santo_Domingo', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date())
export const useMethods = () => useQuery({ queryKey: ['methods'], queryFn: () => apiRequest<{ data: Method[] }>('/api/v1/methods') })
export const useSignals = (date: string, preparing = false) => useQuery({ queryKey: ['signals', date], queryFn: () => apiRequest<{ data: Signal[] }>(`/api/v1/signals?date=${date}`), enabled: Boolean(date), refetchInterval: preparing ? 2000 : false })
export function useGenerateSignals() {
  const client = useQueryClient()
  return useMutation({ mutationFn: (date: string) => apiRequest<{ data: GenerationSummary }>('/api/v1/signals/generate', { method: 'POST', body: JSON.stringify({ date }) }), onSettled: () => client.invalidateQueries({ queryKey: ['signals'] }) })
}
