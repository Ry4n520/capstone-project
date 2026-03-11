# Smart Campus Debugging Guide

## Overview
This guide helps you debug the notification system and homepage display when data isn't showing up as expected.

## System Architecture
The system has 4 main components working together:

1. **Backend APIs** - PHP files that query the database
   - `/api/get-notifications.php` - Aggregates announcements, bookings, class reminders
   - `/api/get-homepage-data.php` - Returns dashboard data (classes, attendance, bookings, announcements)

2. **Frontend JavaScript** - Calls APIs and renders data
   - `/assets/js/header.js` - Loads notifications every 60 seconds
   - `/assets/js/homepage.js` - Loads homepage data every 60 seconds

3. **Database** - Stores the actual data
   - `announcements` - Public announcements
   - `bookings` - Facility reservations
   - `timetables` - Class schedules
   - `enrollments` - Student course enrollments

4. **HTML Templates** - Static page structure
   - `/includes/header.php` - Header with notification bell
   - `/facility-booking.php` - Homepage with dashboard cards

## Debugging Steps

### Step 1: Check Browser Console for Errors
1. Open your browser (Chrome, Firefox, Edge, etc.)
2. Press `F12` to open Developer Tools
3. Click the "Console" tab
4. Look for any red error messages
5. Send us screenshot of any errors

**Expected output:**
- `Smart Campus navigation loaded`
- `[Notifications] Loading notifications from API...`
- `[Notifications] API response status: 200`
- `[Notifications] Got X notifications, unread: Y`

**If you see errors, screenshot them and tell us the exact error message.**

### Step 2: Check API Response in Network Tab
This is the MOST IMPORTANT step to identify data issues.

1. Keep Developer Tools open (F12)
2. Click the "Network" tab
3. Open a new tab and navigate to your homepage: `http://localhost/public/facility-booking.php`
4. Look for these API calls in the Network tab:
   - `get-notifications.php`
   - `get-homepage-data.php`

5. **For each API call:**
   - **Status Column:** Should show `200` (green = success)
   - If you see `500` (red) = server error → Check PHP error logs
   - If you see `401` (yellow) = authentication error → Session not saved
   - If you don't see the API call at all = JavaScript not running

6. **Click on the API request** and check the "Response" tab:
   - You should see JSON data with your notifications and bookings
   - If Response is empty = API returned nothing

**Example successful response for get-homepage-data.php:**
```json
{
  "success": true,
  "data": {
    "todays_classes": [
      {
        "course_name": "Database Systems",
        "section_code": "CS101-A",
        "start_time": "09:00:00",
        "end_time": "11:00:00",
        ...
      }
    ],
    "upcoming_bookings": [
      {
        "facility_name": "Meeting Room 1",
        "booking_date": "2026-03-15",
        ...
      }
    ]
  },
  "debug_info": {
    "user_id": 123,
    "role": "student"
  }
}
```

### Step 3: Check PHP Error Logs
If you see HTTP 500 errors in the Network tab:

1. Look for PHP error logs in:
   - Docker container logs (if using Docker)
   - `/var/log/php-fpm.log` (if using PHP-FPM)
   - Check if Docker is running and healthy

**To check Docker logs:**
```bash
docker-compose logs php
```

Or on Windows PowerShell:
```powershell
docker compose logs php
```

**Look for these error patterns:**
- `Fatal error` - Function or class not found
- `Parse error` - Syntax error in PHP
- `Call stack error` - Database connection failed
- `MySQL error` - Database query failed

### Step 4: Verify Database Contains Your Data
Check if bookings and announcements actually exist in the database:

**For bookings:**
```sql
SELECT * FROM bookings WHERE user_id = YOUR_USER_ID;
```

**For announcements:**
```sql
SELECT * FROM announcements LIMIT 5;
```

**For students' enrollments:**
```sql
SELECT * FROM enrollments WHERE student_id = YOUR_USER_ID;
```

**For public holidays (should have 14 records):**
```sql
SELECT COUNT(*) FROM public_holidays;
```

If these queries return empty results, the data doesn't exist in the database yet.

### Step 5: Check Session and Authentication
The APIs require a logged-in session with `$_SESSION['user_id']`.

1. In Browser Console, type:
```javascript
console.log(document.cookie);
```

You should see a `PHPSESSID` cookie. If not, you're not logged in.

2. Verify you're logged in to the system
3. Check if `/auth/login.php` page works and stores session

### Step 6: Manual API Test
Test the API directly without the frontend:

1. In Browser address bar, go to:
   `http://localhost/public/api/get-notifications.php`

