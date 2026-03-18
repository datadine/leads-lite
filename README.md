# MicroSIP Auto Dialer

A PHP-based auto dialer system with FusionPBX integration for call analytics and lead management.

## Features

- 📞 Auto dialer with lead management
- 📊 FusionPBX call analytics integration
- 👥 User management with role-based access
- 📈 Call reports and performance tracking
- ⏰ Work hours compliance monitoring
- 📱 Callback scheduling

## Installation

1. Clone the repository:
```bash
git clone https://github.com/datadine/microsip-auto-dialer.git
cd microsip-auto-dialer
```

2. Set up database credentials:
```bash
cp db.php.example db.php
cp fusionpbx/fusionpbx_config.php.example fusionpbx/fusionpbx_config.php
```

3. Edit the config files with your database credentials:
```bash
nano db.php
nano fusionpbx/fusionpbx_config.php
```

4. Import the database schema:
```bash
psql -U your_user -d dialerdb < fusionpbx/fusionpbx_schema.sql
```

5. Set up the cron job for FusionPBX sync:
```bash
crontab -e
# Add this line:
*/5 * * * * /usr/bin/php /path/to/fusionpbx/sync_fusionpbx_calls.php >> /var/log/fusionpbx_sync.log 2>&1
```

## Requirements

- PHP 8.1+
- PostgreSQL
- FusionPBX (for call analytics)
- Nginx/Apache

## License

MIT License
