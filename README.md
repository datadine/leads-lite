# 📞 Leads Lite - Auto-Dialer System

A web-based lead management and auto-dialing system that integrates with MicroSIP for efficient cold calling campaigns. Built for sales teams to streamline their outbound calling workflow.

## 🎯 Features

### Core Functionality
- **Automated Dialing**: Upload leads via CSV and auto-dial through MicroSIP integration
- **Campaign Management**: Create and manage multiple calling campaigns
- **Real-time Call Tracking**: Track call outcomes (Interested, Not Interested, No Answer, Callback)
- **Lead Status Management**: Comprehensive lead lifecycle tracking
- **Session Management**: Active dial session monitoring with heartbeat system

### Lead Organization
- **Interested Leads**: Dedicated view for qualified prospects with follow-up scheduling
- **Callback Queue**: Manage leads requiring follow-up calls with date/time scheduling
- **Task Management**: Track follow-ups and action items
- **Notes & Chat**: Add detailed notes and conversation history for each lead

### User Management
- **Multi-User Support**: Role-based access (Admin/User)
- **Permission System**: Granular permissions for upload and delete operations
- **User Assignment**: Assign specific campaigns to team members
- **Performance Tracking**: Monitor individual user calling statistics

### Reporting & Analytics
- **Performance Dashboard**: Track calls made, conversion rates, and time spent
- **Call History**: Complete audit trail of all dialing activity
- **CSV Export**: Download campaign results for external analysis
- **Session Reports**: View active and historical dial sessions

## 🚀 Tech Stack

- **Backend**: PHP 8+ with PostgreSQL
- **Frontend**: Vanilla JavaScript, HTML5, CSS3
- **VoIP Integration**: MicroSIP (via `callto:` protocol)
- **Database**: PostgreSQL with proper indexing and constraints
- **Session Management**: PHP sessions with secure cookie parameters

## 📋 Prerequisites

- PHP 8.0 or higher
- PostgreSQL 12+
- Web server (Apache/Nginx)
- MicroSIP installed on client machines
- Modern web browser

## 🛠️ Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/leads-lite.git
   cd leads-lite
   ```

2. **Database Setup**
   ```bash
   # Create PostgreSQL database
   createdb dialerdb
   
   # Run schema creation (see database/schema.sql)
   psql -U postgres -d dialerdb -f database/schema.sql
   ```

3. **Configure Database Connection**
   ```bash
   # Copy example config
   cp db.example.php db.php
   
   # Edit db.php with your credentials
   nano db.php
   ```

4. **Set Permissions**
   ```bash
   chmod 644 *.php
   chmod 600 db.php  # Protect credentials
   ```

5. **Create Admin User**
   ```sql
   INSERT INTO users (username, password_hash, role, can_upload, can_delete, active)
   VALUES ('admin', '$2y$10$YourHashedPasswordHere', 'admin', TRUE, TRUE, TRUE);
   ```

## 📊 Database Schema

The system uses PostgreSQL with the following main tables:

- `users` - User accounts and permissions
- `campaigns` - Calling campaigns
- `campaign_users` - User-to-campaign assignments
- `leads` - Lead contact information and status
- `call_logs` - Complete call history
- `interested_notes` - Qualified lead tracking
- `callback_notes` - Callback scheduling
- `dial_sessions` - Active calling session management
- `tasks` - Follow-up task management

## 🔧 Configuration

### Database Configuration
Edit `db.php` with your PostgreSQL credentials:

```php
$DB_HOST = '127.0.0.1';
$DB_PORT = '5432';
$DB_NAME = 'dialerdb';
$DB_USER = 'your_username';
$DB_PASS = 'your_password';
```

### MicroSIP Integration
The system uses the `callto:` protocol to trigger MicroSIP dialing. Ensure MicroSIP is:
- Installed on all client machines
- Configured as the default handler for `callto:` URIs
- Connected to your SIP/VoIP provider

## 📖 Usage

### For Admins

1. **Create a Campaign**
   - Navigate to Campaigns → New Campaign
   - Upload CSV file (Format: Business Name, Phone Number)
   - Assign users to the campaign

2. **Manage Users**
   - Admin Panel → Users tab
   - Create users with appropriate permissions
   - Assign campaigns to specific users

### For Users

1. **Start Dialing**
   - Select a campaign
   - Click "Start Dialing"
   - System automatically dials through MicroSIP

2. **Mark Call Outcomes**
   - After each call, select: Interested, Not Interested, No Answer, or Callback
   - Add notes if needed
   - System auto-advances to next lead

3. **Manage Follow-ups**
   - "Interested" tab: Track qualified leads
   - "Call Back" tab: Schedule and manage callbacks
   - Add detailed notes and set follow-up dates

## 📁 File Structure

```
leads-lite/
├── index.php           # Main campaign/dialer interface
├── login.php           # User authentication
├── admin.php           # Admin dashboard
├── interested.php      # Interested leads management
├── callback.php        # Callback queue
├── tasks.php           # Task management
├── myperf.php          # Performance tracking
├── auth.php            # Session/permission helpers
├── db.php              # Database connection (excluded from repo)
├── db.example.php      # Example database config
├── nav.php             # Shared navigation component
├── heartbeat.php       # Session keepalive endpoint
├── download.php        # CSV export handler
├── logout.php          # Logout handler
└── jssip_min.js        # VoIP library (optional)
```

## 🔐 Security Features

- Password hashing with `bcrypt`
- Session security with HTTP-only cookies
- CSRF protection on state-changing operations
- SQL injection prevention via prepared statements
- Role-based access control
- Automatic session timeout (5 minutes inactivity)

## 📈 Performance Optimizations

- PostgreSQL row-level locking for concurrent dialing
- Efficient indexing on frequently queried columns
- Automatic cleanup of stale sessions
- Optimized queries with proper JOINs
- Session heartbeat to prevent timeout during active calls

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📝 License

This project is open source and available under the [MIT License](LICENSE).

## 🐛 Known Issues / TODO

- [ ] Add bulk SMS capability
- [ ] Implement email integration for follow-ups
- [ ] Add advanced reporting/analytics dashboard
- [ ] Mobile-responsive UI improvements
- [ ] WebRTC integration (alternative to MicroSIP)

## 📞 Support

For issues, questions, or contributions, please open an issue on GitHub.

## 🙏 Acknowledgments

Built for sales teams who need efficient cold-calling workflows. Designed to maximize productivity and lead conversion rates.

---

**Note**: This system requires MicroSIP to be installed and configured on client machines for the auto-dialing functionality to work properly.
