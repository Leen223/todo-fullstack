# Full-Stack To-Do List (PHP + MySQL)

A to-do list where tasks are saved in a MySQL database through a JSON REST API written in plain PHP.
The HTML/CSS/JS frontend talks to the API with `fetch()`.

## What it does
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
```bash
mysql -u root -p < database/schema.sql
```
This creates the `todo_app` database and the `tasks` table.

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

| Method | Endpoint | Purpose | Success |
|---|---|---|---|
| GET | `/api/tasks` | List all tasks, newest first | 200 |
| GET | `/api/tasks/:id` | Get one task | 200 (404 if not found) |
| POST | `/api/tasks` | Create a task `{ "title": "..." }` | 201 |
| PUT | `/api/tasks/:id` | Edit `title` and/or `is_completed` | 200 |
| DELETE | `/api/tasks/:id` | Delete a task | 204 |
| POST | `/api/tasks/bulk` | Create several tasks in one transaction `{ "titles": ["A", "B"] }` (1 to 50 items) | 201 |

## Project structure
```
public/          index.html, style.css, api.js, app.js and api/index.php (the router)
src/config.php   .env loader
src/db.php       PDO connection
src/helpers.php  JSON responses and validation
src/tasks.php    endpoint handlers
src/bulk.php     bulk-add endpoint (transaction)
router.php       router for PHP's built-in server
database/        schema.sql
```
