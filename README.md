Crypto-service: <br>
php 8.3 <br>
Laravel 12 <br>
sqlite3 <br>

Установка: <br>
git clone <br>
composer install <br>
cp .env.example .env <br>
php artisan key:generate <br>
php artisan migrate --seed <br>
php artisan serve <br>

При запуске сидов создается тестовый юзер <br>
{ <br>
    "email": "test@example.com", <br>
    "password": "password" <br>
} <br>

Получаем токен Sanctum для энподинтов: <br>
GET       api/crypto/balance <br>
POST      api/crypto/deposit <br>
GET       api/crypto/transactions <br>
POST      api/crypto/withdraw <br>
POST      api/crypto/withdraw/cancel <br>
POST      api/crypto/withdraw/confirm <br>
POST      api/crypto/withdraw/pending <br>