# Notification service

Lumen 10 microservice. **Port:** 8006 Â· **Database:** `notification-db` (host port 5438) Â· **Health check:** `GET http://localhost:8006/api/health`

User notifications.

Run it from this folder:

```powershell
php -S 127.0.0.1:8006 -t public
```

Part of MoviePlatform. See the root `README.md` for setup and `CLAUDE.md` for the architecture and conventions.