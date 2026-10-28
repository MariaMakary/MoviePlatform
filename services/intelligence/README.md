# Intelligence service

Lumen 10 microservice. **Port:** 8005 Â· **Database:** `intelligence-db` (host port 5437) Â· **Health check:** `GET http://localhost:8005/api/health`

Recommendations, review sentiment, weather and calendar.

Run it from this folder:

```powershell
php -S 127.0.0.1:8005 -t public
```

Part of MoviePlatform. See the root `README.md` for setup and `CLAUDE.md` for the architecture and conventions.