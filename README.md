# Gym Tracker

Cuaderno de hierro (Gym Tracker) is a mobile-first web app for recording gym performance during training sessions. It is built as a lightweight PHP/MySQL application with plain HTML, CSS and JavaScript, designed to run on classic shared hosting without a frontend build process.

The project focuses on a practical workout flow: choose a routine, pick a muscle group and exercise, save a mark, and review progress through history tables and daily-best charts.

## Highlights

- Email registration, account verification and password reset.
- PHP session-based authentication.
- CSRF protection for mutating requests.
- Basic rate limiting for auth and import flows.
- User-owned workouts/routines linked to fixed muscle groups.
- User-created exercises with metric types: `kg`, `reps`, `min` and `km`.
- Training flow for saving marks by active workout and exercise.
- Personal records summary with best historical mark and latest entry.
- History view with progress summary, Chart.js daily-best graph, helpful empty state and editable records.
- Management view for workouts, exercises and optional gyms, with contextual editors and accessible delete confirmations.
- Distinctive Cuaderno de hierro identity with locally hosted fonts and responsive navigation.
- Progressive onboarding for the first workout, exercise and mark.
- JSON backup export/import for restorable user data.
- CSV export for spreadsheets and CSV exercise import.
- Local email logging for development with `APP_ENV=local`.

## Screens and Workflow

- **Auth**: login, registration, email verification, forgotten password and reset password.
- **Train**: select active workout, muscle group and exercise, then save a new mark. The selected workout, group and exercise remain active after saving.
- **History**: filter by group/exercise, review best/latest/change/count metrics and the daily-best graph, edit records or delete them after confirmation.
- **Management**: create, edit and delete workouts, exercises and optional gyms; export backups and import data.

The app is responsive from mobile through desktop, provides visible keyboard focus and respects reduced-motion preferences.

## Tech Stack

- PHP 8+
- MySQL/MariaDB
- Plain HTML, CSS and JavaScript
- Chart.js via CDN
- Composer
- PHPMailer for SMTP

There is no bundler, framework or compile step. The deployable web root is `public/`.

## Project Structure

```text
app/                  Shared PHP classes: config, database, auth, request/response, mail
database/schema.sql   Initial database schema and fixed muscle groups
docs/                 Human-oriented project documentation
public/               Web root: SPA, JSON API, CSS and JavaScript
storage/              Generated local files, such as email logs
vendor/               Composer dependencies
PROJECT_CONTEXT.md    Technical and product context for future development
README.md             Public repository overview
```

For a more detailed file-by-file guide, see [docs/PROJECT_STRUCTURE.md](docs/PROJECT_STRUCTURE.md).

## Local Setup

1. Install PHP 8+, MySQL/MariaDB and Composer.
2. Copy `.env.example` to `.env`.
3. Configure `APP_URL`, database credentials and SMTP values.
4. Install dependencies:

```powershell
composer install
```

5. Create the database and run:

```sql
database/schema.sql
```

6. Start a local PHP server:

```powershell
php -S 127.0.0.1:4177 -t public
```

7. Open:

```text
http://127.0.0.1:4177/
```

With `APP_ENV=local`, verification and reset emails are written to:

```text
storage/logs/mail.log
```

## Data Export and Import

The app can export a complete JSON backup that can be imported again by the same app. It includes workouts, exercises and historical records.

```json
{
  "schema": "gym-tracker-export",
  "version": 1,
  "workouts": [{ "name": "Push", "muscle_groups": ["Pectoral", "Hombro"] }],
  "exercises": [{ "muscle_group": "Pectoral", "name": "Press banca", "metric_type": "kg", "notes": "" }],
  "records": [{ "muscle_group": "Pectoral", "exercise": "Press banca", "workout": "Push", "value": "80.00", "metric_type": "kg", "note": "", "recorded_at": "2026-06-09 10:00:00" }]
}
```

CSV export is also available for spreadsheets. In V1, CSV import supports exercises only:

```csv
muscle_group,name,metric_type,notes
Pectoral,Press banca,kg,Barra olimpica
Cardio,Cinta,km,
```

Imports are previewed before writing data. Existing workouts and exercises are merged instead of duplicated.

## Example `.env`

```env
APP_ENV=local
APP_URL=http://127.0.0.1:4177

DB_HOST=localhost
DB_NAME=gym_tracker
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4
```

## Validation

Useful checks after changing the project:

```powershell
composer validate --strict
composer test
composer lint
node scripts\ui-design-requirements.test.cjs
node scripts\ui-shell-behavior.test.cjs
node scripts\training-ui-behavior.test.cjs
node scripts\history-ui-behavior.test.cjs
node scripts\management-ui-behavior.test.cjs
node scripts\auth-onboarding-ui.test.cjs
node scripts\desktop-ui-requirements.test.cjs
node scripts\desktop-ui-behavior.test.cjs
Get-ChildItem -Recurse -Filter *.php | Where-Object { $_.FullName -notlike '*\vendor\*' } | ForEach-Object { php -l $_.FullName }
node --check public\assets\app.js
git diff --check
```

## Deployment Notes

- The recommended document root is `public/`.
- If shared hosting cannot point directly to `public/`, the root `.htaccess` provides a basic fallback.
- Run `composer install --no-dev` for production deployments.
- If Composer cannot run on the server, install dependencies locally and upload `vendor/` together with the app.
- Existing databases must add the `rate_limits` table from `database/schema.sql`.
- Chart.js is loaded from a CDN, so the graph view requires internet access.

## Current Status

This is a functional V1 with the Cuaderno de hierro interface implemented across its main screens. Future work should focus on validated product needs and broader authenticated end-to-end coverage:

- Add incremental migrations for future schema changes.
- Add broader end-to-end tests around authenticated browser flows and account states.

## Documentation

- [Project structure guide](docs/PROJECT_STRUCTURE.md)
- [Technical/project context](PROJECT_CONTEXT.md)
