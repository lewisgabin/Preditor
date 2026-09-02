export class ApiError extends Error {
  public readonly status: number

  constructor(message: string, status: number) {
    super(message)
    this.status = status
  }
}

const xsrfToken = () => {
  const cookie = document.cookie.split('; ').find((item) => item.startsWith('XSRF-TOKEN='))
  return cookie ? decodeURIComponent(cookie.split('=').slice(1).join('=')) : null
}

interface ApiRequestOptions extends RequestInit {
  acceptedStatuses?: number[]
}

export async function apiRequest<T>(path: string, options: ApiRequestOptions = {}): Promise<T> {
  const { acceptedStatuses = [], ...init } = options
  const headers = new Headers(init.headers)
  headers.set('Accept', 'application/json')
  if (init.body) headers.set('Content-Type', 'application/json')
  const token = xsrfToken()
  if (token) headers.set('X-XSRF-TOKEN', token)

  const response = await fetch(path, { ...init, credentials: 'include', headers })
  if (!response.ok && !acceptedStatuses.includes(response.status)) {
    const payload = (await response.json().catch(() => null)) as { message?: string } | null
    throw new ApiError(payload?.message ?? 'No pudimos completar la solicitud.', response.status)
  }
  if (response.status === 204) return undefined as T
  return response.json() as Promise<T>
}

export async function acquireCsrfCookie(): Promise<void> {
  await apiRequest<void>('/sanctum/csrf-cookie')
}
