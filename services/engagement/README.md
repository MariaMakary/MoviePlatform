# Engagement service

Lumen 10 microservice. **Port:** 8003 Â· **Database:** `engagement-db` (host port 5435) Â· **Health check:** `GET http://localhost:8003/api/health`

Ratings, reviews, watchlist, watch history, custom lists and moderation.

Run it from this folder:

```powershell
php -S 127.0.0.1:8003 -t public
```

Part of MoviePlatform. See the root `README.md` for setup and `CLAUDE.md` for the architecture and conventions.