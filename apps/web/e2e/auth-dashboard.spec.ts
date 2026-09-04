import { expect, test, type APIRequestContext } from '@playwright/test'

const requiredEnvironmentVariable = (name: 'E2E_EMAIL' | 'E2E_PASSWORD'): string => {
  const value = process.env[name]

  if (!value) throw new Error(`${name} es obligatoria para ejecutar la prueba E2E`)

  return value
}

const assertAllowedCorsOrigin = async (request: APIRequestContext, apiUrl: string, origin: string) => {
  const response = await request.get(`${apiUrl}/api/health`, { headers: { Origin: origin } })

  expect(response.status()).toBe(200)
  expect(response.headers()['access-control-allow-origin']).toBe(origin)
  expect(response.headers()['access-control-allow-credentials']).toBe('true')
}

test('el propietario inicia sesión y consulta el dashboard', async ({ page, request }) => {
  const email = requiredEnvironmentVariable('E2E_EMAIL')
  const password = requiredEnvironmentVariable('E2E_PASSWORD')
  const apiUrl = process.env.E2E_API_URL ?? 'http://127.0.0.1:8080'
  const consoleErrors: string[] = []

  const anonymousProfileResponsePromise = page.waitForResponse((response) =>
    new URL(response.url()).pathname === '/api/v1/auth/me',
  )
  const loginPageResponse = await page.goto('/login')
  expect(loginPageResponse?.status()).toBe(200)
  expect((await anonymousProfileResponsePromise).status()).toBe(401)

  page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text())
  })

  const healthResponse = await page.request.get('/api/health')
  expect(healthResponse.status()).toBe(200)
  await expect(healthResponse.json()).resolves.toMatchObject({
    status: 'ok',
    checks: {
      application: { status: 'ok' },
      mysql: { status: 'ok' },
      redis: { status: 'ok' },
    },
  })

  await assertAllowedCorsOrigin(request, apiUrl, 'http://localhost:5173')
  await assertAllowedCorsOrigin(request, apiUrl, 'http://127.0.0.1:5173')

  const untrustedCorsResponse = await request.get(`${apiUrl}/api/health`, {
    headers: { Origin: 'https://untrusted.example' },
  })
  expect(untrustedCorsResponse.status()).toBe(200)
  expect(untrustedCorsResponse.headers()['access-control-allow-origin']).toBeUndefined()

  await page.getByLabel('Correo electrónico').fill(email)
  await page.getByLabel('Contraseña').fill(password)

  const csrfResponsePromise = page.waitForResponse((response) =>
    new URL(response.url()).pathname === '/sanctum/csrf-cookie',
  )
  const loginResponsePromise = page.waitForResponse((response) =>
    new URL(response.url()).pathname === '/api/v1/auth/login',
  )
  await page.getByRole('button', { name: 'Entrar', exact: true }).click()

  const csrfResponse = await csrfResponsePromise
  expect(csrfResponse.status()).toBe(204)
  expect((await page.context().cookies()).some((cookie) => cookie.name === 'XSRF-TOKEN')).toBe(true)

  const loginResponse = await loginResponsePromise
  expect(loginResponse.status()).toBe(200)
  expect((await page.context().cookies()).some((cookie) => cookie.name === 'quinielalab-session')).toBe(true)

  await expect(page).toHaveURL('/')
  await expect(page.getByRole('heading', { name: 'Infraestructura visible.' })).toBeVisible()
  await expect(page.getByText('Fase 0 completada', { exact: true })).toBeVisible()
  await expect(page.getByText('MySQL', { exact: true })).toBeVisible()
  await expect(page.getByText('Redis', { exact: true })).toBeVisible()

  const profileResponse = await page.evaluate(async () => {
    const response = await fetch('/api/v1/auth/me', {
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })

    return { status: response.status, payload: await response.json() }
  })
  expect(profileResponse.status).toBe(200)
  expect(profileResponse.payload).toMatchObject({ data: { email } })

  await page.getByRole('button', { name: 'Sorteos', exact: true }).click()
  await expect(page).toHaveURL('/sorteos')
  await expect(page.getByRole('heading', { name: 'Sorteos', exact: true })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Sincronizar todas', exact: true })).toBeVisible()
  await expect(page.getByRole('button', { name: /^Sincronizar (?!todas)/ })).toHaveCount(10)
  expect(consoleErrors).toEqual([])
})

test('sincroniza una lotería desde Sorteos mediante el provider fake', async ({ page }) => {
  const email = requiredEnvironmentVariable('E2E_EMAIL')
  const password = requiredEnvironmentVariable('E2E_PASSWORD')
  const consoleErrors: string[] = []
  const anonymousProfileResponse = page.waitForResponse(response =>
    new URL(response.url()).pathname === '/api/v1/auth/me',
  )
  await page.goto('/login')
  expect((await anonymousProfileResponse).status()).toBe(401)
  page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text())
  })

  await page.getByLabel('Correo electrónico').fill(email)
  await page.getByLabel('Contraseña').fill(password)
  await page.getByRole('button', { name: 'Entrar', exact: true }).click()
  await expect(page).toHaveURL('/')
  await page.getByRole('button', { name: 'Sorteos', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Sorteos', exact: true })).toBeVisible()
  await expect(page.getByRole('button', { name: /^Sincronizar (?!todas)/ })).toHaveCount(10)

  const syncResponse = page.waitForResponse((response) => new URL(response.url()).pathname === '/api/v1/sync-runs' && response.request().method() === 'POST')
  await page.getByRole('button', { name: 'Sincronizar Lotería Nacional' }).click()
  expect((await syncResponse).status()).toBe(202)
  await expect(page.getByText('Sincronización encolada.')).toBeVisible()
  await expect.poll(async () => (await page.getByText('04 · 00 · 97', { exact: true }).count()) > 0).toBe(true)

  const duplicateResponse = page.waitForResponse((response) => new URL(response.url()).pathname === '/api/v1/sync-runs' && response.request().method() === 'POST')
  await page.getByRole('button', { name: 'Sincronizar Lotería Nacional' }).click()
  expect((await duplicateResponse).status()).toBe(202)
  await expect(page.getByText('No se creó otra ejecución porque ya existe una sincronización activa o fue consultada recientemente.')).toBeVisible()
  expect(consoleErrors).toEqual([])
})
