# pomfIB

pomfIB is a small anonymous PHP/MySQL imageboard.

It is intentionally simple and uses the supplied stylesheet as `style.css` without creating a replacement theme stylesheet.

## Included

- PHP 8.3-compatible code
- MariaDB/MySQL schema
- Boards and board descriptions
- Anonymous threads and replies
- Image uploads: JPG, PNG, GIF, WebP
- 8 MB upload limit
- Thread pinning and locking
- Post deletion
- Admin login with password hashing
- CSRF protection
- Prepared SQL statements
- Formatting:
  - `>greentext`
  - `[pink]pink text[/pink]`
  - `[spoiler]hidden text[/spoiler]`
  - `==red text==`

## Ubuntu 24.04 setup

Install the packages:

```bash
sudo apt update
sudo apt install apache2 mariadb-server php php-mysql php-mbstring php-fileinfo unzip
```

Copy the project:

```bash
sudo mkdir -p /var/www/pomfIB
sudo unzip pomfIB.zip -d /var/www/pomfIB
sudo chown -R www-data:www-data /var/www/pomfIB
sudo chmod 755 /var/www/pomfIB/uploads
```

Create the database:

```bash
sudo mariadb < /var/www/pomfIB/db.sql
```

Edit the password in both the database setup and `config.php` so they match:

```bash
sudo nano /var/www/pomfIB/config.php
```

Create an Apache virtual host:

```apache
<VirtualHost *:80>
    ServerName example.com
    DocumentRoot /var/www/pomfIB

    <Directory /var/www/pomfIB>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/pomfib_error.log
    CustomLog ${APACHE_LOG_DIR}/pomfib_access.log combined
</VirtualHost>
```

Then:

```bash
sudo a2ensite pomfib.conf
sudo systemctl reload apache2
```

Visit:

```text
/admin/setup.php
```

Create your first admin, then immediately delete `admin/setup.php`.

## Important production notes

- Change the database password and the `ip_hash()` salt.
- Use HTTPS.
- Delete `admin/setup.php` after creating the first admin.
- Consider adding rate limiting before exposing the board publicly.
- The default upload handling accepts image MIME types only; production deployments should also consider image re-encoding and stricter dimension/pixel limits.
