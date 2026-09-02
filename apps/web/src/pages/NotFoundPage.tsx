import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'

export function NotFoundPage() {
  return <main className="grid min-h-svh place-items-center px-6 text-center"><div><p className="font-mono text-sm text-primary">ERROR 404</p><h1 className="mt-3 font-heading text-5xl">Esta ruta no existe.</h1><p className="mt-4 text-muted-foreground">Vuelve al panel seguro de QuinielaLab.</p><Button className="mt-7 min-h-11" asChild><Link to="/">Ir al panel</Link></Button></div></main>
}
