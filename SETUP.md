# 🚀 Setup Guide - Leads Lite

Complete step-by-step guide to get Leads Lite running on your server.

## Table of Contents
- [System Requirements](#system-requirements)
- [Database Setup](#database-setup)
- [Application Setup](#application-setup)
- [MicroSIP Configuration](#microsip-configuration)
- [Creating Your First Admin User](#creating-your-first-admin-user)
- [Troubleshooting](#troubleshooting)

---

## System Requirements

### Server Requirements
- **OS**: Linux (Ubuntu 20.04+ recommended) or Windows Server
- **PHP**: 8.0 or higher with extensions:
  - `pdo_pgsql`
  - `pgsql`
  - `session`
  - `mbstring`
- **PostgreSQL**: 12.0 or higher
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **RAM**: Minimum 512MB (1GB+ recommended)
- **Storage**: 5GB+ for application and logs

### Client Requirements
- **Browser**: Chrome 90+, Firefox 88+, Edge 90+, or Safari 14+
- **MicroSIP**: Latest version installed and configured
- **Operating System**: Windows 7+ (for MicroSIP compatibility)

---

## Database Setup

### 1. Install PostgreSQL

**Ubuntu/Debian:**
```bash
sudo apt update
sudo apt install postgresql postgresql-contrib
```

**CentOS/RHEL:**
```bash
sudo yum install postgresql-server postgresql-contrib
sudo postgresql-setup initdb
sudo systemctl start postgresql
sudo systemctl enable postgresql
```

### 2. Create Database and User

```bash
# Switch to postgres user
sudo -u postgres psql

# Inside PostgreSQL prompt:
CREATE DATABASE dialerdb;
CREATE USER dialeruser WITH ENCRYPTED PASSWORD 'YourSecurePassword123!';
GRANT ALL PRIVILEGES ON DATABASE dialerdb TO dialeruser;
\q
```

### 3. Create Database Schema

Create a file `schema.sql` with the following content:

```sql
-- Users table
CREATE TABLE IF NOT EXISTS public.users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'user' CHECK (role IN ('admin', 'user')),
    can_upload BOOLEAN DEFAULT FALSE,
    can_delete BOOLEAN DEFAULT FALSE,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT (NOW() AT TIME ZONE 'America/New_York')
);

-- Campaigns table
CREATE TABLE IF NOT EXISTS public.campaigns (
    id SERIAL PRIMARY KEY,
    campaign_code VARCHAR(20) UNIQUE DEFAULT ('C-' || LPAD(nextval('campaigns_id_seq')::TEXT, 6, '0')),
    name VARCHAR(255) NOT NULL,
    created_by INTEGER REFERENCES public.users(id),
    created_at TIMESTAMP DEFAULT (NOW() AT TIME ZONE 'America/New_York'),
    deleted BOOLEAN DEFAULT FALSE,
    deleted_by INTEGER REFERENCES public.users(id),
    deleted_at TIMESTAMP
);

-- Campaign-User assignments
CREATE TABLE IF NOT EXISTS public.campaign_users (
    campaign_id INTEGER REFERENCES public.campaigns(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES public.users(id) ON DELETE CASCADE,
    assigned_at TIMESTAMP DEFAULT (NOW() AT TIME ZONE 'America/New_York'),
    PRIMARY KEY (campaign_id, user_id)
);

-- Leads table
CREATE TABLE IF NOT EXISTS public.leads (
    id SERIAL PRIMARY KEY,
    campaign_id INTEGER REFERENCES public.campaigns(id) ON DELETE CASCADE,
    business_name VARCHAR(255),
    phone VARCHAR(20) NOT NULL,
    status VARCHAR(30) DEFAULT 'new' CHECK (status IN ('new', 'calling', 'called', 'interested', 'not_interested', 'no_answer', 'callback')),
    attempts INTEGER DEFAULT 0,
    last_result VARCHAR(30),
    last_call_time TIMESTAMP,
    created_at TIMESTAMP DEFAULT (NOW() AT TIME ZONE 'America/New_York'),
    deleted_at TIMESTAMP,
    UNIQUE(campaign_id, phone)
);

-- Call logs
CREATE TABLE IF NOT EXISTS public.call_logs (
    id SERIAL PRIMARY KEY,
    lead_id INTEGER REFERENCES public.leads(id) ON DELETE CASCADE,
    campaign_id INTEGER REFERENCES public.campaigns(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES public.users(id),
    outcome VARCHAR(30),
    created_at TIMESTAMP DEFAULT (NOW() AT TIME ZONE 'America/New_York')
);

-- Interested notes
CREATE TABLE IF NOT EXISTS public.interested_notes (
    id SERIAL PRIMARY KEY,
    lead_id INTEGER REFERENCES public.leads(id) ON DELETE CASCADE UNIQUE,
    campaign_id INTEGER REFERENCES public.campaigns(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES public.users(id),
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'paused', 'completed', 'closed')),
    notes TEXT,
    followup_method VARCHAR(20),
    followup_days INTEGER,
    followup_date DATE,
    notes_updated_by VARCHAR(100),
    notes_updated_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT (NOW() AT TIME ZONE 'America/New_York'),
    updated_at TIMESTAMP DEFAULT (NOW() AT TIME ZONE 'America/New_York')
);

-- Callback notes
CREATE TABLE IF NOT EXISTS public.callback_notes (
    id SERIAL PRIMARY KEY,
    lead_id INTEGER REFERENCES public.leads(id) ON DELETE CASCADE UNIQUE,
    campaign_id INTEGER REFERENCES public.campaigns(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES public.users(id),
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'paused', 'completed', 'closed')),
    callback_date DATE,
    callback_time TIME,
    notes TEXT,
    notes_updated_by VARCHAR(100),
    notes_updated_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT (NOW() AT TIME ZONE 'America/New_York'),
    updated_at TIMESTAMP DEFAULT (NOW() AT TIME ZONE 'America/New_York')
);

-- Dial sessions
CREATE TABLE IF NOT EXISTS public.dial_sessions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES public.users(id),
    campaign_id INTEGER REFERENCES public.campaigns(id),
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'closed')),
    started_at TIMESTAMP DEFAULT (NOW() AT TIME ZONE 'America/New_York'),
    last_ping TIMESTAMP DEFAULT (NOW() AT TIME ZONE 'America/New_York'),
    ended_at TIMESTAMP,
    total_seconds INTEGER DEFAULT 0
);

-- Tasks table
CREATE TABLE IF NOT EXISTS public.tasks (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES public.users(id),
    lead_id INTEGER REFERENCES public.leads(id) ON DELETE CASCADE,
    campaign_id INTEGER REFERENCES public.campaigns(id) ON DELETE CASCADE,
    task_type VARCHAR(50),
    title VARCHAR(255),
    description TEXT,
    due_date DATE,
    due_time TIME,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'completed', 'cancelled')),
    created_at TIMESTAMP DEFAULT (NOW() AT TIME ZONE 'America/New_York'),
    completed_at TIMESTAMP
);

-- Lead views (for tracking which leads have been viewed)
CREATE TABLE IF NOT EXISTS public.lead_views (
    user_id INTEGER REFERENCES public.users(id),
    lead_id INTEGER REFERENCES public.leads(id) ON DELETE CASCADE,
    viewed_at TIMESTAMP DEFAULT (NOW() AT TIME ZONE 'America/New_York'),
    PRIMARY KEY (user_id, lead_id)
);

-- Chat notes for leads
CREATE TABLE IF NOT EXISTS public.chat_notes (
    id SERIAL PRIMARY KEY,
    lead_id INTEGER REFERENCES public.leads(id) ON DELETE CASCADE,
    note_id INTEGER, -- Reference to interested_notes or callback_notes
    user_id INTEGER REFERENCES public.users(id),
    username VARCHAR(100),
    note_text TEXT,
    created_at TIMESTAMP DEFAULT (NOW() AT TIME ZONE 'America/New_York')
);

-- Create indexes for better performance
CREATE INDEX idx_leads_campaign_status ON public.leads(campaign_id, status);
CREATE INDEX idx_leads_phone ON public.leads(phone);
CREATE INDEX idx_call_logs_lead ON public.call_logs(lead_id);
CREATE INDEX idx_call_logs_user ON public.call_logs(user_id);
CREATE INDEX idx_interested_status ON public.interested_notes(status);
CREATE INDEX idx_callback_status ON public.callback_notes(status);
CREATE INDEX idx_dial_sessions_user ON public.dial_sessions(user_id);
CREATE INDEX idx_tasks_user ON public.tasks(user_id);
```

Run the schema file:
```bash
psql -U dialeruser -d dialerdb -f schema.sql
```

---

## Application Setup

### 1. Clone or Download the Repository

```bash
cd /var/www/
git clone https://github.com/yourusername/leads-lite.git
cd leads-lite
```

### 2. Configure Database Connection

```bash
# Copy example config
cp db.example.php db.php

# Edit with your credentials
nano db.php
```

Update the values:
```php
$DB_HOST = '127.0.0.1';
$DB_PORT = '5432';
$DB_NAME = 'dialerdb';
$DB_USER = 'dialeruser';
$DB_PASS = 'YourSecurePassword123!';
```

### 3. Set File Permissions

```bash
# Secure the database config
chmod 600 db.php

# Set appropriate permissions
chmod 644 *.php
chmod 755 .
```

### 4. Configure Web Server

**Apache (.htaccess):**
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /leads/
    
    # Protect sensitive files
    <FilesMatch "^(db\.php|\.git)">
        Require all denied
    </FilesMatch>
</IfModule>

# Prevent directory listing
Options -Indexes

# PHP settings
php_value upload_max_filesize 10M
php_value post_max_size 10M
php_value max_execution_time 300
```

**Nginx:**
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/leads-lite;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Deny access to sensitive files
    location ~ /(db\.php|\.git) {
        deny all;
        return 404;
    }
}
```

---

## MicroSIP Configuration

### 1. Install MicroSIP
Download from [microsip.org](https://www.microsip.org/) and install on all client machines.

### 2. Configure SIP Account
1. Open MicroSIP
2. Go to Settings → Accounts
3. Add your SIP/VoIP provider details:
   - SIP Server
   - Username
   - Password
   - Domain

### 3. Set as Default for `callto:` Protocol
Windows should automatically associate `callto:` URIs with MicroSIP. If not:
1. Open Default Programs in Windows
2. Associate `callto:` protocol with MicroSIP

---

## Creating Your First Admin User

### Option 1: Via psql (Recommended)

```bash
# Generate password hash in PHP
php -r "echo password_hash('YourAdminPassword', PASSWORD_BCRYPT) . \"\n\";"

# Use the output in this SQL command
sudo -u postgres psql dialerdb

INSERT INTO public.users (username, password_hash, role, can_upload, can_delete, active)
VALUES ('admin', '$2y$10$HASH_FROM_ABOVE', 'admin', TRUE, TRUE, TRUE);
```

### Option 2: Via SQL Script

Create `create_admin.sql`:
```sql
-- Replace the password hash with your generated hash
INSERT INTO public.users (username, password_hash, role, can_upload, can_delete, active)
VALUES ('admin', '$2y$10$YourHashedPasswordHere', 'admin', TRUE, TRUE, TRUE);
```

Run it:
```bash
psql -U dialeruser -d dialerdb -f create_admin.sql
```

---

## Troubleshooting

### Database Connection Issues

**Error: "could not connect to server"**
```bash
# Check PostgreSQL is running
sudo systemctl status postgresql

# Check pg_hba.conf allows local connections
sudo nano /etc/postgresql/*/main/pg_hba.conf
# Ensure this line exists:
# local   all             all                                     md5
```

**Error: "FATAL: password authentication failed"**
- Verify credentials in `db.php`
- Reset user password: `ALTER USER dialeruser WITH PASSWORD 'newpassword';`

### PHP Extension Issues

**Error: "could not find driver"**
```bash
# Install PostgreSQL PDO extension
sudo apt install php-pgsql
sudo systemctl restart apache2  # or php-fpm
```

### Permission Issues

**Error: "Permission denied"**
```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/leads-lite

# Fix permissions
sudo chmod 755 /var/www/leads-lite
sudo chmod 644 /var/www/leads-lite/*.php
sudo chmod 600 /var/www/leads-lite/db.php
```

### MicroSIP Not Opening

1. Verify MicroSIP is running
2. Check Windows default programs for `callto:` protocol
3. Test with: `<a href="callto:1234567890">Test</a>` in browser
4. Reinstall MicroSIP if necessary

### Session Timeout Issues

Edit `auth.php` to increase timeout:
```php
session_set_cookie_params([
    'lifetime' => 28800, // 8 hours instead of 0
    // ... rest of config
]);
```

---

## Next Steps

1. **Log in** at `http://yourserver/leads/login.php`
2. **Create users** via Admin Panel → Users
3. **Upload your first campaign** via Campaigns → New Campaign
4. **Assign users** to campaigns
5. **Start dialing!**

For additional help, check the [main README](README.md) or open an issue on GitHub.
