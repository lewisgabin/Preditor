# Crear el repositorio privado y conectarlo a Codex

## GitHub

Crear un repositorio privado:

```text
Owner: lewisgabin
Repository: quiniela-lab
Default branch: main
```

No inicializarlo con README, porque este paquete ya lo contiene.

## Subir desde una terminal

```bash
unzip QuinielaLab_Fase0_Repositorio_Inicial.zip
cd quiniela-lab

git init -b main
git add .
git commit -m "docs: initialize QuinielaLab and phase 0"
git remote add origin git@github.com:lewisgabin/quiniela-lab.git
git push -u origin main
```

También puede usarse HTTPS:

```bash
git remote add origin https://github.com/lewisgabin/quiniela-lab.git
```

## Codex cloud

1. Abrir Codex.
2. Conectar GitHub.
3. Dar acceso a `lewisgabin/quiniela-lab`.
4. Crear un entorno para el repositorio.
5. Pegar el contenido de `CODEX_PHASE_0_PROMPT.md`.
6. Revisar el diff y abrir el PR.

Codex leerá `AGENTS.md` automáticamente y también encontrará la skill local de entrega por fases.
