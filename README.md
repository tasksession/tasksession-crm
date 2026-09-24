# TaskSession CRM — Free Edition

TaskSession CRM is a self-hosted CRM, project management, client portal and team collaboration app built with PHP & MySQL.

**Website:** [tasksession.com](https://tasksession.com)

## Features

- Projects, tasks, Kanban board and task permissions
- Client portal and staff areas
- Team chat, task discussions and notifications
- Invoices, reports and dashboards
- Media vault and file sharing
- Attendance, notes and activity log
- Two-factor authentication (2FA)
- Web-based installer

## Requirements

- PHP 8.0 or higher
- MySQL / MariaDB
- PHP extensions: `mysqli`, `curl`, `gd`, `mbstring`, `openssl`, `zip`
- Apache with `mod_rewrite` (an `.htaccess` file is included)

## Installation

1. Download or clone this repository:
   ```bash
   git clone https://github.com/tasksession/tasksession-free.git
   ```
2. Upload the files to your web server (e.g. `public_html` or a subfolder).
3. Create an empty MySQL database and user.
4. Open `https://your-domain.com/install/` in your browser and follow the installer.
5. After installation, delete or protect the `install/` folder.
6. (Optional) Set up the scripts in `cron/` as cron jobs for reminders, recurring tasks and emails.

## Free vs Pro

This repository contains the **TaskSession CRM Free Edition**. For Pro features and support, visit [tasksession.com](https://tasksession.com).

## Support

- Bugs and feature requests: open an [Issue](../../issues)
- Website: [tasksession.com](https://tasksession.com)

## License

TaskSession CRM Free Edition is licensed under the [GNU Affero General Public License v3.0 (AGPL-3.0)](LICENSE).
If you modify it and distribute it or run it as a network service, you must make your modified source code available under the same license.

"TaskSession" name and logo are not covered by this license.
