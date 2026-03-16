# Ubuntu deploy

Base path in the examples below: `/var/www/callcenter-crm`

## 1. Install system packages

Install the PHP, Python, and Nginx packages first. Example for Ubuntu with PHP 8.3:

```bash
sudo apt update
sudo apt install -y nginx git unzip ffmpeg composer \
  php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-intl php8.3-bcmath \
  python3 python3-venv
```

If the server runs PHP 8.2, replace `php8.3-*` with `php8.2-*` and update the PHP-FPM socket path in `nginx-callcenter-crm.conf`.

## 2. Prepare the app

```bash
sudo mkdir -p /var/www
sudo chown -R $USER:www-data /var/www
git clone <your-repository-url> /var/www/callcenter-crm
cd /var/www/callcenter-crm
cp deploy/ubuntu/.env.production.example .env
```

Fill in at least:

- `APP_URL`
- `DB_*`
- `MAIL_*` if you do not want `log` mailer
- `AVITO_OAUTH_REDIRECT_URI` if Avito OAuth is used

Generate the Laravel key once:

```bash
php artisan key:generate --force
```

## 3. Run the deployment script

```bash
bash deploy/ubuntu/deploy.sh
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

For a fresh install, create the default admin/data set:

```bash
php artisan db:seed --force
```

## 4. Enable queue worker and scheduler

```bash
sudo cp deploy/ubuntu/callcenter-crm-queue.service /etc/systemd/system/
sudo cp deploy/ubuntu/callcenter-crm-scheduler.service /etc/systemd/system/
sudo cp deploy/ubuntu/callcenter-crm-scheduler.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now callcenter-crm-queue.service
sudo systemctl enable --now callcenter-crm-scheduler.timer
```

Useful checks:

```bash
sudo systemctl status callcenter-crm-queue.service
sudo systemctl status callcenter-crm-scheduler.timer
journalctl -u callcenter-crm-queue.service -n 100 --no-pager
```

## 5. Enable Nginx

```bash
sudo cp deploy/ubuntu/nginx-callcenter-crm.conf /etc/nginx/sites-available/callcenter-crm.conf
sudo ln -sf /etc/nginx/sites-available/callcenter-crm.conf /etc/nginx/sites-enabled/callcenter-crm.conf
sudo nginx -t
sudo systemctl reload nginx
```

## Notes

- `TRANSCRIPTION_DISPATCH=queue` is required on the server so audio transcription runs outside the web request.
- `DB_QUEUE_RETRY_AFTER=600` is intentionally greater than the transcription worker timeout.
- The current routes file contains a closure route, so `php artisan route:cache` is intentionally not used in `deploy.sh`.
- The first Whisper model download happens on the first transcription job and is stored in `storage/app/whisper-models`.
