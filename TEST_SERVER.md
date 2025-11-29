# Testing the Server

## Quick Test

1. Start the server:
   ```bash
   php -S localhost:3002 server.php
   ```

2. Test the root endpoint:
   - Open: `http://localhost:3002/`
   - Should return JSON with API info

3. Test API endpoint:
   - Open: `http://localhost:3002/api/get_user`
   - Should return: `{"success":false,"message":"Not logged in"}`

If you see these responses, the server is working correctly!

## Common Issues

### "Not Found" error
- Make sure you're using `server.php` as the router
- Command should be: `php -S localhost:3002 server.php`
- NOT: `php -S localhost:3002` (this won't work)

### Port already in use
- Change port: `php -S localhost:3003 server.php`
- Update `frontend/vite.config.js` proxy target to match

### CORS errors
- Check that CORS headers are set in `index.php`
- Verify frontend URL matches: `http://localhost:5173`

