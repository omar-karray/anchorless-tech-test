# Troubleshooting Guide

This guide covers common issues you might encounter and their solutions.

## Common Setup Issues

### Port Already in Use

**Symptom:** Error message about ports 80, 5173, 8080, or other ports being in use.

**Solution:**

```bash
# Check what's using the port
lsof -i :5173  # Frontend
lsof -i :80    # Backend
lsof -i :8080  # Reverb WebSocket

# Kill the process using the port
kill -9 <PID>

# Or stop all Docker containers
docker stop $(docker ps -aq)

# Then restart
make app-boot
```

**Alternative:** Change the port mapping in `docker-compose.yml`:

```yaml
ports:
  - "5174:5173"  # Use 5174 instead of 5173
```

### Docker Desktop Not Running

**Symptom:** Error: `Cannot connect to the Docker daemon`

**Solution:**

1. Start Docker Desktop application
2. Wait for Docker to fully start (icon shows "running")
3. Run `make app-boot` again

**Mac:** Check menu bar for Docker whale icon
**Windows:** Check system tray for Docker icon

### Database Connection Failed

**Symptom:** Error connecting to PostgreSQL database.

**Solution:**

```bash
# Restart PostgreSQL container
docker compose restart postgres

# Check logs for errors
docker compose logs postgres

# If corrupted, recreate database
make app-reboot
```

### Frontend Build Fails

**Symptom:** Error during `npm run build` or frontend container fails to start.

**Solution:**

```bash
# Clear node_modules and rebuild
make frontend-sh
rm -rf node_modules package-lock.json
npm install
npm run build
exit

# Or from host
docker compose exec react-frontend sh -c "rm -rf node_modules && npm install && npm run build"
```

### Reverb WebSocket Connection Issues

**Symptom:** Real-time updates not working, console shows WebSocket connection errors.

**Solution:**

```bash
# Check Reverb is running
docker compose ps reverb

# Restart Reverb
docker compose restart reverb

# Check logs
docker compose logs reverb -f
```

**Frontend check:**

Open browser console and verify:

```javascript
// Should show WebSocket connected
window.Echo.connector.pusher.connection.state
// Expected: "connected"
```

## Runtime Issues

### Files Not Uploading

**Symptom:** File upload appears to work but files don't appear in the list.

**Checklist:**

1. **Check Horizon is running:**
   ```bash
   docker compose logs horizon
   ```

2. **Check MinIO is accessible:**
   Visit http://localhost:9001 (minioadmin/minioadmin)

3. **Check queue jobs:**
   ```bash
   make backend-artisan cmd="queue:work --once"
   ```

4. **Check Redis:**
   ```bash
   docker compose exec redis redis-cli ping
   # Expected: PONG
   ```

5. **Check file size:**
   - Maximum file size: 4MB
   - Check file is not corrupted

### Authentication Issues

**Symptom:** Login fails, gets logged out immediately, or 401/403 errors.

**Solution:**

```bash
# Clear application cache
make backend-artisan cmd="cache:clear"
make backend-artisan cmd="config:clear"

# Check session configuration
make backend-artisan cmd="config:show session"

# Recreate test user
make backend-artisan cmd="db:seed --class=UserSeeder"
```

**Browser:**

- Clear cookies for localhost
- Try incognito/private window
- Check browser console for CORS errors

### Page Shows 404 or Blank Screen

**Symptom:** Frontend loads but shows blank page or 404.

**Solution:**

```bash
# Rebuild frontend
make frontend-build

# Check React Router routes
docker compose logs react-frontend

# Access container and check build output
make frontend-sh
ls -la dist/
exit
```

### WebSocket Real-time Updates Not Working

**Symptom:** Files upload but no success notification appears.

**Debugging:**

1. **Open browser console** and check for errors

2. **Verify WebSocket connection:**
   ```javascript
   // In browser console
   window.Echo.connector.pusher.connection.state
   // Should be: "connected"
   ```

3. **Check Reverb logs:**
   ```bash
   docker compose logs reverb -f
   ```

4. **Verify event is broadcast:**
   ```bash
   docker compose logs laravel.test | grep "Broadcasting"
   ```

5. **Check channel subscription:**
   - Ensure you're logged in
   - Check policy allows access to private channel
   - Verify `/broadcasting/auth` endpoint returns 200

## Performance Issues

### Slow File Upload Processing

