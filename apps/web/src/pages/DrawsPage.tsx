import { RefreshCw } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { type LotterySync, type LotterySyncStatus, useManualSync, useRecentOperations, useSyncStatus } from '@/features/draws/api'
import { AppShell } from '@/shared/layout/AppShell'

const labels: Record<LotterySyncStatus, string> = {
  updated: 'Actualizada', pending: 'Pendiente', syncing: 'Sincronizando', error: 'Error', never_checked: 'Nunca consultada', disabled: 'Desactivada',
}

const formatDateTime = (value: string | null | undefined) => value ? new Intl.DateTimeFormat('es-DO', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'America/Santo_Domingo' }).format(new Date(value)) : 'Sin registro'
const numbers = (draw: LotterySync['today_draw']) => draw ? `${draw.p1} · ${draw.p2} · ${draw.p3}` : 'Pendiente'

function LotteryCard({ lottery, onSync, disabled }: { lottery: LotterySync; onSync: () => void; disabled: boolean }) {
  return <Card>
    <CardHeader className="pb-3"><CardTitle className="flex items-baseline justify-between gap-3 text-lg"><span>{lottery.name}</span><span className="text-sm font-normal text-muted-foreground">#{lottery.external_id}</span></CardTitle></CardHeader>
    <CardContent className="space-y-3 text-sm">
      <p><span className="text-muted-foreground">Estado: </span><strong>{labels[lottery.status]}</strong></p>
      <p><span className="text-muted-foreground">Resultado de hoy: </span><strong>{numbers(lottery.today_draw)}</strong></p>
      <p><span className="text-muted-foreground">Último resultado: </span>{numbers(lottery.latest_draw)} · {lottery.latest_draw?.date ?? 'Sin fecha'}</p>
      <p><span className="text-muted-foreground">Última ejecución: </span>{lottery.latest_run ? `${lottery.latest_run.status} · ${formatDateTime(lottery.latest_run.created_at)}` : 'Sin ejecuciones'}</p>
      <p><span className="text-muted-foreground">Horario: </span>{lottery.schedule?.draw_time_local ?? 'Horario no configurado'}</p>
      <p><span className="text-muted-foreground">Errores abiertos: </span>{lottery.open_error_count} · <span className="text-muted-foreground">Cuarentenas: </span>{lottery.open_quarantine_count}</p>
      <Button variant="outline" size="sm" onClick={onSync} disabled={disabled} aria-label={`Sincronizar ${lottery.name}`}>Sincronizar</Button>
    </CardContent>
  </Card>
}

export function DrawsPage() {
  const status = useSyncStatus()
  const data = status.data?.data
  const sync = useManualSync()
  const operations = useRecentOperations((data?.queued_runs ?? 0) + (data?.running_runs ?? 0) > 0)
  const request = (ids?: number[]) => sync.mutate(ids, {
    onSuccess: (response) => response.data.sync_run_uuids.length > 0
      ? toast.success('Sincronización encolada.')
      : toast.info('No se creó otra ejecución porque ya existe una sincronización activa o fue consultada recientemente.'),
    onError: () => toast.error('No se pudo encolar la sincronización.'),
  })

  if (status.isLoading) return <AppShell><main className="mx-auto max-w-7xl px-4 py-8">Cargando sorteos…</main></AppShell>
  if (status.isError || !data) return <AppShell><main className="mx-auto max-w-7xl px-4 py-8">No se pudo cargar el estado de sorteos.</main></AppShell>

  const count = (state: LotterySyncStatus) => data.lotteries.filter((lottery) => lottery.status === state).length
  const [runs, errors, quarantines] = operations.data ?? [{ data: [] }, { data: [] }, { data: [] }]

  return <AppShell><main className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <header className="mb-8 flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm text-muted-foreground">{data.local_date} · Provider: {data.provider}</p><h1 className="font-heading text-3xl font-semibold">Sorteos</h1><p className="text-sm text-muted-foreground">Automatización {data.automatic_sync_enabled ? 'activa' : 'desactivada'} · Última sincronización correcta: {formatDateTime(data.last_successful_sync_at)}</p></div><Button onClick={() => request()} disabled={sync.isPending}><RefreshCw className="mr-2 size-4" />Sincronizar todas</Button></header>
    <section aria-label="Resumen de sorteos" className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-6"><Card><CardHeader><CardTitle>Actualizadas</CardTitle></CardHeader><CardContent>{count('updated')}</CardContent></Card><Card><CardHeader><CardTitle>Pendientes</CardTitle></CardHeader><CardContent>{count('pending')}</CardContent></Card><Card><CardHeader><CardTitle>Sincronizando</CardTitle></CardHeader><CardContent>{count('syncing')}</CardContent></Card><Card><CardHeader><CardTitle>Errores abiertos</CardTitle></CardHeader><CardContent>{data.open_errors}</CardContent></Card><Card><CardHeader><CardTitle>Cuarentenas abiertas</CardTitle></CardHeader><CardContent>{data.open_quarantines}</CardContent></Card><Card><CardHeader><CardTitle>Runs activos</CardTitle></CardHeader><CardContent>{data.queued_runs} queued · {data.running_runs} running</CardContent></Card></section>
    <section aria-label="Loterías" className="grid gap-3 md:grid-cols-2">{data.lotteries.map((lottery) => <LotteryCard key={lottery.id} lottery={lottery} onSync={() => request([lottery.external_id])} disabled={sync.isPending} />)}</section>
    <section className="mt-8 grid gap-3 lg:grid-cols-3"><Card><CardHeader><CardTitle>Ejecuciones recientes</CardTitle></CardHeader><CardContent className="space-y-2">{runs.data.length === 0 ? <p>Sin ejecuciones recientes.</p> : runs.data.map((run) => <p key={run.uuid} className="text-sm">{run.uuid.slice(0, 8)} · {run.trigger} · {run.status} · +{run.items_inserted} / ↻{run.items_updated} / ={run.items_unchanged} / !{run.items_quarantined} · {formatDateTime(run.finished_at ?? run.started_at)}</p>)}</CardContent></Card><Card><CardHeader><CardTitle>Errores recientes</CardTitle></CardHeader><CardContent>{errors.data.length === 0 ? 'Sin errores abiertos.' : errors.data.map((error) => <p key={error.id} className="text-sm">{error.message}</p>)}</CardContent></Card><Card><CardHeader><CardTitle>Cuarentenas recientes</CardTitle></CardHeader><CardContent>{quarantines.data.length === 0 ? 'Sin cuarentenas abiertas.' : quarantines.data.map((item) => <p key={item.id} className="text-sm">{item.error_code}</p>)}</CardContent></Card></section>
  </main></AppShell>
}
