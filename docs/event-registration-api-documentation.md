# Event Registration API - Fejlesztői dokumentáció

## Tartalomjegyzék
1. [Projekt áttekintése](#projekt-áttekintése)
2. [Adatbázis tervezés](#adatbázis-tervezés)
3. [Modellek](#modellek)
4. [API végpontok](#api-végpontok)
5. [Autentikáció és authorizáció](#autentikáció-és-authorizáció)
6. [Telepítés és konfiguráció](#telepítés-és-konfiguráció)
7. [Tesztelés](#tesztelés)

---

## Projekt áttekintése

Az **Event Registration API** egy Laravel alapú REST API, amely eseménykezelést és regisztrációt biztosít. A rendszer lehetővé teszi felhasználók számára, hogy eseményekre regisztráljanak, adminisztrátorok számára pedig események és felhasználók kezelését.

### Főbb funkciók:
- Felhasználói regisztráció és autentikáció (Laravel Sanctum)
- Esemény létrehozása, módosítása, törlése (admin)
- Eseményekre való regisztráció és lemondás
- Maximális résztvevők számának kezelése
- Regisztrációs státuszok kezelése (függőben, elfogadva, elutasítva)
- Soft delete támogatás

### Technológiai stack:
- **Backend**: Laravel 11.x
- **Autentikáció**: Laravel Sanctum (Bearer token)
- **Adatbázis**: MySQL/PostgreSQL
- **API**: RESTful

---

## Adatbázis tervezés

### 1.1 Users tábla

A felhasználók tárolására szolgáló tábla, Laravel Sanctum támogatással.

**Migráció:** `database/migrations/0001_01_01_000000_create_users_table.php`

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->string('phone')->nullable();
    $table->boolean('is_admin')->default(false);
    $table->rememberToken();
    $table->timestamps();
});
```

**Mezők magyarázata:**
- `id`: Egyedi azonosító (auto-increment)
- `name`: Felhasználó neve
- `email`: Email cím (egyedi, bejelentkezéshez használt)
- `password`: Bcrypt hashelt jelszó
- `phone`: Telefonszám (opcionális)
- `is_admin`: Admin jogosultság flag
- `remember_token`: "Emlékezz rám" funkció tokenje
- `timestamps`: created_at, updated_at

### 1.2 Events tábla

Az események tárolására szolgáló tábla.

**Migráció:** `database/migrations/2026_01_08_075255_create_events_table.php`

```php
Schema::create('events', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('description')->nullable();
    $table->dateTime('date');
    $table->string('location');
    $table->integer('max_attendees');
    $table->softDeletes();
    $table->timestamps();
});
```

**Mezők magyarázata:**
- `id`: Egyedi azonosító
- `title`: Esemény címe
- `description`: Részletes leírás (opcionális)
- `date`: Esemény időpontja
- `location`: Helyszín
- `max_attendees`: Maximum résztvevők száma
- `deleted_at`: Soft delete timestamp
- `timestamps`: created_at, updated_at

### 1.3 Registrations tábla

Az eseményekre való regisztrációk tárolására szolgáló pivot tábla.

**Migráció:** `database/migrations/2026_01_08_080039_create_registrations_table.php`

```php
Schema::create('registrations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('event_id')->constrained()->onDelete('cascade');
    $table->enum('status', ['függőben', 'elfogadva', 'elutasítva'])->default('függőben');
    $table->timestamp('registered_at')->useCurrent();
    $table->softDeletes();
    $table->timestamps();
    $table->unique(['user_id', 'event_id']);
});
```

**Mezők magyarázata:**
- `user_id`: Felhasználó ID (foreign key)
- `event_id`: Esemény ID (foreign key)
- `status`: Regisztráció státusza (függőben/elfogadva/elutasítva)
- `registered_at`: Regisztráció időpontja
- `unique constraint`: Egy felhasználó csak egyszer regisztrálhat egy eseményre

### 1.4 Personal Access Tokens tábla

Laravel Sanctum tokenek tárolására.

**Migráció:** `database/migrations/2026_01_08_073417_create_personal_access_tokens_table.php`

---

## Modellek

### 2.1 User Model

**Fájl:** `app/Models/User.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'is_admin'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Kapcsolatok
    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function events()
    {
        return $this->belongsToMany(Event::class, 'registrations')
            ->withPivot('status', 'registered_at')
            ->withTimestamps();
    }
}
```

**Főbb tulajdonságok:**
- Automatikus jelszó hashelés (`hashed` cast)
- Sanctum token kezelés támogatás
- Kapcsolat regisztrációkkal és eseményekkel

### 2.2 Event Model

**Fájl:** `app/Models/Event.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'date',
        'location',
        'description',
        'max_attendees'
    ];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
        ];
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'registrations')
            ->withPivot('status', 'registered_at')
            ->withTimestamps();
    }
}
```

**Főbb tulajdonságok:**
- Soft delete támogatás
- Dátum automatikus casting
- Kapcsolat regisztrációkkal és felhasználókkal

### 2.3 Registration Model

**Fájl:** `app/Models/Registration.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Registration extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'event_id',
        'status',
        'registered_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
