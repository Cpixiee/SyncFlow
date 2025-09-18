# SyncFlow API Testing Guide

## Overview
Testing suite lengkap untuk API SyncFlow yang mencakup unit tests dan feature tests untuk memastikan semua functionality bekerja sesuai dengan business logic yang diharapkan.

## Test Structure

### 1. Unit Tests (`tests/Unit/`)
- **LoginUserModelTest.php**: Test model LoginUser dan semua method-methodnya
- **ApiAuthenticateMiddlewareTest.php**: Test middleware authentication JWT
- **CheckRoleMiddlewareTest.php**: Test middleware role permission

### 2. Feature Tests (`tests/Feature/`)
- **Auth/LoginApiTest.php**: Test endpoint `/api/v1/login`
- **Auth/CreateUserApiTest.php**: Test endpoint `/api/v1/create-user`
- **Auth/ChangePasswordApiTest.php**: Test endpoint `/api/v1/change-password`
- **Auth/AuthenticatedApiTest.php**: Test endpoints yang memerlukan authentication (`/me`, `/logout`, `/refresh`)
- **RolePermissionTest.php**: Test role-based permissions (operator, admin, superadmin)
- **PasswordChangeLogicTest.php**: Test business logic untuk `must_change_password`

## Business Logic Coverage

### 1. Login API (`/api/v1/login`)
✅ **Tested scenarios:**
- Login dengan credentials yang valid
- Login dengan credentials yang invalid  
- Login user baru yang `must_change_password = true`
- Login user yang sudah ganti password (`must_change_password = false`)
- Validasi field required dan format
- Login untuk semua role (operator, admin, superadmin)
- Response structure dan data yang benar

### 2. Create User API (`/api/v1/create-user`)
✅ **Tested scenarios:**
- Hanya superadmin yang bisa create user
- Admin dan operator tidak bisa create user
- User baru dibuat dengan `must_change_password = true`
- Default password `admin#1234` jika tidak ada password
- Custom password tetap `must_change_password = true`
- Validasi unique username, employee_id, email
- Validasi enum role dan position
- Response structure yang benar

### 3. Change Password API (`/api/v1/change-password`)
✅ **Tested scenarios:**
- User bisa ganti password dengan current password yang benar
- Setelah ganti password, `must_change_password = false`
- `password_changed_at` timestamp di-set
- Validasi current password salah
- Validasi new password sama dengan current
- Validasi password confirmation
- Semua role bisa ganti password mereka sendiri

### 4. Role Permissions
✅ **Tested scenarios:**
- **Operator**: Bisa login, access basic endpoints, tidak bisa create user
- **Admin**: Bisa login, access basic endpoints, bisa CRUD, tidak bisa create user  
- **Superadmin**: Bisa login, access semua endpoints, bisa create user

### 5. Must Change Password Logic
✅ **Tested scenarios:**
- User baru memiliki `must_change_password = true`
- Setelah ganti password pertama kali, jadi `false`
- Login response menunjukkan status `must_change_password`
- Flow lengkap dari create user → login → change password → login lagi
- Superadmin tidak perlu ganti password (sudah di-seed dengan `password_changed = true`)

## How to Run Tests

### Menjalankan Semua Tests
```bash
# Dari root directory project
php artisan test

# Atau dengan composer
composer test
```

### Menjalankan Tests Berdasarkan Kategori

#### Unit Tests Only
```bash
php artisan test tests/Unit
```

#### Feature Tests Only  
```bash
php artisan test tests/Feature
```

#### Specific Test File
```bash
php artisan test tests/Feature/Auth/LoginApiTest.php
```

#### Specific Test Method
```bash
php artisan test --filter=it_can_login_with_valid_credentials
```

### Menjalankan Tests dengan Coverage
```bash
php artisan test --coverage
```

### Menjalankan Tests dengan Output Detail
```bash
php artisan test --verbose
```

## Test Database Configuration

Tests menggunakan konfigurasi berikut (sudah di-setup di `phpunit.xml`):
- **Database**: SQLite in-memory (`:memory:`)
- **Migrations**: Auto-refresh untuk setiap test
- **Seeding**: Auto-seed superadmin untuk test setup

## Test Helpers & Utilities

### Custom TestCase Methods
- `createSuperAdmin()`: Create test superadmin user
- `authenticateAs($user)`: Generate JWT token untuk user
- `actingAsUser($user)`: Set authorization header untuk request
- `assertApiResponseStructure()`: Assert response format API
- `assertApiSuccess()`: Assert successful API response  
- `assertApiError()`: Assert error API response

### Factory States
- `LoginUser::factory()->operator()`: Create operator user
- `LoginUser::factory()->admin()`: Create admin user  
- `LoginUser::factory()->superadmin()`: Create superadmin user
- `LoginUser::factory()->mustChangePassword()`: Create user yang harus ganti password
- `LoginUser::factory()->passwordChanged()`: Create user yang sudah ganti password
- `LoginUser::factory()->defaultPassword()`: Create user dengan default password

## Expected Test Results

Ketika semua tests dijalankan, harusnya:
- **Total Tests**: ~100+ test methods
- **Assertions**: ~300+ assertions
- **Coverage**: Model, Controller, Middleware business logic
- **Pass Rate**: 100% (semua tests harus pass)

## Testing Best Practices

1. **Isolation**: Setiap test independent, tidak depend pada test lain
2. **Data Setup**: Menggunakan factories untuk create test data
3. **Cleanup**: Database di-refresh otomatis untuk setiap test
4. **Realistic Scenarios**: Test scenarios yang mirror real-world usage
5. **Edge Cases**: Test validation, error cases, boundary conditions
6. **Role-based Testing**: Test semua kombinasi role permissions

## Troubleshooting

### Common Issues:

1. **Database Connection Error**
   ```bash
   # Pastikan SQLite extension enabled
   php -m | grep sqlite
   ```

2. **JWT Secret Not Set**
   ```bash
   # Generate JWT secret
   php artisan jwt:secret
   ```

3. **Migration Issues**
   ```bash
   # Reset migration state
   php artisan migrate:fresh --env=testing
   ```

4. **Factory Issues**
   ```bash
   # Clear cache
   php artisan config:clear
   composer dump-autoload
   ```

## CI/CD Integration

Tests bisa diintegrasikan dengan CI/CD pipeline:

```yaml
# .github/workflows/test.yml example
- name: Run Tests
  run: |
    php artisan config:clear
    php artisan test --coverage
```

## Maintenance

Tests harus di-update ketika:
- Menambah endpoint baru
- Mengubah business logic
- Menambah role atau permission baru
- Mengubah response format
- Menambah validation rules baru

