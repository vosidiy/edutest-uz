---
name: codeigniter
description: "Build, review, test, and deploy CodeIgniter 4 applications using current framework conventions for routing, controllers, models, databases, security, configuration, and testing."
license: MIT
metadata:
  version: "1.0.0"
---

# CodeIgniter 4 development

Use this skill for CodeIgniter 4 applications. Check the project's pinned framework version and its matching official documentation before relying on version-specific behavior.

## Identify the project

Common CI4 signals are:

- `app/Config/App.php` and `app/Config/Routes.php`.
- `public/index.php` as the front controller.
- `spark` at the project root.
- `codeigniter4/framework` in `composer.json`.
- Namespaced controllers under `App\Controllers`.

Inspect `composer.json`, `composer.lock`, existing configuration, routes, schema policy, and tests before editing. Do not edit `system/` or `vendor/`.

## Standard layout

```text
app/                 Application code and configuration
public/              Web document root
tests/               Automated tests
writable/            Logs, cache, sessions, and private runtime files
vendor/              Composer dependencies
.env                  Untracked environment values and secrets
spark                 CLI entry point
```

Keep namespaces consistent with PSR-4 paths and preserve filename/class casing for Linux deployments.

## Routes and filters

Prefer defined routes with explicit HTTP verbs. Keep automatic routing disabled unless the project deliberately uses Improved Auto Routing.

```php
$routes->setAutoRoute(false);
$routes->get('products', 'Products::index');
$routes->get('products/(:num)', 'Products::show/$1');
$routes->post('products', 'Products::create');
```

Use `(:segment)` for one URI segment; `(:any)` may span multiple segments. Apply filters to routes or route groups and verify the result:

```bash
php spark routes
php spark filter:check POST /products
```

Use filters for authentication, CSRF, throttling, and other cross-cutting request checks. Do not depend on filters as a replacement for resource-level authorization.

## Controllers and services

Controllers should read the request, validate transport-level input, call application/domain code, and return a response.

```php
public function create(): ResponseInterface
{
    $data = $this->request->getJSON(true);

    if (! is_array($data)) {
        return $this->response->setStatusCode(400)->setJSON([
            'error' => 'A JSON object is required.',
        ]);
    }

    $result = service('productService')->create($data);

    return $this->response->setStatusCode(201)->setJSON($result);
}
```

Use `$this->request->getPost()` for form data and `getJSON(true)` for JSON. Return views/responses instead of echoing them. Put transactions and multi-step business rules in services, not controllers.

## Validation and models

Use CI validation for request shape and service/domain validation for ownership, state, and cross-record rules.

```php
final class ProductModel extends Model
{
    protected $table = 'products';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['name', 'price', 'active'];
}
```

Always define narrow `$allowedFields`. Field protection helps prevent mass assignment but does not provide authorization or complete validation. Never make ownership, credentials, server-generated tokens, scores, or internal state writable from request data without a controlled service operation.

Model validation on partial updates validates supplied fields by default. Validate complete state explicitly when the operation requires it.

## Database and schema management

Use models, Query Builder, or bound SQL. Never concatenate untrusted input into SQL.

Follow the project's declared schema workflow. A project may use CI4 migrations or a canonical SQL file managed outside the application. When SQL is the source of truth, update that file and provide exact forward SQL for existing databases; do not introduce migrations alongside it unless the project changes policy.

Use transactions for workflows that must commit atomically:

```php
$db = db_connect();
$db->transBegin();

try {
    // Perform related writes and required row locks.

    // Query errors do not throw inside CI4 transactions by default.
    if ($db->transStatus() === false) {
        throw new \RuntimeException('Database transaction failed.');
    }

    if (! $db->transCommit()) {
        throw new \RuntimeException('Database transaction could not be committed.');
    }
} catch (\Throwable $exception) {
    $db->transRollback();
    throw $exception;
}
```

Always check `transStatus()` when managing transactions manually. Alternatively, enable `$db->transException(true)` and follow CI4's exception-based transaction pattern. Use a consistent lock order and bounded deadlock retries for concurrent workflows. Store timestamps in a documented timezone and use decimal types for exact financial/scoring values.

