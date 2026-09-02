# Versiones verificadas para Fase 0

Verificadas el 1 de septiembre de 2026:

- Laravel: rama 13.x; el esqueleto oficial requiere PHP `^8.3` y actualmente declara `laravel/framework:^13.17`.
- PHP del contenedor objetivo: 8.4.
- React: 19.2.8.
- Vite: 8.2.2; Vite requiere Node 20.19+ o 22.12+.
- Node del contenedor objetivo: 22.
- Tailwind CSS y `@tailwindcss/vite`: 4.3.3.
- shadcn/ui: CLI 4.19.1; la versión efectiva queda fijada por `package-lock.json` y los componentes pasan a ser código del proyecto.

Fuentes oficiales:

- https://laravel.com/docs/13.x/releases
- https://github.com/laravel/laravel/blob/13.x/composer.json
- https://react.dev/versions
- https://vite.dev/releases
- https://vite.dev/guide/
- https://tailwindcss.com/blog/tailwindcss-v4-3
- https://tailwindcss.com/docs/installation/using-vite
- https://ui.shadcn.com/docs/installation/vite
