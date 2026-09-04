import { Badge } from '@/components/ui/badge'
import { useMethods } from '@/features/strategies/api'
import { AppShell } from '@/shared/layout/AppShell'

export function MethodsPage() {
  const methods = useMethods()
  return <AppShell><main className="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6"><header><p className="font-mono text-xs uppercase tracking-widest text-muted-foreground">Motor de métodos</p><h1 className="mt-2 font-heading text-3xl font-semibold">Métodos</h1><p className="mt-2 text-muted-foreground">Reglas preconfiguradas, fuentes exactas y versiones que conservan cada cálculo.</p></header>
    {methods.isLoading && <p role="status">Cargando métodos…</p>}
    {methods.isError && <p role="alert">No se pudieron cargar los métodos.</p>}
    {methods.data && (['primary', 'alternative'] as const).map(category => <section key={category} className="space-y-4"><h2 className="font-heading text-xl font-semibold">{category === 'primary' ? 'Métodos principales' : 'Alternativas'}</h2><div className="grid gap-4 md:grid-cols-2">{methods.data.data.filter(method => method.category === category).map(method => {
      const version = method.versions[0]
      return <article key={method.id} className="min-w-0 rounded-xl border bg-card p-5"><div className="flex items-center justify-between gap-3"><h3 className="font-mono text-lg font-semibold">{method.code}</h3><Badge variant="outline">{method.is_active && version?.is_active ? 'Activo' : 'Inactivo'}</Badge></div><p className="mt-3 break-words text-sm text-muted-foreground">{method.name}</p>{version && <dl className="mt-4 space-y-3 text-sm"><div><dt className="text-muted-foreground">Destino</dt><dd className="font-semibold">{version.target.name}</dd></div><div><dt className="text-muted-foreground">Fuente</dt><dd>{version.source.name} · {version.source.relation === 'same_day' ? 'Mismo día' : 'Día anterior'}</dd></div><div className="rounded-lg bg-muted p-3"><dt className="sr-only">Regla</dt><dd className="break-words font-mono">{version.rule}</dd></div><div><dt className="sr-only">Versión</dt><dd>Versión {version.version}</dd></div></dl>}</article>
    })}</div></section>)}
  </main></AppShell>
}
