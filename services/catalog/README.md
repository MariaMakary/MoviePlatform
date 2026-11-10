# Catalog service

Lumen 10 microservice. **Port:** 8002 · **Database:** `catalog-db` (host port 5434) · **Health check:** `GET http://localhost:8002/api/health`

TMDB integration, local movie database, search, genres and people.

Run it from this folder:

```powershell
php -S 127.0.0.1:8002 -t public
```

Part of MoviePlatform. See the root `README.md` for setup and `CLAUDE.md` for the architecture and conventions.