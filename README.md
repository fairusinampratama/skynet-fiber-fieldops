# 🌐 Skynet Fiber FieldOps

A comprehensive Laravel + Filament admin panel application for managing optical fiber field documentation. Streamlines the submission, review, and approval workflow for ODC (Optical Distribution Cabinet) and ODP (Optical Distribution Point) asset data across multiple projects.

## ✨ Features

- **Multi-Project Support**: Organize assets and submissions across multiple fiber deployment projects
- **Technician Submissions**: Field technicians submit ODC/ODP data with:
  - Geolocation coordinates (latitude/longitude)
  - High-resolution photos
  - Core color identification
  - Port availability and status tracking
- **Admin Review Workflow**: Structured approval system for submissions before they become official records
- **Official Asset Records**: Approved submissions automatically become official ODC/ODP asset records
- **Technician Performance Tracking**: Monitor technician submissions and assignment progress
- **Comprehensive Dashboards**: Real-time analytics for asset statistics, port availability, project progress, and submission metrics
- **Advanced Filtering**: Filter by project, area, asset type, and status
- **CSV Exports**: Generate reports for analysis and record-keeping
- **Role-Based Access Control**: Admin and technician role separation with permission controls

## 🏗️ Tech Stack

- **Backend**: Laravel 11 with PHP 8.3
- **Admin Panel**: Filament 3
- **Database**: PostgreSQL
- **Frontend**: Alpine.js, Tailwind CSS
- **Build Tool**: Vite
- **Testing**: PHPUnit, Playwright (E2E)
- **Containerization**: Docker & Docker Compose

## 🏭 How It Works

```
Technician Submission
      ↓
Field Data Capture (photos, coordinates, ports)
      ↓
Submit to System
      ↓
Admin Review Dashboard
      ↓
[Approved] → Official Asset Record
      ↓
Dashboard Metrics & Reporting
```

The system maintains a clear separation between technician submissions and official assets, ensuring data integrity and accountability throughout the workflow.

## 🚀 Quick Start

### Prerequisites
- Docker & Docker Compose (recommended)
- OR PHP 8.3+, Composer, Node.js 18+

### Local Development

```bash
# Clone and setup
git clone <repository-url>
cd skynet-fiber-fieldops

# Copy environment file
cp .env.example .env

# Install dependencies
composer install
npm install

# Generate app key
php artisan key:generate

# Run migrations with seed data
php artisan migrate --seed

# Start development server
php artisan serve
npm run dev
```

**Default Credentials:**

| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@skynet.local` | `password` |
| Technician | `tech@skynet.local` | `password` |

Access the admin panel at: `http://localhost:8000/admin`

### Docker Setup

```bash
# Build and start containers
docker compose up --build

# Setup database
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed

# Access at http://localhost:8000/admin
```

### Big Data Dashboard Testing

Use the dedicated big-data command when you want to stress the operational dashboard and Filament resource tables. The normal `php artisan db:seed` stays small for everyday development.

```bash
# Build/start Docker and run normal migrations
docker compose up -d --build
docker compose exec app php artisan migrate

# Demo dashboard with realistic Indonesian project/area/technician data
docker compose exec app php artisan fieldops:seed-big-data --profile=demo --scenario=balanced --seed=2026 --reset --with-submissions

# Heavier pagination/performance run
docker compose exec app php artisan fieldops:seed-big-data --profile=medium --reset --with-submissions

# Additive staging-style run
docker compose exec app php artisan fieldops:seed-big-data --profile=medium --with-submissions
```

The `demo` profile creates deterministic `BIG-*` data with realistic labels such as Malang Timur FTTH, Surabaya Barat Expansion, Indonesian technician names, clustered coordinates, mixed asset health, all major assignment statuses, ODP utilization bands, pressured PONs, unmapped ODCs, and unlinked ODPs. Use `--scenario=balanced`, `--scenario=critical`, or `--scenario=clean` to control how severe the dashboard story is. The `--seed` option keeps coordinates and naming repeatable.

The `medium` profile remains available for scale testing: 10 projects, 100 areas, 100 OLT assets, 800 PON ports, 2,000 ODC assets, 10,000 ODP assets, 16,000 ODC ports, 80,000 ODP ports, and 2,000 submissions when `--with-submissions` is used.

Suggested checks after seeding:

```bash
docker compose exec app php artisan fieldops:seed-big-data --profile=demo --scenario=balanced --reset --with-submissions
docker compose exec app php artisan test tests/Feature/BigDataSeederTest.php
npm run test:e2e
```

Then open `http://localhost:8000/admin` and verify that KPI cards, alert operasional, ODP kritis, PON bermasalah, and port distribution widgets are populated.

## 📁 Project Structure

```
app/
├── Enums/              # Asset types, port statuses, user roles
├── Models/             # Eloquent models for all entities
├── Filament/
│   ├── Resources/      # CRUD interfaces for admin panel
│   ├── Widgets/        # Dashboard statistics & charts
│   └── Exports/        # CSV export generators
├── Services/           # Business logic (approval workflow, etc.)
└── Providers/          # Service providers & configuration

database/
├── migrations/         # Schema definitions
└── seeders/           # Sample data for development

tests/
├── Feature/           # Feature tests
└── e2e/              # Playwright end-to-end tests
```

## 📊 Key Models

- **Project**: Top-level container for fiber deployment projects
- **Area**: Geographical subdivisions within a project
- **OdcAsset / OdpAsset**: Official fiber cabinet/point records
- **Submission**: Technician field submissions awaiting approval
- **User**: System users with role-based permissions

## 🧪 Testing

```bash
# Run unit & feature tests
php artisan test

# Run E2E tests
npx playwright test

# View test report
npx playwright show-report
```

## 📝 Development Notes

- **Hot Module Replacement**: Changes to PHP code take effect immediately; no container restart needed
- **Cache**: Run `php artisan cache:clear` if you encounter caching issues
- **Migrations**: Database schema changes are handled through migrations
- **Submissions Workflow**: Check `ApproveSubmissionService` for approval business logic

## 🤝 Contributing

1. Create a feature branch: `git checkout -b feature/your-feature`
2. Commit changes: `git commit -am 'Add feature'`
3. Push to branch: `git push origin feature/your-feature`
4. Open a Pull Request

## 📄 License

This project is proprietary and confidential.

---

**Built with Laravel 11 & Filament 3** | Designed for scalable field operations management
