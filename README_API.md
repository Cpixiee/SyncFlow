# SyncFlow API - Login Authentication System

## 📋 Overview
API REST untuk sistem autentikasi login menggunakan JWT (JSON Web Token) dengan Laravel 11. API ini dirancang khusus untuk aplikasi Flutter dengan header HTTP yang optimal.

## 🚀 Fitur yang Sudah Diimplementasi

### ✅ Completed Features:
1. **JWT Authentication** - Token-based authentication
2. **Login User Model** - Tabel dan model untuk user dengan role-based access
3. **API Response Standardization** - Format response yang konsisten
4. **CORS Configuration** - Header yang optimal untuk Flutter
5. **Docker Ready** - Dockerfile dan docker-compose untuk deployment
6. **Role-based Authorization** - Operator, Admin, SuperAdmin roles

## 🛡️ User Roles
- **Operator**: Hanya melakukan operasi dasar
- **Admin**: CRUD operations
- **SuperAdmin**: Full access

## 📊 Database Schema

### Table: `login_users`
```sql
id - Primary Key (Auto Increment)
username - VARCHAR (Required)
password - VARCHAR (Hashed, Required)
role - ENUM('operator', 'admin', 'superadmin')
photo_url - VARCHAR (Nullable)
employee_id - VARCHAR (Required)
phone - VARCHAR (Required)
email - VARCHAR (Required)
position - ENUM('manager', 'staff', 'supervisor')
department - VARCHAR (Required)
created_at - DATETIME (Auto)
updated_at - DATETIME (Auto)
```

## 🔗 API Endpoints

### Base URL
```
Local: http://localhost:8000/api/v1
Production: http://103.236.140.19:2020/api/v1
Domain: http://infinity.antix.or.id:2020/api/v1
```

### Endpoints

#### 1. **POST** `/login`
Login user dan mendapatkan JWT token

**Request Body:**
```json
{
  "username": "string",
  "password": "string"
}
```

**Success Response (200):**
```json
{
  "http_code": 200,
  "message": "Login successful",
  "errorId": null,
  "data": {
    "id": 1,
    "username": "wit urrohman",
    "role": "operator",
    "photo_url": "https://example.com/photos/pixiee.jpg",
    "employee_id": "101233948893",
    "phone": "+628123456789",
    "email": "salwit0109@gmail.com",
    "position": "manager",
    "department": "IT",
    "created_at": "2025-09-15 20:30:00",
    "updated_at": "2025-09-15 20:30:00",
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "token_type": "Bearer",
    "expires_in": 86400
  }
}
```

#### 2. **GET** `/me` (Protected)
Mendapatkan data user yang sedang login

**Headers:**
```
Authorization: Bearer {token}
```

#### 3. **POST** `/logout` (Protected)
Logout user dan invalidate token

#### 4. **POST** `/refresh` (Protected)
Refresh JWT token

## 🔧 HTTP Headers untuk Flutter

### Request Headers (yang harus dikirim Flutter):
```dart
{
  'Accept': 'application/json',
  'Content-Type': 'application/json',
  'Authorization': 'Bearer {token}', // untuk protected routes
}
```

### Response Headers (yang dikirim API):
```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
Access-Control-Expose-Headers: Authorization
Access-Control-Allow-Credentials: true
```

## 📝 Response Format

### Success Response:
```json
{
  "http_code": 200,
  "message": "Success",
  "errorId": null,
  "data": {...}
}
```

### Error Responses:

#### Validation Error (400):
```json
{
  "http_code": 400,
  "message": "Request invalid",
  "errorId": "ERR_UNIQUE_ID",
  "data": null
}
```

#### Unauthorized (401):
```json
{
  "http_code": 401,
  "message": "Unauthorized",
  "errorId": "ERR_UNIQUE_ID",
  "data": null
}
```

#### Not Found (404):
```json
{
  "http_code": 404,
  "message": "Data not found",
  "errorId": "ERR_UNIQUE_ID",
  "data": null
}
```

#### Server Error (500):
```json
{
  "http_code": 500,
  "message": "Unknown error occurred",
  "errorId": "ERR_UNIQUE_ID",
  "data": null
}
```

## 🧪 Test Users

Data test user yang sudah tersedia:

1. **SuperAdmin**
   - Username: `superadmin`
   - Password: `password123`
   - Role: `superadmin`

2. **Admin**
   - Username: `admin`
   - Password: `password123`
   - Role: `admin`

3. **Operator**
   - Username: `operator`
   - Password: `password123`
   - Role: `operator`

4. **Wit Urrohman (Sample)**
   - Username: `wit urrohman`
   - Password: `password123`
   - Role: `operator`

## 🐳 Docker Deployment

### Local Development
```bash
# Install dependencies
composer install

# Setup environment
cp .env.example .env
php artisan key:generate
php artisan jwt:secret

# Run migrations and seeder
php artisan migrate
php artisan db:seed --class=LoginUserSeeder

# Start development server
php artisan serve --host=0.0.0.0 --port=8000
```

### Production with Docker
```bash
# Build and run with docker-compose
docker-compose up -d

# The API will be available at:
# http://103.236.140.19:2020
# http://infinity.antix.or.id:2020
```

## 📱 Flutter Integration Example

```dart
// Login request
Future<Map<String, dynamic>> login(String username, String password) async {
  final response = await http.post(
    Uri.parse('http://103.236.140.19:2020/api/v1/login'),
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    },
    body: jsonEncode({
      'username': username,
      'password': password,
    }),
  );
  
  return jsonDecode(response.body);
}

// Protected request with token
Future<Map<String, dynamic>> getProfile(String token) async {
  final response = await http.get(
    Uri.parse('http://103.236.140.19:2020/api/v1/me'),
    headers: {
      'Accept': 'application/json',
      'Authorization': 'Bearer $token',
    },
  );
  
  return jsonDecode(response.body);
}
```

## 🔧 Development Commands

```bash
# Install JWT package
composer require tymon/jwt-auth

# Publish JWT config
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"

# Generate JWT secret
php artisan jwt:secret

# Create migration
php artisan make:migration create_login_users_table

# Create model
php artisan make:model LoginUser

# Create controller
php artisan make:controller Api/V1/AuthController

# Create seeder
php artisan make:seeder LoginUserSeeder

# Run migrations
php artisan migrate

# Run specific seeder
php artisan db:seed --class=LoginUserSeeder
```

## 🚨 Security Notes

1. **JWT Secret**: Sudah di-generate secara otomatis
2. **Password Hashing**: Menggunakan bcrypt
3. **CORS**: Sudah dikonfigurasi untuk Flutter
4. **Token Expiry**: Default 24 jam (1440 menit)
5. **Environment**: Production ready dengan .env.example.production

## 📞 Support

Jika ada pertanyaan atau issue, silakan hubungi developer.

---
**Status**: ✅ Ready for Production
**Last Updated**: September 15, 2025
**Port**: 2020 (Production)
