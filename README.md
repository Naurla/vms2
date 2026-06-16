# 🏠 Care Home VMS — Visitor Management System for Home for the Aged

A full-featured web application for managing visitors to a care home, built with **PHP (vanilla)**, **JavaScript**, **HTML/CSS**, and **MySQL**.

---

## 🚀 Quick Start (XAMPP / WAMP)

1. **Copy the project** to your web server root:
   - XAMPP: `C:\xampp\htdocs\vms2\`
   - WAMP: `C:\wamp64\www\vms2\`

2. **Start Apache and MySQL** in XAMPP/WAMP.

3. **Run the installer** — open your browser and go to:
   ```
   http://localhost/vms2/setup.php
   ```
   Fill in your MySQL credentials (default: host=`localhost`, user=`root`, password=empty) and click **Install Now**.

4. **Log in** at:
   ```
   http://localhost/vms2/
   ```

---

## 🔑 Default Credentials

| Role | Username | Password |
|------|----------|----------|
| Administrator | `admin` | `Admin@123` |
| Receptionist  | `receptionist` | `Staff@123` |

> ⚠️ **Change these passwords immediately** after first login via Users → Edit.

---

## 📁 Project Structure

```
vms2/
├── index.php              ← Login page
├── dashboard.php          ← Main dashboard
├── setup.php              ← One-time installer (delete after setup!)
├── logout.php
│
├── visitors/
│   ├── register.php       ← Register new visitor
│   ├── checkin.php        ← Check in a visitor
│   ├── checkout.php       ← Check out visitors
│   └── list.php           ← Full visit log with filters
│
├── residents/
│   ├── list.php           ← Residents directory
│   ├── add.php            ← Add new resident
│   ├── edit.php           ← Edit resident details
│   └── view.php           ← Resident profile & visit history
│
├── reports/
│   └── index.php          ← Analytics, charts, date-range reports
│
├── users/
│   ├── list.php           ← Manage staff accounts (admin only)
│   ├── add.php            ← Add new user
│   └── edit.php           ← Edit user / change password
│
├── api/
│   ├── search_visitors.php    ← AJAX visitor autocomplete
│   ├── search_residents.php   ← AJAX resident autocomplete
│   └── dashboard_stats.php    ← Live dashboard stats
│
├── includes/
│   ├── config.php         ← DB credentials & app settings
│   ├── db.php             ← PDO connection
│   ├── auth.php           ← Session, auth guards, CSRF
│   ├── functions.php      ← Utility helpers
│   ├── header.php         ← Shared sidebar & top bar
│   └── footer.php         ← Shared footer & JS
│
├── assets/
│   ├── css/style.css      ← Full stylesheet
│   └── js/main.js         ← Autocomplete, toasts, clock, etc.
│
└── database/
    └── vms_home_aged.sql  ← Schema (for manual import)
```

---

## ✨ Features

- **Dashboard** — live stats (today's visitors, checked-in count, residents, monthly visits)
- **Check In** — autocomplete visitor search, resident selector, purpose/relationship tracking
- **Check Out** — one-click checkout with automatic duration calculation
- **Visitor Registration** — full profile with ID type/number validation
- **Resident Management** — profiles with emergency contacts and medical notes
- **Visit History** — per-resident visit timeline
- **Reports** — Chart.js bar chart, most-visited residents, purpose breakdown, date-range filtering
- **User Management** — admin creates/manages staff accounts
- **Role-Based Access** — Admin vs Receptionist
- **Security** — CSRF protection, PDO prepared statements, bcrypt passwords

---

## ⚙️ Configuration

Edit `includes/config.php` to change:
- Database credentials
- Timezone (default: `Asia/Manila`)
- App name

---

## 🗑️ After Setup

Delete or password-protect `setup.php` after the initial installation:
```
del C:\xampp\htdocs\vms2\setup.php
```
