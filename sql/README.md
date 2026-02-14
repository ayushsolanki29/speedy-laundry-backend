# Database Schema

## New Install

Run the full schema:

```bash
php ../init.php
```

Or import manually:

```bash
mysql -u root -p your_database < schema.sql
```

## Existing Database

If you upgraded from an older version, run:

```bash
php ../apply_updates.php
```

For manual migration (add blog counts only):

```bash
mysql -u root -p your_database < migrate-existing.sql
```
Note: Ignore "Duplicate column" errors if columns already exist.

To seed existing reviews when the `reviews` table is empty:

```bash
mysql -u root -p your_database < seed-reviews.sql
```

## Tables

| Table           | Purpose                                    |
|-----------------|--------------------------------------------|
| admins          | Admin users (login)                        |
| admin_sessions  | Admin tokens (session validation)          |
| settings        | Site config (footer links, contact info)   |
| enquiries       | Contact form & business quote submissions  |
| visits          | Visitor tracking for dashboard             |
| email_queue     | Queued emails (processed by cron script)   |
| blogs           | Blog posts                                 |
| blog_likes      | Blog like tracking (by IP)                 |
| blog_comments   | Blog comments & admin replies              |
| reviews         | Customer testimonials (admin-managed)       |
