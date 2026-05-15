# Event Ethiopia - Setup Instructions

## Quick Start Guide

### Step 1: Database Setup

Before you can log in, you need to initialize the database with sample data.

1. **Open your browser** and navigate to:

   ```
   http://localhost/emp/setup-database.php
   ```

2. **Run the setup** - This will:
   - Create the database `ethiopian_events`
   - Create all required tables (users, events, categories, bookings, reviews)
   - Insert sample user accounts with test data
   - Insert sample categories

3. **Success!** You should see a green message confirming the setup is complete.

---

## Test Credentials

After running `setup-database.php`, you can log in with these credentials:

### Admin Account

- **Email:** `admin@example.com`
- **Password:** `password`
- **Access:** Admin Dashboard - Full system control, user management, approvals

### Event Organizer Account

- **Email:** `organizer@example.com`
- **Password:** `password`
- **Access:** Organizer Dashboard - Manage your own events and bookings

### Regular User Account

- **Email:** `user@example.com`
- **Password:** `password`
- **Access:** Browse events, book tickets, leave reviews

---

## Features

### User Roles & Access Control (RBAC)

**Admin (Super User)**

- Full system control
- View and manage all users
- Approve/reject event organizers
- View all events and bookings
- View system statistics and revenue

**Event Organizer**

- Manage their own events
- Create, edit, delete events
- View bookings for their events
- Generate QR codes for tickets
- Track event performance and revenue

**Normal User (Attendee)**

- Browse all published events
- Filter events by category/date/location
- Book event tickets
- Receive QR code tickets
- Leave event reviews and ratings

---

## Pages & Features

### Public Pages

- **Login** (`/login.php`) - Beautiful gradient design with two-column layout
- **Register** (`/register.php`) - Create new accounts as user or organizer
- **Events** (`/events.php`) - Browse and search all published events
- **Event Details** (`/event-details.php`) - View event info, book tickets, read reviews

### Dashboard Pages (Require Login)

- **Admin Dashboard** (`/admin-dashboard.php`) - System overview and management
- **Organizer Dashboard** (`/organizer-dashboard.php`) - Event management
- **Events Booking** - Users can view their bookings from the events page

---

## Dark Mode

All pages support dark mode! Click the moon/sun icon (🌙/☀️) in the top right corner to toggle between light and dark themes. Your preference is saved in your browser.

---

## Database Details

**Database Name:** `ethiopian_events`

**Tables:**

- `users` - User accounts with role-based access
- `events` - Event listings created by organizers
- `categories` - Event categories
- `bookings` - Ticket bookings made by users
- `reviews` - User reviews and ratings for events

---

## Troubleshooting

### "Invalid email or password" Error

1. Make sure you ran `setup-database.php` first
2. Check that the credentials match exactly (case-sensitive)
3. Ensure MySQL is running on `localhost`
4. Verify the database connection in `includes/config.php`

### "Database connection error" Message

1. Check that MySQL is installed and running
2. Verify the database credentials in `includes/config.php`:
   - Host: `127.0.0.1`
   - Username: `root`
   - Password: (empty by default)
   - Database: `ethiopian_events`

### "Your organizer account is pending approval"

- Only admins can approve organizer accounts
- Log in as admin and approve the organizer in the admin dashboard
- The organizer will then be able to log in

### Dark Mode Not Working

1. Make sure JavaScript is enabled in your browser
2. Check that `js/dark-mode.js` exists in your project
3. Clear browser cache and reload the page

---

## Project Structure

```
event_manager/
├── login.php              # Login page
├── register.php           # Registration page
├── logout.php             # Logout handler
├── events.php             # Browse events
├── event-details.php      # Event details & booking
├── admin-dashboard.php    # Admin control panel
├── organizer-dashboard.php # Organizer panel
├── setup-database.php     # Database initialization (run once)
├── includes/
│   ├── config.php         # Database configuration
│   ├── header.php         # Page header (navigation)
│   └── footer.php         # Page footer
├── css/
│   └── style.css          # Main stylesheet
├── js/
│   ├── script.js          # Main JavaScript
│   └── dark-mode.js       # Dark mode toggle
└── database.sql           # Database schema (reference)
```

---

## Next Steps

1. ✅ Run `setup-database.php` to initialize the database
2. ✅ Log in with one of the test accounts above
3. ✅ Explore the different dashboards based on your role
4. ✅ Try the dark mode toggle in the top right
5. ✅ Create new test accounts via registration page

---

## Support

If you encounter any issues:

1. Check the error messages carefully
2. Review the TROUBLESHOOTING section above
3. Ensure all files are in place and readable
4. Verify MySQL is running and accessible

Enjoy the Event Ethiopia application!
