import { useState, type FormEvent } from 'react'
import { Navigate, useNavigate } from 'react-router-dom'
import { LockKeyhole, Orbit } from 'lucide-react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { useCurrentUser, useLogin } from '@/features/auth/api'

export function LoginPage() {
  const navigate = useNavigate()
  const currentUser = useCurrentUser()
  const login = useLogin()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')

  if (currentUser.isSuccess) return <Navigate to="/" replace />

  const submit = (event: FormEvent) => {
    event.preventDefault()
    login.mutate({ email, password }, { onSuccess: () => { void navigate('/') } })
  }

  return (
    <main className="login-canvas min-h-svh px-4 py-8 sm:px-8">
      <div className="mx-auto grid min-h-[calc(100svh-4rem)] max-w-6xl items-center gap-10 lg:grid-cols-[1.15fr_0.85fr]">
        <section className="max-w-2xl py-6" aria-labelledby="login-heading">
          <div className="mb-10 flex items-center gap-3 text-sm font-semibold tracking-[0.18em] uppercase">
            <span className="grid size-11 place-items-center rounded-full bg-primary text-primary-foreground"><Orbit aria-hidden="true" /></span>
            QuinielaLab
          </div>
          <p className="mb-4 font-mono text-xs tracking-[0.24em] text-primary uppercase">Estación privada · Fase 0</p>
          <h1 id="login-heading" className="font-heading text-5xl leading-[0.95] font-semibold tracking-[-0.045em] sm:text-7xl">La base técnica,<br /><em className="font-normal text-primary">bajo control.</em></h1>
          <p className="mt-7 max-w-xl text-base leading-7 text-muted-foreground sm:text-lg">Acceso reservado al propietario. Aquí observas la salud de la aplicación antes de activar cualquier módulo operativo.</p>
        </section>

        <Card className="border-0 bg-card/95 py-7 shadow-[0_28px_80px_-28px_oklch(0.2_0.05_250/0.35)] ring-1 ring-border backdrop-blur">
          <CardHeader className="px-6 sm:px-8">
            <span className="mb-3 grid size-12 place-items-center rounded-xl bg-accent text-accent-foreground"><LockKeyhole aria-hidden="true" /></span>
            <CardTitle className="font-heading text-3xl">Entrar al observatorio</CardTitle>
            <CardDescription>Usa las credenciales creadas por el administrador.</CardDescription>
          </CardHeader>
          <CardContent className="px-6 sm:px-8">
            <form onSubmit={submit} noValidate>
              <FieldGroup>
                <Field data-invalid={login.isError || undefined}><FieldLabel htmlFor="email">Correo electrónico</FieldLabel><Input id="email" name="email" type="email" autoComplete="username" required value={email} onChange={(event) => setEmail(event.target.value)} className="min-h-11" /></Field>
                <Field data-invalid={login.isError || undefined}><FieldLabel htmlFor="password">Contraseña</FieldLabel><Input id="password" name="password" type="password" autoComplete="current-password" required value={password} onChange={(event) => setPassword(event.target.value)} className="min-h-11" />{login.isError && <FieldError>Correo o contraseña incorrectos.</FieldError>}</Field>
                {login.isError && <Alert variant="destructive"><AlertTitle>No pudimos iniciar sesión</AlertTitle><AlertDescription>Verifica tus datos e inténtalo de nuevo.</AlertDescription></Alert>}
                <Button type="submit" size="lg" className="min-h-11 w-full" disabled={login.isPending || !email || !password}>{login.isPending ? 'Verificando…' : 'Entrar'}</Button>
              </FieldGroup>
            </form>
          </CardContent>
        </Card>
      </div>
    </main>
  )
}
