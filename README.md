# Caetanix Hosting Billing

Billing for hosting services and domains: clients, products, servers, services,
domains, orders, payments, automated renewal orders and reminder emails, and
import from WHMCS.

Built with Laravel, Livewire and Flux.

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
