export interface User {
  id: number
  name: string
  email: string
}

export type ServiceState = 'ok' | 'degraded'

export interface HealthResponse {
  status: ServiceState
  checks: Record<'application' | 'mysql' | 'redis' | 'scheduler', { status: ServiceState }>
  version: string | null
  git_sha: string | null
}

export interface ResourceResponse<T> {
  data: T
}
