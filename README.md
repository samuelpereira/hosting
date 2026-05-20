<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

## Configuration

### 1. Run composer

````
composer install
````

### 2. Run composer script

````
composer run-script setup
````

## Create a Service

### Create file in /etc/systemd/system/billing-queue.service

````
[Unit]
Description=Laravel Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
WorkingDirectory=/var/www/billing
ExecStart=/usr/bin/php artisan queue:listen --name=billing --memory=128 --timeout=3600 --tries=5

[Install]
WantedBy=multi-user.target
````

### Enable the Service

````
systemctl enable billing-queue.service
````

### Start the Service

````
service billing-queue start
````

### Run migrations
````
php artisan migrate
````

````
php artisan db:seed
````

### Force Run Automation
````
php artisan billing:run-automation --force
````

### Crontab
````
0 * * * * php artisan schedule:run
````
