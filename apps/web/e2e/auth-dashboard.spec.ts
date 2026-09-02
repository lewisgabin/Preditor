import { expect, test } from '@playwright/test'

test('el propietario inicia sesión y consulta el dashboard', async ({ page }) => {
  const email = process.env.E2E_EMAIL
  const password = process.env.E2E_PASSWORD

  test.skip(!email || !password, 'E2E_EMAIL y E2E_PASSWORD son obligatorios')

  await page.goto('/login')
  await page.getByLabel('Correo electrónico').fill(email!)
  await page.getByLabel('Contraseña').fill(password!)
  await page.getByRole('button', { name: 'Entrar', exact: true }).click()

  await expect(page).toHaveURL('/')
  await expect(page.getByRole('heading', { name: 'Infraestructura visible.' })).toBeVisible()
  await expect(page.getByText('Fase 0 completada', { exact: true })).toBeVisible()
  await expect(page.getByText('MySQL', { exact: true })).toBeVisible()
  await expect(page.getByText('Redis', { exact: true })).toBeVisible()
})
