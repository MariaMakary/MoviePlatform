# Social service

Lumen 10 microservice. **Port:** 8004 · **Database:** `social-db` (host port 5436) · **Health check:** `GET http://localhost:8004/api/health`

Follows, profiles, privacy, activity feed, movie nights and chat rooms.

Run it from this folder:

```powershell
php -S 127.0.0.1:8004 -t public
```

Part of MoviePlatform. See the root `README.md` for setup and `CLAUDE.md` for the architecture and conventions.