import { AlertTriangle, CheckCircle2, Database, Radio, ServerCog } from 'lucide-react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useHealth } from './api'

const labels = { application: 'API', mysql: 'MySQL', redis: 'Redis', scheduler: 'Scheduler' }
const icons = { application: Radio, mysql: Database, redis: ServerCog, scheduler: CheckCircle2 }

export function HealthStatus() {
  const health = useHealth()
  if (health.isPending) return <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Consultando servicios">{Object.keys(labels).map((name) => <Skeleton key={name} className="h-32 rounded-xl" />)}</div>
  if (health.isError) return <Alert variant="destructive"><AlertTriangle aria-hidden="true" /><AlertTitle>La API no está disponible</AlertTitle><AlertDescription>No pudimos consultar los servicios. El panel volverá a intentarlo.</AlertDescription></Alert>

  return <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">{Object.entries(health.data.checks).map(([key, check]) => {
    const Icon = icons[key as keyof typeof icons]
    const ready = check.status === 'ok'
    return <Card key={key} size="sm" className="status-card"><CardHeader><div className="mb-7 flex items-center justify-between"><span className="grid size-10 place-items-center rounded-lg bg-accent"><Icon className="size-5" aria-hidden="true" /></span><Badge variant={ready ? 'secondary' : 'destructive'}>{ready ? 'Operativo' : 'Degradado'}</Badge></div><CardTitle className="text-lg">{labels[key as keyof typeof labels]}</CardTitle></CardHeader><CardContent><p className="text-xs text-muted-foreground">Monitoreo automático · 30 s</p></CardContent></Card>
  })}</div>
}
