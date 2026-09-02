import { Navigate, Outlet } from 'react-router-dom'
import { Skeleton } from '@/components/ui/skeleton'
import { useCurrentUser } from './api'

export function ProtectedRoute() {
  const user = useCurrentUser()
  if (user.isPending) {
    return <div className="grid min-h-svh place-items-center" aria-label="Verificando sesión"><Skeleton className="h-12 w-48" /></div>
  }
  if (user.isError) return <Navigate to="/login" replace />
  return <Outlet />
}
