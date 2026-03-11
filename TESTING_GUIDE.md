# Testing & Expected Outputs

## What You Should See After Updates

### 1. Browser Console (F12 → Console Tab)

**When you open the homepage, you should see these logs in order:**

```
Smart Campus navigation loaded
[Notifications] Loading notifications from API...
[Notifications] API response status: 200
[Notifications] API data received: {notifications: Array(0), unread_count: 0, debug_errors: Array(0)}
[Notifications] Got 0 notifications, unread: 0
```

**If you have announcements/bookings, you'll see:**
```
[Notifications] Got 3 notifications, unread: 2
```

### 2. Browser Network Tab (F12 → Network Tab)

**Step by step:**
1. Refresh the page (F5)
2. Look at the list of requests
3. Find: `get-notifications.php`
   - **Status:** `200` (green)
   - **Type:** `fetch`
   - **Size:** `1.2 KB` (or similar)

4. Click on the request
5. Click "Response" tab
6. You should see JSON like:
```json
{
  "success": true,
  "notifications": [
    {
      "id": 1,
      "type": "announcement",
      "title": "System Maintenance",
      "message": "New announcement: Maintenance scheduled for...",
      "created_at": "2026-03-15 09:00:00",
      "time_ago": "1 hour ago"
    }
  ],
  "unread_count": 1,
  "debug_errors": []
}
```

7. Find: `get-homepage-data.php`
   - **Status:** `200` (green)
   - **Response:** JSON with your data

### 3. Notification Badge on Bell Icon

**In the header, you should see:**
- Bell icon (🔔)
- Red badge showing count (e.g., "5")
- Badge only shows if unread_count > 0

### 4. Notification Popup

**Click the bell icon, you should see:**
- List of recent notifications
- Each with emoji icon based on type:
  - `📢` for announcements
  - `✓` for confirmed bookings
  - `✗` for cancelled bookings
  - `⏳` for pending bookings
  - `🔔` for class reminders
- Time ago (e.g., "2 hours ago")

### 5. Homepage Dashboard Cards

**You should see these cards populated:**

#### Card 1: "Today's Classes" (if student)
Shows classes for today with:
- Course name
- Room location
- Lecturer name
- Class status (ongoing/upcoming/completed)
- Time

Example display:
```
Database Systems (CS101-A)
Room: SD201 (Building A)
Lecturer: Dr. Smith
Upcoming | 9:00 AM - 11:00 AM
```

#### Card 2: "Attendance Rate" (if student)
Shows percentage and comparison
```
Attendance Rate
92.5%
↑ 5% above class average
```

#### Card 3: "Recent Announcements"
Shows latest announcements with titles and dates
```
System Maintenance
Scheduled for March 20
Posted: Today
```

#### Card 4: "Upcoming Bookings"
Shows next 3 facility bookings
```
Meeting Room 1
March 15, 2026 | 2:00 PM - 3:00 PM
Status: Confirmed
```

## Troubleshooting by Symptom

### Symptom 1: Console shows "API response status: 401"
**Problem:** Not authenticated
**Solution:**
1. Log out
2. Log in again
3. Refresh homepage

### Symptom 2: Console shows "API response status: 500"
**Problem:** Server error
**Solution:**
1. Check Docker logs: `docker-compose logs php`
2. Look for "Fatal error" or "Parse error"
3. Check if MySQL is running

### Symptom 3: Console shows status 200 but notifications is empty array
**Problem:** No data in database or queries returning nothing
**Solution:**
1. Create test announcement in database
2. Create test facility booking
3. Refresh homepage
4. Should see notifications now

### Symptom 4: Bell icon has no red badge
**Problem:** unread_count is 0
**Solution:**
1. Check if you have any recent notifications
2. Must be from last 24 hours to count as "unread"
3. Create new announcement or booking

### Symptom 5: Homepage cards show empty state message
**Problem:** API returning data but display function not rendering
**Solution:**
1. Hard refresh: Ctrl+Shift+R (Windows) or Cmd+Shift+R (Mac)
2. Check browser console for errors
3. Check Network tab - is API returning data?
4. If API has data but not displaying, it's a JavaScript issue

## Test Scenarios

