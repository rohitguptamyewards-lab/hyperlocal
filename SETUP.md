# Hyperlocal Network — New PC Setup Guide

## Prerequisites

Install these on the new PC before starting:

| Tool | Version | Download |
|------|---------|----------|
| PHP | 8.2+ | https://php.net/downloads |
| Composer | 2.x | https://getcomposer.org |
| Node.js | 20+ | https://nodejs.org |
| Git | any | https://git-scm.com |

---

## 1. Clone the repository

```bash
git clone https://github.com/rohitguptamyewards-lab/hyperlocal.git
cd hyperlocal
```

---

## 2. Backend setup

```bash
cd src/backend

# Install PHP dependencies
composer install

# The .env file is already committed — no need to copy .env.example
# The SQLite database with all data is also committed at:
#   src/backend/database/database.sqlite

# Clear caches
php artisan config:clear
php artisan cache:clear

# Start the dev server (port 8000)
php artisan serve --port=8000
```

> The database already contains all brands, partnerships, and data from the original machine.

---

## 3. Frontend setup

```bash
cd src/frontend

# Install JS dependencies
npm install

# Start the dev server (port 3000)
npm run dev
```

---

## 4. Access the app

| URL | Purpose |
|-----|---------|
| http://localhost:3000 | Main merchant dashboard |
| http://localhost:8000 | Backend API |

---

## Useful artisan commands

```bash
# Run any missing migrations (if DB schema changes)
php artisan migrate

# Re-seed demo data (WARNING: this clears existing data)
php artisan db:seed

# Clear all Laravel caches
php artisan optimize:clear
```

---

## Architecture overview

```
hyperlocal/
├── src/
│   ├── backend/          ← Laravel 13 API (PHP)
│   │   ├── app/Modules/  ← Feature modules (Partnership, Discovery, etc.)
│   │   ├── database/
│   │   │   └── database.sqlite  ← All data lives here
│   │   └── routes/api.php
│   └── frontend/         ← Vue 3 + Vite + TypeScript
│       └── src/
│           ├── modules/  ← Feature views per module
│           ├── stores/   ← Pinia state stores
│           └── services/ ← API client
```
