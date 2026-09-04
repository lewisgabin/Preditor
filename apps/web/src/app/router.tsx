import { MethodsPage } from '@/pages/MethodsPage'
import { SignalsPage } from '@/pages/SignalsPage'
import { Route, Routes } from 'react-router-dom'
import { ProtectedRoute } from '@/features/auth/ProtectedRoute'
import { DashboardPage } from '@/pages/DashboardPage'
import { DrawsPage } from '@/pages/DrawsPage'
import { LoginPage } from '@/pages/LoginPage'
import { NotFoundPage } from '@/pages/NotFoundPage'

export function AppRouter() {
  return <Routes><Route path="/login" element={<LoginPage />} /><Route element={<ProtectedRoute />}><Route index element={<DashboardPage />} /><Route path="/metodos" element={<MethodsPage />} /><Route path="/senales" element={<SignalsPage />} /><Route path="/sorteos" element={<DrawsPage />} /></Route><Route path="*" element={<NotFoundPage />} /></Routes>
}
