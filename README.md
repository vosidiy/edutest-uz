# EduTest

EduTest is a focused quiz platform for educational assessments and private practice. The MVP uses CodeIgniter 4, server-rendered pages, plain JavaScript, and a locally hosted Vue 3 runtime for the future interactive builder.

See [PLAN.MD](PLAN.MD) for product requirements and [ARCHITECTURE.MD](ARCHITECTURE.MD) for the database and application design.

## Requirements

- PHP 8.4 with `intl` and `mbstring`
- Composer 2
- MySQL 8.0 for application development

## Local setup

```bash
cp env .env
php spark serve
```

Open <http://localhost:8080>. To configure MySQL, edit `.env` and enable the `database.default.*` values. Keep secrets out of version control.

## Useful commands

```bash
composer test
php spark routes
php spark cache:clear
```

The production web server document root must point to `public/`. Writable runtime files belong in `writable/`.

The repository currently contains the CodeIgniter application scaffold and public landing page. Authentication and product features follow the milestones in `PLAN.MD`.
