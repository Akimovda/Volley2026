# Dev — Volley

## Frontend
- Vite
- команды:
  - `npm run dev`
  - `npm run build`

## Styles
- основные стили проекта вынесены в `resources/css/volley.css`
- `resources/css/app.css` импортирует `volley.css`

## Releases / Changelog
Используется `standard-version`.

### Alpha release
```bash
cd /var/www/volley-bot
npx --prefix backend standard-version --prerelease alpha
git push --follow-tags origin main
