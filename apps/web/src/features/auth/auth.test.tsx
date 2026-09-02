import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { AppRouter } from '@/app/router'
import { acquireCsrfCookie } from '@/shared/api/http-client'

const jsonResponse = (body: unknown, status = 200) => new Response(JSON.stringify(body), {
  status,
  headers: { 'Content-Type': 'application/json' },
})

function renderRoute(path: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  return render(<QueryClientProvider client={client}><MemoryRouter initialEntries={[path]}><AppRouter /></MemoryRouter></QueryClientProvider>)
}

it('acquires a CSRF cookie with credentials included', async () => {
  const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 204 }))
  await acquireCsrfCookie()
  expect(fetchMock).toHaveBeenCalledWith('/sanctum/csrf-cookie', expect.objectContaining({ credentials: 'include' }))
})

it('redirects unauthenticated access to the Spanish login', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({ message: 'Unauthenticated.' }, 401))
  renderRoute('/')
  expect(await screen.findByRole('heading', { name: /la base técnica/i })).toBeInTheDocument()
})

it('shows Spanish feedback and a disabled loading submit for invalid login', async () => {
  let loginStarted = false
  vi.spyOn(globalThis, 'fetch').mockImplementation(async (input) => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.href : input.url
    if (url.includes('/auth/me')) return jsonResponse({ message: 'Unauthenticated.' }, 401)
    if (url.includes('/sanctum/')) return new Response(null, { status: 204 })
    loginStarted = true
    await Promise.resolve()
    return jsonResponse({ message: 'Credenciales incorrectas.' }, 422)
  })
  renderRoute('/login')
  const user = userEvent.setup()
  await user.type(await screen.findByLabelText('Correo electrónico'), 'owner@example.test')
  await user.type(screen.getByLabelText('Contraseña'), 'incorrecta')
  await user.click(screen.getByRole('button', { name: 'Entrar' }))
  await waitFor(() => expect(loginStarted).toBe(true))
  expect(await screen.findByText('No pudimos iniciar sesión')).toBeInTheDocument()
  expect(screen.getByText('Correo o contraseña incorrectos.')).toBeInTheDocument()
})

it('renders a Spanish not found page', () => {
  renderRoute('/ruta-inexistente')
  expect(screen.getByRole('heading', { name: 'Esta ruta no existe.' })).toBeInTheDocument()
})
