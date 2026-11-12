# Full-Stack To-Do List (PHP + MySQL)

A to-do list where tasks are saved in a MySQL database through a JSON REST API written in plain PHP.
Users register and log in with a JWT. The HTML/CSS/JS frontend talks to the API with `fetch()`.

## What it does
- User accounts: register, log in and log out (JWT, passwords hashed with bcrypt)
- Every user sees and edits only their own tasks; admins can also see all users and all tasks
- Add, edit, complete and delete tasks
- Add many tasks at once (one per line) with a live counter
- Tasks survive a page refresh and a server restart
- Empty titles are rejected on both the frontend and the backend
- Loading state and friendly error messages

## Requirements
- PHP 8.1 or newer (with the `pdo_mysql` and `mbstring` extensions)
- MySQL 8 (or MariaDB)

## Install
```bash
git clone https://github.com/Leen223/todo-fullstack.git
cd todo-fullstack
```

## Set up the database
Run the two scripts in this order:
```bash
mysql -u root -p < database/schema.sql
mysql -u root -p < database/002_auth.sql
```
- `schema.sql` creates the `todo_app` database and the `tasks` table.
- `002_auth.sql` adds the `users` table and the `tasks.user_id` column. Run it only once.

Then create your `.env` file and fill in your values (it is ignored by Git):
```bash
cp .env.example .env
```

| Variable | Meaning |
|---|---|
| `DB_HOST` / `DB_PORT` | MySQL host and port (usually `127.0.0.1` / `3306`) |
| `DB_USER` / `DB_PASSWORD` | MySQL login |
| `DB_NAME` | `todo_app` |
| `CORS_ORIGIN` | Allowed frontend origin (default `*`) |
| `JWT_SECRET` | Secret used to sign tokens: at least 32 random characters |
| `JWT_EXPIRES_IN` | Token lifetime in seconds (default `3600` = 1 hour) |

Generate a secret with:
```bash
php -r "echo bin2hex(random_bytes(32));"
```

## Run
```bash
php -S localhost:8000 -t public router.php
```
Open http://localhost:8000

**With XAMPP instead:** copy the project folder into `htdocs`, enable `mod_rewrite`, and open `http://localhost/<folder>/public/`.

If the frontend runs on another port, add this line before `api.js` in `public/index.html`:
`<script>window.API_BASE = "http://localhost:8000/api";</script>`

## API
All responses are JSON. Errors look like `{ "error": "message" }`.

**Every `/api/tasks` endpoint needs `Authorization: Bearer <token>`** and only works on the logged-in user's own tasks (401 without a valid token).

| Method | Endpoint | Purpose | Success |
|---|---|---|---|
| GET | `/api/tasks` | List all tasks, newest first | 200 |
| GET | `/api/tasks/:id` | Get one task | 200 (404 if not found) |
| POST | `/api/tasks` | Create a task `{ "title": "..." }` | 201 |
| PUT | `/api/tasks/:id` | Edit `title` and/or `is_completed` | 200 |
| DELETE | `/api/tasks/:id` | Delete a task | 204 |
| POST | `/api/tasks/bulk` | Create several tasks in one transaction `{ "titles": ["A", "B"] }` (1 to 50 items) | 201 |

## Authentication (JWT)
Users must register and log in before using the app. Passwords are hashed with bcrypt and never returned by the API.

| Method | Endpoint | Purpose | Success |
|---|---|---|---|
| POST | `/api/auth/register` | Create an account `{ "name", "email", "password" }` | 201 (400 invalid input, 409 email already used) |
| POST | `/api/auth/login` | Returns a JWT `{ "email", "password" }` | 200 (401 "Invalid email or password") |
| GET | `/api/auth/me` | Profile of the logged-in user | 200 (401 without a valid token) |

- The JWT payload holds only `id`, `email` and `role` (plus the `iat`/`exp` times) and is signed with HS256 using `JWT_SECRET`. It expires after `JWT_EXPIRES_IN` seconds (default 1 hour).
- Send it on every API call: `Authorization: Bearer <token>`.

### Where the token is stored
The frontend keeps the JWT in **localStorage** and sends it in the `Authorization` header.

- **Why:** it is simple and works when the frontend and the API are on different domains.
- **Trade-off:** JavaScript can read localStorage, so an XSS bug could steal the token. An `httpOnly` cookie cannot be read by JavaScript, but it needs CSRF protection and extra cookie/CORS settings.
- **Mitigations used:** all user text is inserted with `textContent` (never `innerHTML`), and the token expires after 1 hour.

## Authorization (roles)

| Role | Own tasks | Other users' tasks | Users list |
|---|---|---|---|
| `user` (default) | view, add, edit, delete | no access | no access |
| `admin` | view, add, edit, delete | view all, with the owner's name | view all |

- The **auth middleware** (`requireAuth`) verifies the JWT and loads the user from the database, so a deleted account or a changed role takes effect immediately.
- The **role middleware** (`requireRole('admin')`) protects the admin endpoints.
- Every task query is limited with `WHERE user_id = ?`, and new tasks are saved with the current user's id.

### Admin endpoints

| Method | Endpoint | Purpose | Access |
|---|---|---|---|
| GET | `/api/admin/users` | List all users (no passwords) | admin |
| GET | `/api/admin/tasks` | List all tasks with their owner | admin |
| DELETE | `/api/admin/users/:id` | Delete a user and their tasks | admin |

A normal user gets **403 Forbidden**, and a request without a valid token gets **401**.
An admin cannot delete their own account.

### Why 404 and not 403 for another user's task
If a user asks for a task that belongs to someone else, the API answers **404 Not Found**, the same answer as for a task that does not exist. With 403 the API would confirm that the ID exists and belongs to another person, so IDs would be leaked. 403 is used only for admin endpoints, where the endpoint itself exists but the role is not allowed.

### Create the first admin
Every new account is a normal `user`. Create the first admin with the seed script:
```bash
php database/seed_admin.php "Admin Name" admin@example.com "StrongPassword123"
```
If the email already exists, that account is promoted to admin (its password is not changed). Or run this SQL:
```sql
UPDATE users SET role = 'admin' WHERE email = 'you@example.com';
```
The admin page is `admin.html` (the **Admin** link appears in the app only for admins). Hiding the link is only for convenience: the API itself rejects non-admins.

Tasks created before the auth migration have no owner (`user_id` is `NULL`); delete them with `DELETE FROM tasks WHERE user_id IS NULL;`.

## Project structure
```
public/                 index.html, login.html, register.html, admin.html, style.css, api.js, app.js, auth.js, admin.js
public/api/index.php    API router
src/config.php          .env loader
src/db.php              PDO connection
src/helpers.php         JSON responses and validation
src/jwt.php             JWT helpers (HS256)
src/middleware.php      auth and role middleware
src/auth.php            register, login and me
src/tasks.php           task endpoint handlers
src/bulk.php            bulk-add endpoint (transaction)
src/admin.php           admin endpoints
router.php              router for PHP's built-in server
database/               schema.sql, 002_auth.sql, seed_admin.php
```