**Symptom:** Files take a long time to process.

**Solution:**

```bash
# Check queue worker is running
docker compose ps horizon

# Increase queue workers in config/horizon.php
# Then restart
docker compose restart horizon

# Process queue manually (for testing)
make backend-artisan cmd="queue:work --tries=3"
```

### Application Running Slowly

**Checklist:**

1. **Check Docker resource allocation:**
   - Docker Desktop → Settings → Resources
   - Recommended: 4GB+ RAM, 2+ CPUs

2. **Check container resources:**
   ```bash
   docker stats
   ```

3. **Optimize Laravel cache:**
   ```bash
   make backend-artisan cmd="optimize"
   ```

4. **Check database queries:**
   - Enable query log in `.env`: `DB_LOG_QUERIES=true`
   - Check `storage/logs/laravel.log` for slow queries

## Development Issues

### Changes Not Reflecting

**Frontend:**

```bash
# Ensure dev server is running with hot reload
docker compose logs react-frontend -f

# If using build mode, rebuild
make frontend-build
```

**Backend:**

```bash
# Clear all caches
make backend-artisan cmd="cache:clear"
make backend-artisan cmd="config:clear"
make backend-artisan cmd="route:clear"
make backend-artisan cmd="view:clear"

# Restart PHP-FPM (Laravel container)
docker compose restart laravel.test
```

### Migration Errors

**Symptom:** Error running migrations.

**Solution:**

```bash
# Check current migration status
make backend-artisan cmd="migrate:status"

# Rollback and re-run
make backend-artisan cmd="migrate:fresh --seed"

# If database is corrupted, full reset
make app-reboot
```

### Seeder Data Not Appearing

**Solution:**

```bash
# Re-run seeders
make backend-artisan cmd="db:seed"

# Or specific seeder
make backend-artisan cmd="db:seed --class=FileCategorySeeder"

# Fresh database with seed
make backend-artisan cmd="migrate:fresh --seed"
```

## Docker Issues

### Container Won't Start

**Solution:**

```bash
# Check logs for specific container
docker compose logs <container-name>

# Examples:
docker compose logs laravel.test
docker compose logs postgres
docker compose logs redis

# Remove and recreate
docker compose down
docker compose up -d
```

### Out of Disk Space

**Solution:**

```bash
# Remove unused Docker resources
docker system prune -a --volumes

# Check disk usage
docker system df

# Remove specific volumes
docker volume ls
docker volume rm <volume-name>
```

### Permission Errors

**Symptom:** Permission denied errors in logs.

**Solution:**

```bash
# Fix Laravel storage permissions
make backend-bash
chmod -R 777 storage bootstrap/cache
exit

# Or from host
docker compose exec laravel.test chmod -R 777 storage bootstrap/cache
```

## Logs & Debugging

### View All Logs

```bash
# All services
docker compose logs -f

# Specific service
docker compose logs laravel.test -f
docker compose logs react-frontend -f
docker compose logs horizon -f
docker compose logs reverb -f
```

### Laravel Logs

```bash
# Inside container
make backend-bash
tail -f storage/logs/laravel.log
exit

# Or from host
docker compose exec laravel.test tail -f storage/logs/laravel.log
```

### Check Service Health

```bash
# All containers status
docker compose ps

# Check specific service
docker compose exec laravel.test php artisan about

# Check database connection
docker compose exec laravel.test php artisan db:show

# Check Redis connection
docker compose exec redis redis-cli ping
```

## Getting Further Help

If your issue is not listed here:

1. **Check service logs:**
   ```bash
   docker compose logs -f
   ```

2. **Review Laravel logs:**
   ```bash
   docker compose exec laravel.test tail -f storage/logs/laravel.log
   ```

3. **Check browser console:**
   - Open DevTools (F12)
   - Check Console tab for JavaScript errors
   - Check Network tab for failed requests

4. **Verify configuration:**
   ```bash
   # Laravel config
   make backend-artisan cmd="config:show"
   
   # Environment
   make backend-bash
   cat .env
   exit
   ```

5. **Full application reset:**
   ```bash
   make app-reboot
   ```
   ⚠️ **Warning:** This destroys all data!

6. **Contact the development team** with:
   - Error message
   - Relevant logs
   - Steps to reproduce

---

Still having issues? Return to the [Getting Started Guide](getting-started.md) or check the [Backend Setup](backend-setup.md) for detailed configuration.
