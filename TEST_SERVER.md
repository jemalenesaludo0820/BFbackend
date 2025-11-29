# Testing the Server

## Quick Test

1. Start the server:
   ```bash
   php -S localhost:3002 server.php
   ```

2. Test the root endpoint:
   - Open: `https://bfbackend-l9q7.onrender.com/`
   - Should return JSON with API info

3. Test API endpoint:
   - Open: `https://bfbackend-l9q7.onrender.com/api/get_user`
   - Should return: `{"success":false,"message":"Not logged in"}`

If you see these responses, the server is working correctly!

## Common Issues

### "Not Found" error
- Make sure you're using `server.php` as the router
- Command should be: `https://bfbackend-l9q7.onrender.com`
- NOT: `php -S localhost:3002` (this won't work)

### Port already in use
- Change port: `https://bfbackend-l9q7.onrender.com`
- Update `frontend/vite.config.js` proxy target to match

### CORS errors
- Check that CORS headers are set in `index.php`
- Verify frontend URL matches: `https://blog-flow-nu.vercel.app/`

