import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { vi } from 'vitest'
import { MethodsPage } from './MethodsPage'
import { SignalsPage } from './SignalsPage'

const json = (body: unknown, status = 200) => new Response(JSON.stringify(body), { status })
const signal = { id: 1, method: { code: 'P02', name: 'LoteDom P2 + 10', version: 1 }, target: { lottery_id: 2, external_id: 5, lottery_name: 'Leidsa', date: '2026-09-04' }, recommended_number: '07', status: 'generated', sources: [{ draw_id: 123, lottery_name: 'LoteDom', date: '2026-09-03', p1: '14', p2: '97', p3: '32' }], explanation: '97 + 10 mod 100 = 07', generated_at: '2026-09-04T12:00:00Z' }
function mount(page: 'methods' | 'signals') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(<QueryClientProvider client={client}><MemoryRouter>{page === 'methods' ? <MethodsPage /> : <SignalsPage />}</MemoryRouter></QueryClientProvider>)
  return client
}
it('shows twenty methods with source, destination, rule and version', async () => {
  const methods = ['P', 'A'].flatMap(prefix => Array.from({ length: 10 }, (_, index) => ({ id: `${prefix}${index}`, code: `${prefix}${String(index + 1).padStart(2, '0')}`, name: 'Método preconfigurado', category: prefix === 'P' ? 'primary' : 'alternative', is_active: true, versions: [{ version: 1, is_active: true, source: { name: 'LoteDom', relation: 'previous_day' }, target: { name: 'Leidsa' }, rule: 'P2 + 10 mod 100' }] })))
  vi.spyOn(globalThis, 'fetch').mockImplementation(input => Promise.resolve(json((typeof input === 'string' ? input : input instanceof URL ? input.href : input.url).includes('/methods') ? { data: methods } : { data: { name: 'Owner' } })))
  mount('methods')
  await screen.findByText('P01')
  for (const method of methods) expect(screen.getByText(method.code)).toBeInTheDocument()
  expect(screen.getAllByText('Versión 1')).toHaveLength(20)
  expect(screen.getAllByText('P2 + 10 mod 100')).toHaveLength(20)
  expect(screen.getAllByText('Leidsa')).toHaveLength(20)
  expect(screen.getAllByText('LoteDom · Día anterior')).toHaveLength(20)
})
it('shows loading and errors', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(json({ message: 'error' }, 500))
  mount('signals')
  expect(screen.getByText(/Preparando señales para/)).toBeInTheDocument()
  expect(await screen.findByText('No se pudieron cargar las señales.')).toBeInTheDocument()
})
it('automatically generates for the selected date and invalidates the list to show the backend calculation', async () => {
  let generated = false
  vi.spyOn(globalThis, 'fetch').mockImplementation((input, init) => {
    if ((typeof input === 'string' ? input : input instanceof URL ? input.href : input.url).includes('/auth/')) return Promise.resolve(json({ data: { name: 'Owner' } }))
    if (init?.method === 'POST') { generated = true; return Promise.resolve(json({ data: { generated: 1, already_exists: 0, missing_source: 19, timing_blocked: 0, error: 0, signals: [signal] } })) }
    return Promise.resolve(json({ data: generated ? [signal] : [] }))
  })
  const client = mount('signals')
  const invalidate = vi.spyOn(client, 'invalidateQueries')
  const input = screen.getByLabelText('Fecha de destino')
  fireEvent.change(input, { target: { value: '2026-09-04' } })
  expect(await screen.findByText('07')).toBeInTheDocument()
  expect(screen.getByText('P02')).toBeInTheDocument()
  expect(screen.getByText('Leidsa')).toBeInTheDocument()
  expect(screen.getByText('LoteDom 03/09/2026')).toBeInTheDocument()
  expect(screen.getByText('97 + 10 mod 100 = 07')).toBeInTheDocument()
  await waitFor(() => expect(invalidate).toHaveBeenCalledWith({ queryKey: ['signals'] }))
  expect(globalThis.fetch).toHaveBeenCalledWith('/api/v1/signals/generate', expect.objectContaining({ body: JSON.stringify({ date: '2026-09-04' }) }))
})
