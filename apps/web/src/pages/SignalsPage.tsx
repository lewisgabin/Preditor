import { useEffect } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { localToday, useGenerateSignals, useSignals } from '@/features/strategies/api'
import { AppShell } from '@/shared/layout/AppShell'

const statusLabels = { generated: 'Generada', expired: 'Vencida', cancelled: 'Cancelada' }
const displayDate = (value: string) => value.split('-').reverse().join('/')

export function SignalsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const date = searchParams.get('date') ?? localToday()
  const generate = useGenerateSignals()
  const { mutate: prepare } = generate
  const validDate = /^\d{4}-\d{2}-\d{2}$/.test(date)
  const preparing = validDate && (generate.isPending || generate.variables !== date)
  const signals = useSignals(date, preparing)
  useEffect(() => {
    if (!validDate) return
    const timer = window.setTimeout(() => prepare(date), 400)
    return () => window.clearTimeout(timer)
  }, [date, validDate, prepare])
  return <AppShell><main className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6"><header className="flex flex-wrap items-end justify-between gap-5"><div><p className="font-mono text-xs uppercase tracking-widest text-muted-foreground">Cálculos trazables</p><h1 className="mt-2 font-heading text-3xl font-semibold">Señales</h1><p className="mt-2 text-muted-foreground">Elige una fecha: calculamos sus señales con el historial disponible y conservamos cada fuente y explicación.</p></div><div className="flex flex-wrap items-end gap-3"><div><label htmlFor="signal-date" className="mb-2 block text-sm font-medium">Fecha de destino</label><Input id="signal-date" type="date" value={date} onChange={event => { setSearchParams({ date: event.target.value }, { replace: true }); generate.reset() }} className="min-h-11 w-auto" /></div><Button className="min-h-11" disabled={!date || generate.isPending} onClick={() => generate.mutate(date)}>{generate.isPending ? 'Generando…' : 'Generar señales'}</Button></div></header>
    {generate.variables === date && generate.isError && <p role="alert">No se pudieron generar las señales. Inténtalo de nuevo.</p>}
    {!preparing && generate.variables === date && generate.data && <p role="status" className="rounded-lg border bg-muted p-4 text-sm">{generate.data.data.generated} generadas · {generate.data.data.already_exists} existentes · {generate.data.data.missing_source} sin fuente · {generate.data.data.timing_blocked} bloqueadas por horario · {generate.data.data.error} errores</p>}
    {(signals.isLoading || preparing) && <p role="status">Preparando señales para {displayDate(date)}…</p>}
    {signals.isError && <p role="alert">No se pudieron cargar las señales.</p>}
    {!preparing && !signals.isFetching && !generate.isError && signals.data?.data.length === 0 && <div className="rounded-xl border border-dashed p-10 text-center"><h2 className="font-heading text-xl">Sin señales para esta fecha</h2><p className="mt-2 text-muted-foreground">No se encontraron fuentes suficientes para generar señales en esta fecha. Se necesitan resultados confirmados y, para métodos del mismo día, horarios verificables.</p></div>}
    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">{signals.data?.data.map(signal => <article aria-label={`Señal ${signal.method.code}`} key={signal.id} className="min-w-0 rounded-xl border bg-card p-5"><div className="flex items-center justify-between gap-3"><h2 className="font-mono font-semibold">{signal.method.code}</h2><Badge variant="outline">{statusLabels[signal.status]}</Badge></div><p className="mt-3 font-heading text-xl font-semibold">{signal.target.lottery_name}</p><p className="text-sm text-muted-foreground">{displayDate(signal.target.date)} · Versión {signal.method.version}</p><p className="my-5 font-mono text-7xl font-semibold tabular-nums tracking-tight text-primary">{signal.recommended_number}</p><div className="border-t pt-4"><p className="text-xs font-medium uppercase tracking-widest text-muted-foreground">Fuente</p>{signal.sources.map(source => <p key={source.draw_id} className="mt-2 text-sm">{source.lottery_name} {displayDate(source.date)}</p>)}<p className="mt-4 break-words rounded-lg bg-muted p-3 font-mono text-sm">{signal.explanation}</p></div></article>)}</div>
  </main></AppShell>
}