```

**Főbb tulajdonságok:**
- Pivot model user és event között
- Státusz kezelés
- Soft delete támogatás

---

## API végpontok

### 3.1 Publikus végpontok (autentikáció nélkül)

#### GET /api/ping
Teszt végpont az API elérhetőségének ellenőrzésére.

**Request:**
```http
GET /api/ping
```

**Response:**
```json
{
  "message": "API működik"
}
```

#### POST /api/register
Új felhasználó regisztrálása.

**Request:**
```http
POST /api/register
Content-Type: application/json

{
  "name": "Teszt János",
  "email": "janos@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "phone": "+36301234567"
}
```

**Validációs szabályok:**
- `name`: kötelező, string, max 255 karakter
- `email`: kötelező, email formátum, egyedi
- `password`: kötelező, min 6 karakter, megerősítés szükséges
- `phone`: opcionális, max 20 karakter

**Success Response (201):**
```json
{
  "message": "User created successfully",
  "user": {
    "id": 1,
    "name": "Teszt János",
    "email": "janos@example.com",
    "phone": "+36301234567"
  }
}
```

**Error Response (422):**
```json
{
  "message": "Failed to register user",
  "errors": {
    "email": ["The email has already been taken."]
  }
}
```

#### POST /api/login
Bejelentkezés és token generálás.

**Request:**
```http
POST /api/login
Content-Type: application/json

