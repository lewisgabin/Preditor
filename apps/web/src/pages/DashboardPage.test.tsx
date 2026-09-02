import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { DashboardPage } from './DashboardPage'
import { authQueryKey } from '@/features/auth/api'

const owner = { data: { id: 1, name: 'Lewis', email: 'owner@example.test' } }
const checks = {
  application: { status: 'ok' },
  mysql: { status: 'ok' },
  redis: { status: 'degraded' },
  scheduler: { status: 'ok' },
} as const

it('shows the owner, service degradation and disabled future modules', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(JSON.stringify({ status: 'degraded', checks, version: 'test', git_sha: 'abc' }), { status: 503, headers: { 'Content-Type': 'application/json' } }))
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  client.setQueryData(authQueryKey, owner)
  render(<QueryClientProvider client={client}><MemoryRouter><DashboardPage /></MemoryRouter></QueryClientProvider>)

  expect(await screen.findByText('Degradado')).toBeInTheDocument()
  expect(screen.getByText('Lewis')).toBeInTheDocument()
  for (const module of ['Señales', 'Métodos', 'Capital', 'Backtesting', 'Palés']) {
    expect(screen.getByRole('button', { name: new RegExp(module) })).toBeDisabled()
  }
})
