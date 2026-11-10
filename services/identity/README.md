# Identity service

Lumen 10 microservice. **Port:** 8001 · **Database:** `identity-db` (host port 5433) · **Health check:** `GET http://localhost:8001/api/health`

Users, authentication, social login (Google/GitHub), roles and JWT issuing (RS256).

Run it from this folder:

```powershell
php -S 127.0.0.1:8001 -t public
```

Part of MoviePlatform. See the root `README.md` for setup and `CLAUDE.md` for the architecture and conventions.