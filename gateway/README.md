# Gateway

Laravel 12 web app. **Port:** 8000 · **Health check:** `GET http://localhost:8000/up`

The MoviePlatform user interface (Blade + Livewire + Tailwind) and admin dashboard. It owns no business data: every
page gets its data from the services through Client classes (`app/Services/Clients`). Sessions and cache are stored in
Redis.

Run it from this folder:

```powershell
php artisan serve --port=8000
npm run dev
```

`npm run dev` builds the CSS and JS with hot reload. The services you use must be running too (see the root `README.md`).

Part of MoviePlatform. See the root `README.md` for setup and `CLAUDE.md` for the architecture and conventions.
