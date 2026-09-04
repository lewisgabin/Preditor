import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { vi } from 'vitest'
import { DrawsPage } from './DrawsPage'

const { toast } = vi.hoisted(() => ({ toast: { success: vi.fn(), info: vi.fn(), error: vi.fn() } }))
vi.mock('sonner', () => ({ toast }))

const json = (body: unknown, status = 200) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
const lottery = (status: string) => ({ id: 1, external_id: 4, name: 'Lotería Nacional', status, today_draw: status === 'updated' ? { id: 1, date: '2026-09-04', p1: '04', p2: '00', p3: '97', received_at: '2026-09-04T12:00:00Z' } : null, latest_draw: { id: 2, date: '2026-09-03', p1: '09', p2: '01', p3: '00', received_at: '2026-09-03T12:00:00Z' }, latest_run: { uuid: '12345678-abcd', status: 'queued', created_at: '2026-09-04T12:00:00Z' }, schedule: null, open_error_count: 1, open_quarantine_count: 2 })
const statusPayload = (state = 'updated') => ({ data: { automatic_sync_enabled: false, provider: 'fake', local_date: '2026-09-04', status_refresh_seconds: 30, last_successful_sync_at: '2026-09-04T12:00:00Z', queued_runs: state === 'syncing' ? 1 : 0, running_runs: 0, open_errors: state === 'error' ? 1 : 0, open_quarantines: 2, lotteries: [lottery(state)] } })

function renderPage(state = 'updated') {
  vi.spyOn(globalThis, 'fetch').mockImplementation((input, init) => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.href : input.url
    if (url.includes('/auth/me')) return Promise.resolve(json({ data: { name: 'Owner' } }))
    if (url.includes('/sync-status')) return Promise.resolve(json(statusPayload(state)))
    if (url.includes('/sync-runs') && init?.method === 'POST') return Promise.resolve(json({ data: { sync_run_uuids: ['run-1'] } }, 202))
    if (url.includes('/sync-runs')) return Promise.resolve(json({ data: [] }))
    if (url.includes('/sync-errors')) return Promise.resolve(json({ data: [] }))
    if (url.includes('/draw-quarantines')) return Promise.resolve(json({ data: [] }))
    return Promise.resolve(json({ message: 'not found' }, 404))
  })
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  return render(<QueryClientProvider client={client}><MemoryRouter><DrawsPage /></MemoryRouter></QueryClientProvider>)
}

it('renders the operational summary, translated statuses, draws, and empty activity', async () => {
  renderPage()
  expect(await screen.findByRole('heading', { name: 'Sorteos' })).toBeInTheDocument()
  expect(screen.getByText('Actualizada')).toBeInTheDocument()
  expect(screen.getByText('04 · 00 · 97')).toBeInTheDocument()
  expect(screen.getByText('Horario no configurado')).toBeInTheDocument()
  expect(screen.getByText('Sin ejecuciones recientes.')).toBeInTheDocument()
  expect(screen.getByText('Sin errores abiertos.')).toBeInTheDocument()
  expect(screen.getByText('Sin cuarentenas abiertas.')).toBeInTheDocument()
})

it.each(['pending', 'syncing', 'error', 'never_checked'] as const)('renders the %s status in Spanish', async (state) => {
  renderPage(state)
  await screen.findByRole('heading', { name: 'Sorteos' })
  expect(screen.getAllByText({ pending: 'Pendiente', syncing: 'Sincronizando', error: 'Error', never_checked: 'Nunca consultada' }[state]).length).toBeGreaterThan(0)
})

it('queues one individual synchronization and blocks double clicks', async () => {
  let resolveSync: (response: Response) => void = () => undefined
  const syncResponse = new Promise<Response>((resolve) => { resolveSync = resolve })
  vi.spyOn(globalThis, 'fetch').mockImplementation((input, init) => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.href : input.url
    if (url.includes('/auth/me')) return Promise.resolve(json({ data: { name: 'Owner' } }))
    if (url.includes('/sync-status')) return Promise.resolve(json(statusPayload()))
    if (url.includes('/sync-runs') && init?.method === 'POST') return syncResponse
    if (url.includes('/sync-runs') || url.includes('/sync-errors') || url.includes('/draw-quarantines')) return Promise.resolve(json({ data: [] }))
    return Promise.resolve(json({ message: 'not found' }, 404))
  })
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(<QueryClientProvider client={client}><MemoryRouter><DrawsPage /></MemoryRouter></QueryClientProvider>)
  const user = userEvent.setup()
  const button = await screen.findByRole('button', { name: 'Sincronizar Lotería Nacional' })
  await user.click(button)
  expect(button).toBeDisabled()
  await user.click(button)
  resolveSync(json({ data: { sync_run_uuids: ['run-1'] } }, 202))
  await waitFor(() => expect(toast.success).toHaveBeenCalledWith('Sincronización encolada.'))
  const syncRequests = vi.mocked(globalThis.fetch).mock.calls.filter(([, init]) => init?.method === 'POST')
  expect(syncRequests).toHaveLength(1)
  expect(globalThis.fetch).toHaveBeenCalledWith('/api/v1/sync-runs', expect.objectContaining({ method: 'POST' }))
})

it('shows loading and endpoint failure states', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(json({ message: 'falló' }, 500))
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(<QueryClientProvider client={client}><MemoryRouter><DrawsPage /></MemoryRouter></QueryClientProvider>)
  expect(screen.getByText('Cargando sorteos…')).toBeInTheDocument()
  expect(await screen.findByText('No se pudo cargar el estado de sorteos.')).toBeInTheDocument()
})