You should see JSON output like:
```json
{
  "success": true,
  "notifications": [...],
  "unread_count": 5
}
```

If you get an error page instead, the API file has a syntax error.

## Common Issues and Solutions

### Issue 1: "No recent notifications" message appears
**Diagnosis:** API is working but returning empty notifications array

**Possible causes:**
1. No announcements exist in database
2. No bookings exist for current user
3. No classes/enrollments for current student
4. All data is older than 7 days

**Solution:**
- Create a test announcement
- Create a test facility booking
- Check database with SQL queries above

### Issue 2: Notification popup shows something, but Network tab shows empty response
**Diagnosis:** JavaScript is showing cached data or hardcoded data

**Solution:**
- Hard refresh browser with `Ctrl+Shift+R` or `Cmd+Shift+R`
- Clear browser cache and cookies
- Check if there's a `.json` file with test data somewhere

### Issue 3: HTTP 500 error on API calls
**Diagnosis:** Server error occurred

**Solution:**
1. Check Docker PHP logs: `docker-compose logs php`
2. Look for "Fatal error" or "Parse error" messages
3. Check database connection: Is MySQL running?
4. Check if tables exist: Run `SHOW TABLES;` in MySQL

### Issue 4: HTTP 401 Unauthorized error
**Diagnosis:** Session is not authenticated

**Solution:**
1. Log in again
2. Make sure you're on same domain/port
3. Check if session cookie is being set
4. Verify `$_SESSION['user_id']` is set in login script

### Issue 5: Homepage cards show empty state, but bookings exist
**Diagnosis:** API returns data but JavaScript display logic has issues

**Solution:**
1. Check browser Console for JavaScript errors
2. Verify API response contains data (Network tab)
3. Hard refresh browser to reload new JavaScript code
4. Check if CSS is hiding the elements (display: none, opacity: 0)

## Advanced Debugging

### Enable SQL Query Logging
Add this to get-homepage-data.php to log all queries:

```php
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
```

### Check Browser Storage
1. In Dev Tools, go to Application/Storage tab
2. Check localStorage for any cached data
3. Clear all storage and reload

### Enable PHP Debug Mode
Add to top of API files:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

This will show errors directly in API response (careful in production!).

## Data Flow Diagram

```
User visits homepage
         ↓
JavaScript loads (DOMContentLoaded)
         ↓
Call fetch('api/get-homepage-data.php')
         ↓
PHP queries database (SELECT from timetables, bookings, announcements)
         ↓
PHP returns JSON response
         ↓
JavaScript receives JSON
         ↓
JavaScript displays data in card elements
         ↓
User sees dashboards with classes, bookings, announcements
```

## Testing Checklist

- [ ] Browser console shows `Smart Campus navigation loaded`
- [ ] Console shows `[Notifications] API response status: 200`
- [ ] Network tab shows `get-notifications.php` request with 200 status
- [ ] Network tab shows `get-homepage-data.php` request with 200 status
- [ ] API responses contain JSON data (not empty or error message)
- [ ] Database has bookings for current user (SQL query returns results)
- [ ] Database has at least one announcement (SQL query returns results)
- [ ] User is logged in (PHPSESSID cookie exists)
- [ ] Homepage cards display data (not showing empty state)
- [ ] Notification bell shows unread count badge in red

## Still Having Issues?

If you've gone through all these steps, provide the following information:

1. **Screenshot of Browser Console** - Show any red error messages
2. **Screenshot of Network tab** showing:
   - Status codes (should all be 200)
   - Response tab content of failed requests
3. **Output of these SQL queries:**
   ```sql
   SELECT COUNT(*) FROM bookings WHERE user_id = YOUR_ID;
   SELECT COUNT(*) FROM announcements;
   SELECT COUNT(*) FROM enrollments WHERE student_id = YOUR_ID;
   ```
4. **PHP error log** - Docker logs or server logs
5. **Current user ID** - Your login user ID from database

With this information, we can identify exactly where the data flow is breaking.

## Files Modified for Debugging

These files now include enhanced logging and error handling:

- `/api/get-notifications.php` - Added error_log() statements and debug_errors in response
- `/api/get-homepage-data.php` - Added error_log() statements and debug_info in response
- `/assets/js/header.js` - Added detailed console.log messages with [Notifications] prefix
- `/assets/js/homepage.js` - Already has detailed console logging

**To see the logs:**
1. Check browser Console (F12 → Console tab) for JavaScript logs
2. Check Docker/PHP logs for PHP error_log() output
3. Check API responses for debug fields