## Authentication and authorization

Follow the authentication approach selected by the project. For project-owned email/password authentication, use PHP's password API, CI4 sessions, CSRF protection, and rate limiting:

```php
$routes->group('account', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', 'Account::index');
});

$hash = password_hash($password, PASSWORD_DEFAULT);
$valid = password_verify($password, $hash);
```

Regenerate the session ID after login, store only a narrow account identifier in the session, use `password_needs_rehash()` after successful verification, and return the same error for nonexistent and incorrect-password accounts. Never flash passwords back to the session or log credentials.

Authentication proves who the user is. Each service must still authorize the requested record and nested resources.

## Security

- Keep session-authenticated POST/PUT/PATCH/DELETE routes CSRF-protected.
- Escape untrusted view output with `esc()` using the appropriate context.
- Validate input; do not rely on output escaping as input sanitization.
- Use secure cookies and HTTPS in production.
- Validate uploaded files by size and actual content/MIME, not client filename alone.
- Store private files outside `public/` and authorize every download.
- Do not log passwords, tokens, secrets, signed URLs, or sensitive request bodies.
- Disable detailed error output and development tools in production.

## Views and frontend assets

Use view layouts, sections, and partials for server-rendered pages:

```php
<?= $this->extend('layouts/default') ?>
<?= $this->section('content') ?>
<h1><?= esc($title) ?></h1>
<?= $this->endSection() ?>
```

Store public CSS/JavaScript under `public/`. Keep private uploads and generated runtime data under `writable/` or another non-public directory.

## Configuration and `.env`

CI4 loads a root `env` template copied to `.env`. Keep `.env` untracked.

```ini
CI_ENVIRONMENT = development
app.baseURL = 'http://localhost:8080/'
database.default.DBDriver = MySQLi
```

Place secrets and environment-dependent values in `.env` or host environment variables. Keep structural, non-secret defaults in typed `app/Config/*` classes. Never expose `phpinfo()`, environment dumps, or configuration objects publicly.

Useful checks:

```bash
php spark env
php spark phpini:check
php spark cache:clear
```

## Testing

- Extend `CodeIgniter\Test\CIUnitTestCase`.
- Use `DatabaseTestTrait` with a dedicated test database.
- Use `FeatureTestTrait` for routes, filters, sessions, and HTTP responses.
- Call parent `setUp()`/`tearDown()` when overriding them.
- Create known test state using the project's schema workflow and isolated fixtures.
- Never run automated tests against a development or production database.

Run focused tests first, then the relevant suite. Test successful behavior, validation failures, forbidden access, transaction rollback, and concurrency/retry behavior where applicable.

## Deployment

- Point the web server document root to `public/`.
- Install locked production dependencies with `composer install --no-dev --optimize-autoloader`.
- Set `CI_ENVIRONMENT = production`.
- Make only required runtime directories writable; never use broad world-writable permissions.
- Back up before schema changes and verify the applied database matches its source of truth.
- Configure HTTPS, production mail, cron jobs, logging/redaction, and backups.
- Clear relevant caches after configuration or file-location changes.

For shared hosting that cannot point directly to `public/`, follow CI4's official two-directory deployment guidance rather than exposing the project root.

## Official documentation

- [Requirements](https://codeigniter.com/user_guide/intro/requirements.html)
- [Configuration and dotenv](https://codeigniter.com/user_guide/general/configuration.html)
- [Routing](https://codeigniter.com/user_guide/incoming/routing.html)
- [Filters](https://codeigniter.com/user_guide/incoming/filters.html)
- [Security and CSRF](https://codeigniter.com/user_guide/libraries/security.html)
- [Models](https://codeigniter.com/user_guide/models/model.html)
- [Database testing](https://codeigniter.com/user_guide/testing/database.html)
- [Feature testing](https://codeigniter.com/user_guide/testing/feature.html)
- [Deployment](https://codeigniter.com/user_guide/installation/deployment.html)