### Scenario 1: Verify Notifications Work
**Steps:**
1. Log in to the system
2. Open browser console (F12)
3. Go to admin panel or database tool
4. Create a new announcement: Title "Test 123", Content "Testing notification system"
5. Go back to homepage
6. Wait 5 seconds
7. **Check:** Red badge appears on bell with count "1"
8. **Check:** Click bell, see "Test 123" announcement with 📢 emoji

**Expected Time:** 5 seconds (API refreshes every 60s, or immediately if you manually trigger)

### Scenario 2: Verify Homepage Classes Display
**Steps:**
1. Log in as a student account that has class enrollments
2. Open homepage
3. Look at "Today's Classes" card
4. **Check:** Card shows your courses for today
5. **Check:** Shows correct room number and lecturer
6. **Check:** Shows correct class status (upcoming if time hasn't started)

**Expected:** If today is a class day, shows 1-3 classes; if not, shows empty with "No classes today"

### Scenario 3: Verify Bookings Display
**Steps:**
1. Make a facility booking for a future date
2. Refresh homepage
3. **Check:** "Upcoming Bookings" card shows your booking
4. **Check:** Shows facility name, date, time
5. **Check:** Shows "Confirmed" or "Pending" status

**Expected:** Booking appears within 5 seconds after creation

### Scenario 4: Verify Attendance Shows
**Requirements:** Must be student with class sessions and attendance marked
**Steps:**
1. Log in as student
2. Go to homepage
3. **Check:** "Attendance Rate" card shows percentage
4. **Check:** Shows trend indicator (↑ or ↓ vs class average)

**Expected:** Shows your calculated attendance percentage

## Database Queries for Verification

### Check Announcements Exist
```sql
SELECT announcement_id, title, created_date FROM announcements ORDER BY created_date DESC LIMIT 5;
```
Should return results if announcements table has data.

### Check Your Bookings
```sql
SELECT booking_id, facility_id, booking_date, start_time, booking_status 
FROM bookings 
WHERE user_id = (SELECT user_id FROM users WHERE email = 'YOUR_EMAIL')
ORDER BY booking_date DESC;
```
Should show your facility bookings.

### Check Class Enrollments
```sql
SELECT e.enrollment_id, cs.section_code, c.course_name, e.status
FROM enrollments e
JOIN course_sections cs ON e.section_id = cs.section_id
JOIN courses c ON cs.course_id = c.course_id
WHERE e.student_id = (SELECT user_id FROM users WHERE email = 'YOUR_EMAIL');
```
Should show your enrolled classes.

### Check Timetable for Today
```sql
SELECT t.timetable_id, c.course_name, t.start_time, t.end_time
FROM timetables t
JOIN course_sections cs ON t.section_id = cs.section_id
JOIN courses c ON cs.course_id = c.course_id
WHERE t.day_of_week = DAYNAME(CURDATE())
AND DATE(NOW()) BETWEEN t.week_start_date AND t.week_end_date;
```
Should show classes scheduled for today.

## Performance Notes

- **API Response Time:** Should be < 500ms
- **Notifications Refresh:** Every 60 seconds automatically
- **Homepage Data Refresh:** Every 60 seconds automatically
- **Manual Refresh:** Refresh (F5) always fetches latest data

If API takes > 1000ms:
- Database might be slow
- Too many rows in announcements/bookings table
- It's time to add database indexes

## Mobile Testing

The system should also work on mobile:
- Open on phone browser
- Bell icon should be visible
- Notifications popup should be readable
- Homepage cards should stack vertically
- All data should display correctly

Check if responsive CSS is working by resizing browser window.

## Browser Compatibility

**Tested and working on:**
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

**Key JavaScript features used:**
- `fetch()` API
- `querySelector()` and `querySelectorAll()`
- `Array.map()` and `.join()`
- Template literals with backticks

If using an old browser, some features might not work.

## Next Steps

1. **Do the Testing Checklist** - Verify everything shows what's expected
2. **Create Test Data** - Make bookings and announcements
3. **Monitor Console** - Watch for any error messages
4. **Check Network Tab** - Verify API calls are successful
5. **Collect Logs** - If issues exist, gather console logs and API responses

With all these improvements, you now have comprehensive visibility into what's happening at every step of the data flow!

