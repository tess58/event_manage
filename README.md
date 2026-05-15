# Ethiopian Event Management System

A full-stack event management website built with HTML5, CSS3, JavaScript, PHP, and MySQL. The system supports role-based access control for Admins, Event Organizers, and Attendees.

## Features

- Responsive modern UI with event cards, filters, and dashboards
- Role-based authentication with Admin, Organizer, and User accounts
- Organizer approval workflow for pending organizers
- Event creation, management, and deletion for organizers
- Event browsing with dynamic search and filter via AJAX
- Ticket booking with unique QR code generation
- Review submission for booked events
- Secure login and registration using PHP sessions
- Password hashing and prepared statements for safety
- Dark mode toggle available in the navigation bar

## Project Structure

- `index.php` — homepage with featured events
- `events.php` — all events listing with dynamic filtering
- `event-details.php` — event details, booking, QR ticket, reviews
- `login.php` / `register.php` — authentication pages
- `dashboard.php` — role-based dashboard for Admin, Organizer, User
- `logout.php` — sign out and destroy session
- `events_ajax.php` — AJAX endpoint for live event filtering
- `includes/config.php` — database connection and helper functions
- `includes/header.php` — shared header and navigation
- `includes/footer.php` — shared footer
- `css/style.css` — design and responsive styling
- `js/script.js` — DOM validation, filters, and dark mode toggle
- `database.sql` — schema and sample data

## Setup Instructions

1. Import `database.sql` into your MySQL server. It creates the `ethiopian_events` database and sample data.
2. Update `includes/config.php` with your MySQL credentials if needed.
3. Place the project folder in a PHP-enabled web server directory (e.g. `htdocs` or `www`).
4. Open the site in your browser and use the sample accounts.

## Sample Accounts

- Admin: `admin@example.com` / `password`
- Organizer: `organizer@example.com` / `password`
- Attendee: `user@example.com` / `password`

## Dark Mode

A dark mode toggle is available in the top navigation bar. It remembers your theme choice using local storage.

## Notes

- No frameworks are used. The app is built with plain PHP and vanilla JavaScript.
- The QR code image in `event-details.php` uses an external QR code API for simple ticket display.
- You can extend the system with event editing, email notifications, or booking cancellation as needed.

## Full AI Build Prompt

Build a complete Ethiopian Event Management System using PHP, MySQL, HTML/CSS, and vanilla JavaScript. The app must support role-based access control with Admin, Event Organizer, and Attendee accounts, and should include event browsing, booking, QR ticket generation, reviews, dashboards, and dark mode.

1. Project Purpose
   Create a full-stack event management website for Ethiopian events with:

- public event discovery
- secure registration and login
- organizer event management
- admin user and organizer approval workflows
- ticket booking with QR code support
- event reviews and ratings
- responsive UI and dark mode

2. Functional Requirements

User Roles

- Admin
  - Manage users
  - Approve/reject organizers
  - View all events and bookings
  - Monitor system statistics
- Organizer
  - Create, edit, delete own events
  - View bookings for own events
  - See revenue and event performance
- Attendee / User
  - Browse events
  - Filter/search events
  - Book tickets
  - View QR ticket
  - Leave reviews and ratings

Public Pages

- index.php
  - Homepage with featured events
  - Summary of platform capabilities
- events.php
  - Event listing page
  - Live search and filtering by category/date/location
- event-details.php
  - Event detail view
  - Booking form
  - Ticket confirmation with QR code
  - Reviews display
- login.php
  - Login form
- register.php
  - Registration form with role selection

Auth and Account Management

- logout.php destroys session and signs out
- Session-based login
- Password hashing
- Input sanitization
- Role-based page access protection
- Organizer accounts may require admin approval

Dashboard Pages

- dashboard.php
  - Role-based redirect/dashboard landing page
- admin-dashboard.php
  - Admin control panel
- organizer-dashboard.php
  - Organizer event management
- user-dashboard.php
  - Attendee booking overview

Backend Support

- events_ajax.php
  - AJAX backend for live event filtering and search
- setup-database.php
  - Initialize MySQL database and insert sample data

3. Database Requirements
   Create a MySQL database named ethiopian_events containing:

- users
  - id, name, email, password, role, status, created_at
- events
  - id, organizer_id, title, description, category_id, location, date, time, price, image, seats, status, created_at
- categories
  - id, name
- bookings
  - id, user_id, event_id, qr_code or ticket_code, status, payment_status, check_in_status, ticket_number, qr_code_data, validation_token, created_at
- reviews
  - id, user_id, event_id, rating, comment, created_at
- notifications (optional)
  - id, user_id, title, message, type, is_read, created_at

Other supporting schema:

- event announcements
- booking validation tokens
- ticket types
- attendance tracking

4. File Structure
   Use this structure exactly:

- index.php
- events.php
- event-details.php
- login.php
- register.php
- logout.php
- dashboard.php
- admin-dashboard.php
- organizer-dashboard.php
- user-dashboard.php
- admin-users.php
- admin-events.php
- admin-reports.php
- admin-bookings.php
- admin-categories.php
- admin-reviews.php
- admin-settings.php
- organizer-events.php
- organizer-bookings.php
- organizer-checkin.php
- organizer-notifications.php
- organizer-reviews.php
- organizer-settings.php
- notifications-poll.php
- setup-database.php

Shared includes:

- includes/config.php
- includes/header.php
- includes/footer.php

Assets:

- css/style.css
- js/script.js
- js/dark-mode.js

5. UI / UX Requirements

General Layout

- Modern, responsive layout
- Clean event cards
- Dashboard panels for stats
- Sidebar or top nav depending on role
- Consistent header and footer across pages

Visual Design

- Ethiopia / event themed styling
- Gradient background for login/register
- Cards for events with images, title, date, location, category, price
- Buttons for booking, editing, approving, deleting
- Tables for admin and organizer listings
- Mobile-first responsive design

Navigation

- Top nav with links:
  - Home
  - Events
  - Dashboard
  - Login/Register or Logout
- Dark mode toggle in the navigation
- Breadcrumbs or page titles in dashboards

Booking UI

- Event details page shows:
  - event image
  - date/time
  - location
  - price
  - description
  - organizer info
  - reviews
- Booking form button triggers booking creation
- Booking confirmation shows QR code image
- QR code uses external API or generated payload

Dark Mode

- Toggle control in top nav
- Save preference in local storage
- Persist theme across pages

6. JavaScript Behavior

- Form validation on login/register/book event
- AJAX event filtering on events.php
- Dark mode toggle and storage persistence
- Optional dynamic UI updates for notifications/polls

7. Additional Notes

- No frameworks required; use plain PHP and vanilla JS
- Use prepared statements for database queries
- Sanitize all user input
- Provide sample accounts:
  - Admin: admin@example.com / password
  - Organizer: organizer@example.com / password
  - User: user@example.com / password
- Include a setup script for database initialization

8. Deliverables
   Generate all source files, including:

- .php pages
- includes/ shared PHP utilities
- css/style.css
- js/script.js
- js/dark-mode.js
- Setup database script
- SQL schema or auto-setup script

Use this prompt as the exact specification to build the same event management system end-to-end.
