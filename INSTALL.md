# Installation Guide

This guide walks you through setting up Coinwink V2 (Laravel 9 + Vue 3) from a fresh clone to a running development environment, and lists the extra steps required for production features (alerts, SMS, Telegram, market data, payments).

---

## 1. Requirements

Make sure the following are installed and available on your `PATH`:

| Tool | Version | Notes |
|------|---------|-------|
| PHP | 8.0.2+ | With extensions: `mbstring`, `xml`, `bcmath`, `curl`, `mysqli`, `pdo_mysql`, `openssl`, `tokenizer`, `ctype`, `json`, `fileinfo` |
| Composer | 2.x | https://getcomposer.org |
| Node.js | 16+ | LTS recommended |
| npm | 8+ | Ships with Node |
| MySQL | 5.7+ / 8.0 | MariaDB 10.4+ also works |
| Web server | Apache or Nginx | Must support virtual hosts |

Optional (only for specific features):

- A **CoinMarketCap API key** (price data) — https://coinmarketcap.com/api/pricing/
- A **Twilio account** (SMS alerts) — https://www.twilio.com/
- A **Telegram bot token** (Telegram alerts) — https://core.telegram.org/bots
- A **Stripe account** (paid subscriptions) — https://stripe.com
- An **SMTP server** (email alerts and user signup)

---

## 2. Clone the Repository

```bash
git clone https://github.com/coinwink/Coinwink.git
cd Coinwink
```

---

## 3. Install Dependencies

```bash
composer install
npm install
```

---

## 4. Environment Configuration

Copy the example file and generate the application key:

```bash
cp .env.example .env
php artisan key:generate
```

Open `.env` and set at minimum:

```ini
APP_NAME=Coinwink
APP_ENV=local
APP_URL=http://coinwink.local      # match your virtual host

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=coinwink                # create this database first
DB_USERNAME=root
DB_PASSWORD=your_password

# Telegram bot library uses its own MySQL connection
TG_DB_HOST=localhost
TG_DB_DATABASE=coinwink_tg
TG_DB_USERNAME=root
TG_DB_PASSWORD=your_password

# SMTP — required to create new users (verification emails)
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your_user
MAIL_PASSWORD=your_pass
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=hello@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

ADMIN_EMAIL=admin@yourdomain.com
```

Stripe, AWS, Pusher, and Redis keys can be left blank unless you need those features.

---

## 5. Create the Database

```bash
mysql -u root -p -e "CREATE DATABASE coinwink CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Then run the migrations:

```bash
php artisan migrate
```

---

## 6. Configure the Standalone PHP Auth Files

The cron scripts under `/public` are standalone PHP — they do **not** read `.env`. You must edit each `coinwink_auth_*.php` file with the appropriate credentials:

| File | Purpose | Required for |
|------|---------|--------------|
| `public/coinwink_auth_sql.php` | MySQL connection for cron scripts | All cron scripts |
| `public/coinwink_auth_cmc.php` | CoinMarketCap API key | Price/market data |
| `public/coinwink_auth_email.php` | SMTP credentials | Email alerts |
| `public/coinwink_auth_sms.php` | Twilio SID/token | SMS alerts |
| `public/coinwink_auth_tg.php` | Telegram bot token | Telegram alerts |
| `public/coinwink_auth_crons.php` | Cron lock/auth token | Cron entry protection |

At minimum, set the MySQL credentials in `public/coinwink_auth_sql.php`:

```php
$servername = "localhost";
$username   = "root";
$password   = "your_password";
$dbname     = "coinwink";
```

---

## 7. Cryptocurrency Logos

Download the logo set into `public/img/coins/`:

```bash
git clone https://github.com/coinwink/cryptocurrency-logos.git /tmp/cw-logos
mkdir -p public/img/coins
cp -r /tmp/cw-logos/* public/img/coins/
```

Final structure should be `public/img/coins/<logo files>`.

---

## 8. Web Server Virtual Host

Point a virtual host at the **`public/`** directory (never the repo root).

### Apache example

```apache
<VirtualHost *:80>
    ServerName coinwink.local
    DocumentRoot /var/www/Coinwink/public

    <Directory /var/www/Coinwink/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Add to `/etc/hosts`:

```
127.0.0.1   coinwink.local
```

### Nginx example

```nginx
server {
    listen 80;
    server_name coinwink.local;
    root /var/www/Coinwink/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
    }
}
```

Then update `webpack.mix.js` so BrowserSync proxies your vhost:

```js
mix.browserSync({
    proxy: 'coinwink.local',
    notify: false
});
```

---

## 9. Build Frontend Assets

For development with hot reload:

```bash
npm run watch
```

For a production build:

```bash
npm run prod
```

---

## 10. Create Your First User

1. Visit `http://coinwink.local` in a browser.
2. Use the sign-up form to register (SMTP must be configured — the email verification flow runs through Laravel Fortify).
3. To unlock all premium features manually:

   ```sql
   -- Mark the user as a paid subscriber
   UPDATE cw_settings SET subs = 1, sms = 100 WHERE user_id = <YOUR_USER_ID>;

   -- Add an active subscription record
   INSERT INTO cw_subs (user_id, status, plan)
   VALUES (<YOUR_USER_ID>, 'active', 'premium');
   ```

---

## 11. Background Jobs (Cron Scripts)

These scripts live in `public/` and should be triggered via `cron` (or any scheduler). They are standalone PHP — invoke them with `php`, **not** `php artisan`.

Suggested crontab:

```cron
# Fetch CoinMarketCap data every 5 minutes
*/5 * * * * php /var/www/Coinwink/public/cron_data_cmc.php

