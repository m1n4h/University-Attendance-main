# IFM University Attendance System

A modern QR Code-based attendance management system for universities, built with PHP and MySQL.

## Developer Information

| Field | Details |
|-------|---------|
| Developer | Benny Tech Design |
| Email | bennytechdesign@gmail.com |
| Phone | +255 690 388 447 |
| Location | SUA, Mazimbu Morogoro, Tanzania |

---

## Table of Contents

1. [System Overview](#system-overview)
2. [Features](#features)
3. [System Requirements](#system-requirements)
4. [Installation Guide](#installation-guide)
5. [Database Setup](#database-setup)
6. [User Roles](#user-roles)
7. [System Flow](#system-flow)
8. [QR Code Attendance Flow](#qr-code-attendance-flow)
9. [Testing Instructions](#testing-instructions)
10. [Admin PDF Reports](#admin-pdf-reports)
11. [Troubleshooting](#troubleshooting)

---

## System Overview

The IFM University Attendance System is a web-based application that enables:
- Teachers to take attendance using QR codes
- Students to sign in by scanning QR codes (must be physically present in class)
- Administrators to manage users, classes, subjects, and generate reports

The system uses unique QR codes per lecture that expire after 30 minutes, ensuring students must be physically present to mark attendance.

---

## Features

### Admin Panel
- Manage Students (Add, Edit, Delete, Bulk Upload via CSV)
- Manage Teachers
- Manage Classes and Subjects
- Assign Teachers to Subjects and Classes
- Assign Students to Subjects
- Generate Attendance Reports (PDF/CSV Export)
- Dashboard with Statistics

### Teacher Panel
- View Assigned Lectures/Timetable
- Start Attendance (Generates Unique QR Code)
- View Real-time Attendance Status
- Finalize Attendance (Mark Absent after lecture ends)
- Export Attendance Records (CSV)
- View Attendance History

### Student Panel
- View Enrolled Subjects and Lectures
- Scan QR Code to Sign In (Camera-based)
- View Personal Attendance History
- Export Attendance Report (CSV)
- Dashboard with Attendance Statistics

---

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache Web Server (XAMPP recommended)
- Modern Web Browser (Chrome, Firefox, Edge)
- Camera-enabled device for QR scanning

---

## Installation Guide

### Step 1: Install XAMPP
Download and install XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)

### Step 2: Clone/Copy Project
Copy the project folder to your XAMPP htdocs directory:
```
C:\xampp\htdocs\University-Attendance-main\
```

### Step 3: Start Services
1. Open XAMPP Control Panel
2. Start Apache
3. Start MySQL

### Step 4: Access the System
Open your browser and navigate to:
```
http://localhost/University-Attendance-main/
```

---

## Database Setup

### Step 1: Create Database
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Create a new database named: `IFM_AS`

### Step 2: Import Schema
1. Click on the `IFM_AS` database
2. Go to "Import" tab
3. Select the file: `sql/ifm.sql`
4. Click "Go" to import

### Database Configuration
The database connection is configured in `config/dbcon.php`:
```php
define('DB_SERVER', '127.0.0.1');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'IFM_AS');
```

### Key Database Tables

| Table | Description |
|-------|-------------|
| `tblstudent` | Student records |
| `tblteacher` | Teacher records |
| `tbladmin` | Admin records |
| `tblclass` | Class/Year information |
| `tblsubject` | Subject details |
| `tblteacher_subject_class` | Lecture assignments (Teacher + Subject + Class + Schedule) |
| `tblstudent_subject` | Student subject enrollment |
| `tblattendance` | Master attendance records (per lecture session) |
| `tblattendance_record` | Individual student attendance records |

---

## User Roles

### 1. Administrator
- Full system access
- Manages all users, classes, and subjects
- Generates reports
- Default login: Check database for admin credentials

### 2. Teacher
- Manages attendance for assigned classes
- Generates QR codes for lectures
- Views and exports attendance data
- Login: Email + Password

### 3. Student
- Views enrolled subjects
- Scans QR code to mark attendance
- Views personal attendance history
- Login: Admission Number + Password

---

## System Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                        ADMIN SETUP                               │
├─────────────────────────────────────────────────────────────────┤
│  1. Admin creates Classes (e.g., "Computer Science Year 1")     │
│  2. Admin creates Subjects (e.g., "Database Systems")           │
│  3. Admin registers Teachers                                     │
│  4. Admin registers Students (assigns to Class)                  │
│  5. Admin assigns Teachers to Subjects + Classes (Timetable)    │
│  6. Admin assigns Students to Subjects they're enrolled in      │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                     TEACHER WORKFLOW                             │
├─────────────────────────────────────────────────────────────────┤
│  1. Teacher logs in                                              │
│  2. Views Dashboard with assigned lectures                       │
│  3. Clicks "Start Attendance" for a lecture                     │
│  4. System generates UNIQUE QR Code (valid 30 minutes)          │
│  5. Teacher displays QR code to class                           │
│  6. Teacher monitors real-time sign-ins                         │
│  7. After lecture ends, clicks "Finalize (Mark Absent)"         │
│  8. Students who didn't sign in are marked ABSENT               │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                     STUDENT WORKFLOW                             │
├─────────────────────────────────────────────────────────────────┤
│  1. Student logs in                                              │
│  2. Views Dashboard with enrolled subjects only                  │
│  3. Sees today's lectures with status                           │
│  4. Clicks "Scan QR" button when in class                       │
│  5. Camera opens, student scans teacher's QR code               │
│  6. System validates:                                            │
│     - Is QR code valid and not expired?                         │
│     - Is student enrolled in this subject?                      │
│     - Is it within the sign-in window?                          │
│  7. If valid: Marks PRESENT or LATE based on time               │
│  8. Student sees confirmation                                    │
└─────────────────────────────────────────────────────────────────┘
```

---

## QR Code Attendance Flow

### Sign-in Window Rules

| Time Period | Status |
|-------------|--------|
| 15 minutes before lecture starts | Window Opens |
| Within 15 minutes of start time | PRESENT |
| After 15 minutes but before lecture ends | LATE |
| After lecture end time | WINDOW CLOSED |

### Attendance Status Meanings

| Status | Color | Meaning |
|--------|-------|---------|
| Present | Green | Student signed in on time |
| Late | Orange | Student signed in late (after 15 min grace period) |
| Absent | Red | Teacher started attendance, student didn't sign in |
| Missed | Grey | Teacher never started attendance (not student's fault) |

### QR Code Security
- Each QR code is UNIQUE per lecture session
- QR codes expire after 30 minutes
- Students MUST physically scan the QR code (no manual sign-in)
- QR code contains encrypted token validated server-side

---

## Testing Instructions

### Test Admin Functions
1. Login as Admin
2. Create a new Class (e.g., "Test Class Year 1")
3. Create a new Subject (e.g., "Test Subject")
4. Create a Teacher account
5. Create a Student account (assign to the class)
6. Assign Teacher to Subject + Class with schedule
7. Assign Student to the Subject

### Test Teacher Functions
1. Login as Teacher
2. Go to Dashboard - see assigned lectures
3. Click "Start Attendance" on a lecture
4. QR code should appear
5. Open student login on another device/browser
6. Have student scan the QR code
7. Verify attendance appears in real-time
8. After lecture time ends, click "Finalize (Mark Absent)"

### Test Student Functions
1. Login as Student
2. Dashboard shows only enrolled subjects
3. Click "Scan QR" on an active lecture
4. Camera opens - scan teacher's QR code
5. Verify "Present" or "Late" status
6. Check "My Attendance" page for history
7. Test CSV export

### Test Remote Access
1. Start ngrok (see Remote Access section)
2. Share ngrok URL with test users
3. Test QR scanning from mobile devices

---

## Admin PDF Reports

### How to Generate Reports

1. Login as Admin
2. Navigate to "Attendance Reports" in sidebar
3. Select filters:
   - Class (required)
   - Start Date
   - End Date
4. Click "Generate"
5. View report in table format
6. Click "Export to PDF" button

### Report Contents
- Student Name
- Admission Number
- Subject Code
- Total Classes
- Present Count
- Absent Count
- Late Count
- Attendance Percentage

### PDF Features
- Professional formatting
- Date range header
- Color-coded statistics
- Downloadable file

---

## Troubleshooting

### Common Issues

**Problem: Page not loading**
- Ensure XAMPP Apache is running
- Check URL is correct: `http://localhost/University-Attendance-main/`

**Problem: Database connection error**
- Ensure MySQL is running in XAMPP
- Verify database `IFM_AS` exists
- Check credentials in `config/dbcon.php`

**Problem: QR code not scanning**
- Ensure camera permissions are granted
- Use HTTPS (ngrok) for mobile cameras
- Check QR code hasn't expired (30 min limit)

**Problem: Student can't see lectures**
- Verify student is assigned to the subject in Admin panel
- Check `tblstudent_subject` table

**Problem: "Window Closed" message**
- Lecture end time has passed
- Student must sign in before lecture ends

**Problem: Changes not appearing**
- Hard refresh browser: `Ctrl + Shift + R`
- Clear browser cache

### Browser Cache
If you see old content after updates:
```
Press Ctrl + Shift + R (Hard Refresh)
```

---

## File Structure

```
University-Attendance-main/
├── admin/                  # Admin panel pages
│   ├── dashboard.php
│   ├── manage-students.php
│   ├── manage-teachers.php
│   ├── manage-classes.php
│   ├── manage-subjects.php
│   ├── assign-class.php
│   ├── assign-student-subject.php
│   ├── report-attendance.php
│   └── student-upload.php
├── teacher/                # Teacher panel pages
│   ├── dashboard.php
│   ├── take-attendance.php
│   ├── view-attendance.php
│   ├── student-list.php
│   └── attendance-handler.php
├── student/                # Student panel pages
│   ├── dashboard.php
│   ├── sign-in.php
│   └── my-attendance.php
├── public/                 # Public pages & assets
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   └── images/
│   ├── index.php          # Login page
│   ├── logout.php
│   ├── profile.php
│   └── change-password.php
├── includes/               # Shared components
│   ├── header.php
│   ├── footer.php
│   ├── navbar.php
│   ├── sidebar.php
│   └── auth_check.php
├── config/                 # Configuration files
│   ├── config.php         # App settings, CSRF, timezone
│   └── dbcon.php          # Database connection
├── sql/                    # Database files
│   └── ifm.sql            # Main database schema
├── cron/                   # Scheduled tasks
│   └── auto_mark_absent.php
├── index.php              # Root redirect
├── .htaccess              # Apache config
└── README.md              # This file
```

---

## Technologies Used

- PHP 7.4+
- MySQL 5.7+
- Bootstrap 5
- jQuery
- Font Awesome Icons
- jsPDF (PDF generation)
- html5-qrcode (QR scanning)
- DataTables (Table management)

---

## Security Features

- CSRF Token Protection
- Password Hashing (bcrypt)
- Prepared SQL Statements (PDO)
- Session Security (httponly, secure cookies)
- Input Sanitization
- Role-based Access Control

---

## Timezone

System timezone: `Africa/Dar_es_Salaam` (East Africa Time, UTC+3)

---

## License

This project is developed by Benny Tech Design for educational purposes.

---

## Contact & Support

For support, customization, or inquiries:

- Email: bennytechdesign@gmail.com
- Phone: +255 690 388 447
- Location: SUA, Mazimbu Morogoro, Tanzania

---

*Last Updated: December 2025*
