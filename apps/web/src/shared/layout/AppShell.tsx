import type { ReactNode } from 'react'
import { LogOut, Orbit } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { useCurrentUser, useLogout } from '@/features/auth/api'

export function AppShell({ children }: { children: ReactNode }) {
  const navigate = useNavigate()
  const user = useCurrentUser()
  const logout = useLogout()
  const leave = () => logout.mutate(undefined, { onSuccess: () => { void navigate('/login') } })

  return <div className="min-h-svh bg-background"><header className="sticky top-0 z-20 border-b bg-background/90 backdrop-blur"><div className="mx-auto flex min-h-16 max-w-7xl items-center justify-between px-4 sm:px-6"><div className="flex items-center gap-3"><span className="grid size-10 place-items-center rounded-full bg-primary text-primary-foreground"><Orbit className="size-5" aria-hidden="true" /></span><div><p className="font-heading text-lg font-semibold leading-none">QuinielaLab</p><p className="mt-1 font-mono text-[10px] tracking-[0.18em] text-muted-foreground uppercase">Observatorio técnico</p></div></div><div className="flex items-center gap-2 sm:gap-4"><div className="hidden text-right sm:block"><p className="text-sm font-medium">{user.data?.data.name}</p><p className="text-xs text-muted-foreground">Propietario</p></div><Button variant="outline" size="icon" className="min-h-11 min-w-11" onClick={leave} disabled={logout.isPending} aria-label="Cerrar sesión"><LogOut aria-hidden="true" /></Button></div></div><nav className="mx-auto flex max-w-7xl gap-2 px-4 pb-3 sm:px-6"><Button variant="ghost" size="sm" onClick={() => void navigate('/')}>Inicio</Button><Button variant="ghost" size="sm" onClick={() => void navigate('/sorteos')}>Sorteos</Button></nav></header>{children}</div>
}