# Fiat currency rates every hour
0 * * * *   php /var/www/Coinwink/public/cron_data_cur_rates.php

# Price/percentage alerts (run frequently)
*/2 * * * * php /var/www/Coinwink/public/cron_alerts_email_cur.php
*/2 * * * * php /var/www/Coinwink/public/cron_alerts_email_per.php
*/2 * * * * php /var/www/Coinwink/public/cron_alerts_sms_cur.php
*/2 * * * * php /var/www/Coinwink/public/cron_alerts_sms_per.php
*/2 * * * * php /var/www/Coinwink/public/cron_alerts_tg_cur.php
*/2 * * * * php /var/www/Coinwink/public/cron_alerts_tg_per.php
*/5 * * * * php /var/www/Coinwink/public/cron_alerts_portfolio.php

# Maintenance
0 0 * * *   php /var/www/Coinwink/public/cron_delete_logs.php
*/10 * * * * php /var/www/Coinwink/public/cron_rate_limiter_reset.php
*/10 * * * * php /var/www/Coinwink/public/cron_rate_limiter_alerts_reset.php
```

Only enable the rows you have credentials for (e.g. skip SMS rows if you have no Twilio account).

---

## 12. Verification Checklist

After completing the steps above:

- [ ] `http://coinwink.local` loads the homepage.
- [ ] You can register a new user and receive the verification email.
- [ ] After verification, you can log in and access the dashboard.
- [ ] Logos appear next to coin names (confirms step 7).
- [ ] `php /var/www/Coinwink/public/cron_data_cmc.php` exits cleanly and populates the `cw_coins` table.
- [ ] `npm run watch` rebuilds assets when you edit a `.vue` file under `resources/js/`.

---

## 13. Production Notes

For production deployments, also do the following:

```bash
# Optimize Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Build minified assets
npm run prod

# Set restrictive permissions on credential files
chmod 600 public/coinwink_auth_*.php
chown -R www-data:www-data storage bootstrap/cache
```

In `.env`, set:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
```

Serve only over HTTPS, and make sure the web root points at `public/` so the `coinwink_auth_*.php` files in that directory cannot be directly fetched — Laravel's `.htaccess` handles this for Apache, but verify your Nginx config blocks direct access to them.

---

## 14. Troubleshooting

| Symptom | Likely cause |
|---------|--------------|
| `SQLSTATE[HY000] [2002]` on `php artisan migrate` | MySQL not running, or wrong host/port in `.env` |
| White page / 500 error | Check `storage/logs/laravel.log`; usually a missing extension or wrong `APP_KEY` |
| Assets 404 | Run `npm run prod` (or `watch`) — `public/mix-manifest.json` must match the requested files |
| Cron scripts print "Connection failed" | Credentials in `public/coinwink_auth_sql.php` are wrong or DB isn't reachable |
| Signup verification email never arrives | SMTP settings in `.env` are wrong, or `MAIL_MAILER=log` is still set |
| Logos missing | Step 7 not completed |

---

## License

Coinwink's source code is available for personal and non-commercial use only. See [LICENSE](LICENSE) for details.
