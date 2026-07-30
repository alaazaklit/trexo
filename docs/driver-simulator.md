# Driver Simulator

## URL

- `/admin/driver-simulator`

## What it does

- Spawns fake drivers into the existing `users` and `drivers` tables.
- Moves simulated drivers every poll cycle.
- Lets you drag markers on the Google Map and persist the new position.
- Creates simulated ride requests and transitions them through accept, pickup, trip started, and finished.
- Reuses the existing authenticated admin area and the same user record that the driver app already uses.

## Setup

1. Set `GOOGLE_MAPS_KEY` in your `.env`.
2. Run migrations.
3. Build assets with Vite.
4. Open `/admin/driver-simulator`.

## Driver API sync

- `App\Http\Controllers\Api\UsersController@updateProfile` now accepts `latitude`, `longitude`, `heading`, `speed_kmh`, and `last_seen_at`.
- That gives the driver app a compatible location update path while keeping the simulator aligned with the same user record.

## Simulator routes

- `GET /admin/driver-simulator`
- `GET /admin/driver-simulator/state`
- `POST /admin/driver-simulator/tick`
- `POST /admin/driver-simulator/spawn`
- `POST /admin/driver-simulator/drivers`
- `PUT /admin/driver-simulator/drivers/{driver}`
- `DELETE /admin/driver-simulator/drivers/{driver}`
- `POST /admin/driver-simulator/drivers/{driver}/toggle`
- `POST /admin/driver-simulator/drivers/{driver}/move`
- `POST /admin/driver-simulator/ride-requests`
- `POST /admin/driver-simulator/ride-requests/{ride}/decision`

## Notes

- Polling is enabled every second for live updates.
- Ride movement uses a simplified route approximation so it is lightweight and deterministic for development.
- If you want, we can add websocket broadcasting and hook the simulator directly into the passenger ride creation flow next.
