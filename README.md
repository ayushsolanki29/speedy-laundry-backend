# Speedy Laundry Backend

A lightweight PHP core structure for building APIs.

## Directory Structure

- `api/`: Contains all API endpoints (e.g., `status.php`, `login.php`).
- `config/`: Configuration files (database credentials, constants).
- `core/`: Core classes like `Database.php` and utility `functions.php`.
- `sql/`: SQL schema and migration files.
- `init.php`: Setup script to initialize the database and tables.

## Setup

1. Configure your database credentials in `config/config.php`.
2. Run `php init.php` from the terminal (or visit it in your browser if hosted) to create the database and initial tables.
3. Access the API at `http://your-domain/backend/api/status.php` to verify the connection.

## Features

- **Automatic DB Creation**: The `Database` class automatically creates the database if it doesn't exist.
- **PDO Singleton**: Efficient database connection management.
- **JSON Response Helpers**: Standardized API response format.
- **CORS Enabled**: Pre-configured for frontend integration.