{
  "email": "janos@example.com",
  "password": "password123"
}
```

**Success Response (200):**
```json
{
  "message": "Login successful",
  "user": {
    "id": 1,
    "name": "Teszt János",
    "email": "janos@example.com",
    "phone": "+36301234567",
    "is_admin": false
  },
  "access": {
    "token": "1|abcdef123456...",
    "token_type": "Bearer"
  }
}
```

**Error Response (401):**
```json
{
  "message": "Invalid email or password."
}
```

---

### 3.2 Védett végpontok (autentikáció szükséges)

Minden védett végpont esetén szükséges a Bearer token:

```http
Authorization: Bearer 1|abcdef123456...
```

#### POST /api/logout
Kijelentkezés (aktuális token törlése).

**Request:**
```http
POST /api/logout
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "message": "Logged out successfully"
}
```

---

### 3.3 User végpontok

#### GET /api/me
Bejelentkezett felhasználó adatainak lekérése.

**Controller:** `UserController@me`

#### PUT /api/me
Bejelentkezett felhasználó adatainak módosítása.

**Controller:** `UserController@updateMe`

#### GET /api/users
Összes felhasználó listázása (csak admin).

**Controller:** `UserController@index`

#### GET /api/users/{id}
Egy felhasználó adatainak lekérése (csak admin).

**Controller:** `UserController@show`

#### POST /api/users
Új felhasználó létrehozása (csak admin).

**Controller:** `UserController@store`

#### PUT /api/users/{id}
Felhasználó módosítása (csak admin).

**Controller:** `UserController@update`

#### DELETE /api/users/{id}
Felhasználó törlése (csak admin).

**Controller:** `UserController@destroy`

---

### 3.4 Event végpontok

#### GET /api/events
Összes esemény listázása.

**Controller:** `EventController@index`

**Response példa:**
```json
[
  {
    "id": 1,
    "title": "Laravel Meetup 2026",
    "description": "Havi Laravel közösségi találkozó",
    "date": "2026-02-15T18:00:00.000000Z",
    "location": "Budapest, MeetUp Café",
    "max_attendees": 50,
    "created_at": "2026-01-14T10:00:00.000000Z",
    "updated_at": "2026-01-14T10:00:00.000000Z"
  }
]
```

#### GET /api/events/upcoming
Jövőbeli események listázása.

**Controller:** `EventController@upcoming`

#### GET /api/events/past
Múltbeli események listázása.

**Controller:** `EventController@past`

#### GET /api/events/filter
Események szűrése különböző feltételek alapján.

**Controller:** `EventController@filter`

**Query paraméterek:**
- `date_from`: Dátum-tól
- `date_to`: Dátum-ig
- `location`: Helyszín
- `available_spots`: Csak elérhető helyekkel

#### POST /api/events
Új esemény létrehozása (csak admin).

**Controller:** `EventController@store`

**Request:**
```http
POST /api/events
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "Laravel Workshop",
  "description": "Kezdő Laravel workshop",
  "date": "2026-03-20T14:00:00",
  "location": "Budapest, Tech Hub",
  "max_attendees": 30
}
```

**Validációs szabályok:**
- `title`: kötelező, string
- `description`: opcionális, text
- `date`: kötelező, datetime, jövőbeli dátum
- `location`: kötelező, string
- `max_attendees`: kötelező, integer, minimum 1

#### PUT /api/events/{id}
Esemény módosítása (csak admin).

**Controller:** `EventController@update`

#### DELETE /api/events/{id}
Esemény törlése (csak admin, soft delete).

**Controller:** `EventController@destroy`

---

### 3.5 Registration végpontok

#### POST /api/events/{event}/register
Regisztráció egy eseményre.

**Controller:** `RegistrationController@register`

**Request:**
```http
POST /api/events/1/register
Authorization: Bearer {token}
```

**Success Response (201):**
```json
{
  "message": "Successfully registered for event",
  "registration": {
    "id": 15,
    "user_id": 5,
    "event_id": 1,
    "status": "függőben",
    "registered_at": "2026-01-14T12:30:00.000000Z"
  }
}
```

**Error Response (409):**
```json
{
  "message": "Already registered for this event"
}
```

**Error Response (422):**
```json
{
  "message": "Event is full"
}
```

#### DELETE /api/events/{event}/unregister
Regisztráció törlése (lemondás).

**Controller:** `RegistrationController@unregister`

**Request:**
```http
DELETE /api/events/1/unregister
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "message": "Registration cancelled successfully"
}
```

#### DELETE /api/events/{event}/users/{user}
Felhasználó eltávolítása egy eseményről (csak admin).

**Controller:** `RegistrationController@adminRemoveUser`

**Request:**
```http
DELETE /api/events/1/users/5
Authorization: Bearer {token}
```

---

## Autentikáció és authorizáció

### 4.1 Laravel Sanctum

A projekt Laravel Sanctum-ot használ Bearer token alapú autentikációhoz.

**Token generálás:**
```php
$token = $user->createToken('api-token')->plainTextToken;
```

**Token használata:**
```http
Authorization: Bearer {token}
```

**Token törlés (logout):**
```php
$user->currentAccessToken()->delete();
```

### 4.2 Middleware védelem

**Fájl:** `routes/api.php`

```php
// Védett route-ok
Route::middleware('auth:sanctum')->group(function () {
    // Csak bejelentkezett felhasználók
});
```

### 4.3 Admin jogosultság

Az `is_admin` boolean mező határozza meg az admin jogosultságot a `users` táblában.

**Admin műveletek:**
- Események létrehozása, módosítása, törlése
- Felhasználók kezelése
- Regisztrációk adminisztrálása

**Implementáció javasolt middleware-rel:**
```php
// app/Http/Middleware/IsAdmin.php
public function handle($request, Closure $next)
{
    if (!auth()->user()->is_admin) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }
    return $next($request);
}
```

---

## Telepítés és konfiguráció

### 5.1 Követelmények

- PHP >= 8.2
- Composer
- MySQL/PostgreSQL
- Node.js & NPM (frontend assets-hez)

### 5.2 Telepítési lépések

1. **Repository klónozása:**
```bash
git clone <repository-url>
cd eventRegistration-main
```

2. **Függőségek telepítése:**
```bash
composer install
npm install
```

3. **Környezeti változók beállítása:**
```bash
cp .env.example .env
php artisan key:generate
```

4. **.env konfiguráció:**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=event_registration
DB_USERNAME=root
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=localhost:3000
```

5. **Adatbázis migrációk futtatása:**
```bash
php artisan migrate
```

6. **Seeder futtatása (opcionális):**
```bash
php artisan db:seed
```

7. **Development szerver indítása:**
```bash
php artisan serve
```

API elérhető: `http://localhost:8000/api`

### 5.3 Sanctum konfiguráció

**Fájl:** `config/sanctum.php`

```php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost')),
'expiration' => null, // Token soha nem jár le (opcionális)
```

---

## Tesztelés

### 6.1 Unit tesztek

**Fájl:** `tests/Unit/ExampleTest.php`

```bash
php artisan test --filter Unit
```

### 6.2 Feature tesztek

**Fájl:** `tests/Feature/ExampleTest.php`

```bash
php artisan test --filter Feature
```

### 6.3 API tesztelés Postman-nel

1. Importáld a Postman collection-t (ha létezik)
2. Állítsd be a `{{base_url}}` változót: `http://localhost:8000/api`
3. Login után mentsd el a tokent egy változóba
4. Használd a tokent az Authorization headerben

### 6.4 Javasolt teszt esetek

**AuthController tesztek:**
- Sikeres regisztráció
- Duplikált email regisztráció
- Sikeres bejelentkezés
- Hibás jelszóval bejelentkezés
- Token generálás
- Kijelentkezés

**EventController tesztek:**
- Események listázása
- Esemény létrehozása (admin)
- Esemény módosítása (admin)
- Esemény törlése (admin)
- Jövőbeli események szűrése
- Múltbeli események szűrése

**RegistrationController tesztek:**
- Regisztráció eseményre
- Duplikált regisztráció tiltása
- Telt eseményre regisztráció tiltása
- Regisztráció lemondása
- Admin user eltávolítás

---

## Factory-k és Seeder-ek

### 7.1 User Factory

**Fájl:** `database/factories/UserFactory.php`

Generál random felhasználókat teszteléshez.

### 7.2 Event Factory

**Fájl:** `database/factories/EventFactory.php`

Generál random eseményeket teszteléshez.

### 7.3 Registration Factory

**Fájl:** `database/factories/RegistrationFactory.php`

Generál random regisztrációkat teszteléshez.

### 7.4 Database Seeder

**Fájl:** `database/seeders/DatabaseSeeder.php`

Futtatás:
```bash
php artisan db:seed
```

---

## Best Practices

### 8.1 Biztonság

- **Jelszavak:** Mindig bcrypt hashelés
- **Token védelem:** Sanctum token biztonságos tárolása
- **CORS:** Megfelelő CORS beállítás production-ben
- **Validáció:** Minden input validálása
- **SQL Injection:** Eloquent ORM használata

### 8.2 Kód struktúra

- **Controller:** Csak routing logika, validáció
- **Model:** Adatbázis kapcsolatok, cast-ok
- **Service osztályok:** Üzleti logika (javasolt)
- **Request osztályok:** Form validáció (javasolt)

### 8.3 API Design

- RESTful konvenciók követése
- Konzisztens HTTP status kódok
- JSON response formátum
- Pagination nagy listák esetén
- API verziózás (v1, v2)

### 8.4 Hibaüzenetek

**Konzisztens formátum:**
```json
{
  "message": "Error message",
  "errors": {
    "field": ["Validation error"]
  }
}
```

---

## Továbbfejlesztési lehetőségek

1. **Email értesítések:** Regisztráció megerősítő emailek
2. **Pagination:** Nagy listák lapozása
3. **Rate limiting:** API hívások korlátozása
4. **Queue kezelés:** Aszinkron feladatok (emailek)
5. **File upload:** Esemény képek feltöltése
6. **Search:** Teljes szöveges keresés eseményekben
7. **Notifications:** Push értesítések
8. **Export:** CSV/Excel export regisztrációkból
9. **Statistics:** Dashboard statisztikákkal
10. **Multi-language:** Többnyelvű támogatás

---

## Kapcsolat és támogatás

- **Repository:** <repository-url>
- **Issues:** <issues-url>
- **Laravel Dokumentáció:** https://laravel.com/docs
- **Sanctum Dokumentáció:** https://laravel.com/docs/sanctum

---

## Licensz

Ez a projekt Laravel alapú, ami MIT licensz alatt áll.

---

**Verzió:** 1.0  
**Utolsó frissítés:** 2026-01-14  
**Laravel verzió:** 11.x
