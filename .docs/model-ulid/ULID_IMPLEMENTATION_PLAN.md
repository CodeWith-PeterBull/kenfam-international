# ULID Implementation Plan for QPIMS
## Quest Pinnacle Investment Management System

**Document Version:** 1.0
**Created:** 2026-04-21
**Status:** Planning Phase
**Architecture Decision:** Separate ULID Column (NOT Primary Key)

---

## Executive Summary

This document outlines the comprehensive plan to add ULID (Universally Unique Lexicographically Sortable Identifier) columns to QPIMS models for route obfuscation and enhanced security. Following the proven architectural pattern, **ULIDs will be implemented as separate indexed columns**, not as primary keys, to maintain compatibility with:

- Third-party packages (especially `plank/laravel-mediable` with polymorphic relationships)
- Existing foreign key relationships
- Type hinting and code clarity
- Laravel's route model binding conventions

---

## Table of Contents

1. [Architectural Decision](#architectural-decision)
2. [Codebase Context](#codebase-context)
3. [Implementation Strategy](#implementation-strategy)
4. [Priority Matrix](#priority-matrix)
5. [Model-by-Model Implementation Plan](#model-by-model-implementation-plan)
6. [Migration Strategy](#migration-strategy)
7. [Testing Strategy](#testing-strategy)
8. [Rollout Timeline](#rollout-timeline)
9. [Risk Assessment](#risk-assessment)
10. [Success Criteria](#success-criteria)

---

## Architectural Decision

### ✅ CORRECT APPROACH: Separate ULID Column

**Pattern:**
```php
// Model
class User extends Authenticatable
{
    // Keep integer primary key (default)
    protected $keyType = 'int';
    public $incrementing = true;

    // Add ULID to fillable
    protected $fillable = [..., 'ulid'];

    // Auto-generate ULID on creation
    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }
}

// Migration
Schema::table('users', function (Blueprint $table) {
    $table->string('ulid', 26)->unique()->index()->after('id');
});

// Routes - Laravel's route model binding
Route::get('/users/{user:ulid}', [UserController::class, 'show']);

// Controller - No changes needed
public function show(User $user)
{
    // $user automatically resolved via ULID
    return view('users.show', compact('user'));
}
```

### ❌ INCORRECT APPROACH: ULID as Primary Key

**Why This Fails:**
1. **Package Incompatibility**: `plank/laravel-mediable` uses `morphs()` which expects `unsignedBigInteger` for polymorphic IDs
2. **Foreign Key Complexity**: All foreign keys must be converted from `foreignId()` to `string()` + manual constraints
3. **Type Hinting Issues**: Services need union types `string|int|null` instead of clean `int`
4. **Livewire Complexity**: Route model binding breaks, requires manual `findOrFail()` calls
5. **Migration Risk**: High risk of breaking existing relationships

### Why Separate Column is Superior

| Aspect | ULID as PK | Separate ULID Column |
|--------|-----------|---------------------|
| Package Compatibility | ❌ Breaks mediable | ✅ Full compatibility |
| Foreign Keys | ❌ Complex migration | ✅ No changes needed |
| Type Hints | ❌ Union types needed | ✅ Clean type hints |
| Route Binding | ❌ Requires manual work | ✅ Laravel handles it |
| Code Changes | ❌ Extensive | ✅ Minimal |
| Rollback | ❌ Difficult | ✅ Simple |

---

## Codebase Context

### Current Technology Stack

```json
{
  "framework": "Laravel 12.42.0",
  "php": "8.2.12",
  "database": "MySQL",
  "authentication": "Laravel Breeze + Custom 2FA",
  "api_auth": "Laravel Sanctum",
  "permissions": "Spatie Laravel Permission",
  "media": "Plank Laravel Mediable",
  "frontend": "Blade + Livewire (partial)"
}
```

### Key Architectural Components

1. **User Types (Enum-based)**
   - `Investor` - Human investors accessing via web
   - `Admin` - Administrators with elevated permissions
   - `ServiceApi` - Machine identities for API integrations (Equity Bank, etc.)

2. **Authentication Flow**
   - Web: Laravel Breeze + Custom 2FA
   - API: Laravel Sanctum Personal Access Tokens
   - Middleware: `two-factor`, `redirect.role`, `role:*`

3. **Critical Package Dependencies**
   - `plank/laravel-mediable` - Uses polymorphic relationships (`mediable_id`, `mediable_type`)
   - `spatie/laravel-permission` - Role/permission system
   - `laravel/sanctum` - API token management

4. **Current Route Structure**
   ```php
   // Resource routes using integer IDs
   Route::resource('users', UserController::class);
   Route::resource('investments', InvestmentController::class);
   Route::resource('contributions', ContributionController::class);
   Route::resource('dividends', DividendController::class);
   Route::resource('investment-categories', InvestmentCategoryController::class);

   // Service API routes with custom parameter name
   Route::get('/service-api/{serviceUser}', [ServiceTokenController::class, 'show']);
   Route::get('/service-api/{serviceUser}/edit', [ServiceTokenController::class, 'edit']);
   ```

5. **Models with Foreign Key Relationships**
   ```
   User (id) → hasMany
   ├── Contribution (user_id)
   ├── Dividend (user_id)
   ├── Beneficiary (user_id)
   ├── BankAccount (user_id)
   ├── Activity (user_id)
   ├── TwoFactorAttempt (user_id)
   ├── TwoFactorSession (user_id)
   └── ServiceApiProfile (user_id)

   Investment (id) → hasMany
   └── Contribution (investment_id)

   InvestmentCategory (id) → hasMany
   └── Investment (investment_category_id)
   ```

6. **Polymorphic Relationships (Mediable)**
   ```php
   // Users can have profile pictures, ID documents, attachments
   User::attachMedia($media, 'profile_picture');
   User::attachMedia($media, 'id_passport_media');
   User::attachMedia($media, 'attachment');

   // Stored as:
   // mediable_type: 'App\Models\User'
   // mediable_id: 123 (integer user ID)
   ```

---

## Implementation Strategy

### Phase-Based Rollout

We'll implement ULID in **4 phases** to minimize risk and ensure thorough testing at each stage.

```
Phase 1: Foundation (User Model)
   ↓
Phase 2: Investment System (Investment, InvestmentCategory, Contribution)
   ↓
Phase 3: Financial Operations (Dividend, BankAccount, Beneficiary)
   ↓
Phase 4: Supporting Models (Corporate, Director, ServiceApiProfile)
```

### Core Implementation Steps (Per Model)

Each model implementation follows these steps:

1. **Create Migration**
   - Add `ulid` column (string, 26 chars, unique, indexed)
   - Position after `id` column for clarity
   - Generate ULIDs for existing records

2. **Update Model**
   - Add `ulid` to `$fillable` array
   - Add auto-generation in `booted()` method
   - Add helper methods if needed

3. **Update Routes**
   - Change from `/{model}` to `/{model:ulid}`
   - Update any route names if necessary
   - Verify route model binding works

4. **Update Views**
   - Replace `{{ $model->id }}` with `{{ $model->ulid }}` in route generation
   - Update `route()` helper calls
   - Check Livewire components if applicable

5. **Testing**
   - Unit tests for ULID generation
   - Feature tests for route binding
   - Manual testing of CRUD operations
   - Verify polymorphic relationships still work

6. **Documentation**
   - Update API documentation if applicable
   - Document route changes
   - Update developer notes

---

## Priority Matrix

### Priority 1: Critical (Immediate Security Benefit) 🔴

| Model | Route Exposure | Security Risk | User Visibility |
|-------|---------------|---------------|-----------------|
| **User** | High (public profiles, admin CRUD) | **Critical** | High |
| **ServiceApiProfile** | Medium (admin only, via serviceUser) | High | Medium |

**Rationale:** User IDs are exposed in multiple routes and can leak sensitive information about user count, registration patterns, and account structure. Service API profiles contain sensitive integration details.

### Priority 2: High (Financial & Sensitive Data) 🟠

| Model | Route Exposure | Data Sensitivity | Business Impact |
|-------|---------------|------------------|-----------------|
| **Investment** | High (admin + investor views) | High | High |
| **Contribution** | High (admin CRUD, investor view) | **Critical** | High |
| **Dividend** | High (admin CRUD, investor view) | High | High |

**Rationale:** These models contain financial data and transaction history. Obfuscating IDs prevents attackers from enumerating contributions, inferring investment amounts, or analyzing payment patterns.

### Priority 3: Medium (Business Operations) 🟡

| Model | Route Exposure | Data Sensitivity | Business Impact |
|-------|---------------|------------------|-----------------|
| **InvestmentCategory** | Medium (admin only) | Medium | Medium |
| **Beneficiary** | Medium (investor + admin) | High (PII) | Medium |
| **BankAccount** | Medium (investor + admin) | High (Financial) | Medium |

**Rationale:** Less frequently accessed via direct routes, but still contain sensitive data. Lower enumeration risk but high data sensitivity.

### Priority 4: Low (Supporting Data) 🟢

| Model | Route Exposure | Data Sensitivity | Business Impact |
|-------|---------------|------------------|-----------------|
| **Corporate** | Low (admin only) | Medium | Low |
| **Director** | Low (admin only) | Medium | Low |
| **Activity** | None (internal logging) | Low | Low |

**Rationale:** Minimal route exposure, primarily admin-facing, or internal system records.

---

## Model-by-Model Implementation Plan

### Phase 1: User Model (PRIORITY 1)

**Estimated Effort:** 3-4 hours
**Risk Level:** Medium (high usage, but well-tested pattern)

#### Current State Analysis

**Model:** `app/Models/User.php`
- Primary Key: `id` (integer, auto-increment)
- Route Parameter: `{user}`
- Relationships: 10+ (hasMany, hasOne, belongsTo)
- Polymorphic Media: Yes (profile_picture, id_passport_media, attachment)
- Soft Deletes: Yes
- Traits: `HasApiTokens`, `HasFactory`, `HasRoles`, `Mediable`, `Notifiable`, `SoftDeletes`

**Routes Affected:**
```php
// Resource routes
Route::resource('users', UserController::class);
// Generates:
// GET    /users/{user}           → show
// GET    /users/{user}/edit      → edit
// PATCH  /users/{user}           → update
// DELETE /users/{user}           → destroy

// Service API routes (indirect - serviceUser is a User model)
Route::get('/service-api/{serviceUser}', [ServiceTokenController::class, 'show']);
Route::get('/service-api/{serviceUser}/edit', [ServiceTokenController::class, 'edit']);
Route::patch('/service-api/{serviceUser}', [ServiceTokenController::class, 'update']);
```

**Controllers Affected:**
- `app/Http/Controllers/UserController.php` (show, edit, update, destroy)
- `app/Http/Controllers/ServiceApi/ServiceTokenController.php` (show, edit, update)
- `app/Http/Controllers/ProfileController.php` (edit, update)

**Views Affected:**
```
resources/views/users/
├── index.blade.php       → Links to show/edit with user->id
├── show.blade.php        → Edit/delete buttons with user->id
├── edit.blade.php        → Form action with user->id
└── create.blade.php      → No changes needed

resources/views/service-api/
├── index.blade.php       → Links with serviceUser->id
├── show.blade.php        → Edit button with serviceUser->id
└── edit.blade.php        → Form action with serviceUser->id

resources/views/profile/
└── edit.blade.php        → Uses auth()->user(), no explicit ID
```

#### Implementation Steps

**Step 1: Create Migration**

File: `database/migrations/2026_04_21_HHMMSS_add_ulid_to_users_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Add ULID column to users table for secure route binding.
     *
     * Architecture Decision: ULID as separate column, NOT primary key.
     * This maintains compatibility with packages expecting integer PKs
     * (especially plank/laravel-mediable polymorphic relationships).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add ULID column after id
            $table->string('ulid', 26)
                ->unique()
                ->index()
                ->after('id');
        });

        // Generate ULIDs for existing users
        DB::table('users')->whereNull('ulid')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['ulid' => (string) Str::ulid()]);
            }
        });

        // Make ULID non-nullable after backfilling
        Schema::table('users', function (Blueprint $table) {
            $table->string('ulid', 26)->nullable(false)->change();
        });
    }

    /**
     * Remove ULID column from users table.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['ulid']);
            $table->dropIndex(['ulid']);
            $table->dropColumn('ulid');
        });
    }
};
```

**Step 2: Update User Model**

File: `app/Models/User.php`

```php
// Add to $fillable array (around line 45)
protected $fillable = [
    'ulid',  // Add this as first item for visibility
    'salutation',
    'name',
    'email',
    // ... rest of fields
];

// Add to booted() method (around line 251, or create if doesn't exist)
protected static function booted(): void
{
    // Auto-generate ULID on user creation
    static::creating(function (User $user) {
        if (empty($user->ulid)) {
            $user->ulid = (string) Str::ulid();
        }
    });

    // Existing soft delete cascades
    static::deleting(function (User $user): void {
        if (! $user->isForceDeleting()) {
            $user->contributions()->delete();
            $user->dividends()->delete();
            $user->activities()->delete();
        }
    });

    static::restoring(function (User $user): void {
        $user->contributions()->restore();
        $user->dividends()->restore();
        $user->activities()->restore();
    });
}

// Optional: Add helper method for route key name override
/**
 * Get the route key for the model.
 *
 * @return string
 */
public function getRouteKeyName(): string
{
    // This is optional - Laravel's {user:ulid} syntax is preferred
    // Only implement if you want /users/{user} to use ULID by default
    return 'ulid';
}
```

**Step 3: Update Routes**

File: `routes/web.php`

```php
// BEFORE
Route::resource('users', UserController::class);

// AFTER - Option A: Explicit ULID binding (RECOMMENDED)
Route::resource('users', UserController::class)->parameters([
    'users' => 'user:ulid'
]);

// AFTER - Option B: Individual route definitions (more control)
Route::controller(UserController::class)
    ->prefix('users')
    ->name('users.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{user:ulid}', 'show')->name('show');
        Route::get('/{user:ulid}/edit', 'edit')->name('edit');
        Route::patch('/{user:ulid}', 'update')->name('update');
        Route::delete('/{user:ulid}', 'destroy')->name('destroy');
    });

// Service API routes - change {serviceUser} to {serviceUser:ulid}
Route::prefix('service-api')
    ->name('service-api.')
    ->group(function (): void {
        Route::get('/', [ServiceTokenController::class, 'index'])->name('index');
        Route::get('/create', [ServiceTokenController::class, 'create'])->name('create');
        Route::post('/', [ServiceTokenController::class, 'store'])->name('store');

        // Change these routes
        Route::get('/{serviceUser:ulid}', [ServiceTokenController::class, 'show'])->name('show');
        Route::get('/{serviceUser:ulid}/edit', [ServiceTokenController::class, 'edit'])->name('edit');
        Route::patch('/{serviceUser:ulid}', [ServiceTokenController::class, 'update'])->name('update');

        // Token management routes
        Route::post('/{serviceUser:ulid}/tokens', [ServiceTokenController::class, 'issueToken'])
            ->name('tokens.issue');
        Route::delete('/{serviceUser:ulid}/tokens/{tokenId}', [ServiceTokenController::class, 'revokeToken'])
            ->name('tokens.revoke');
        Route::delete('/{serviceUser:ulid}/tokens', [ServiceTokenController::class, 'revokeAllTokens'])
            ->name('tokens.revoke-all');
    });
```

**Step 4: Update Controllers**

**No changes needed!** Laravel's route model binding automatically resolves the User model via ULID.

**Verify these methods work without changes:**
```php
// app/Http/Controllers/UserController.php
public function show(User $user) { } // ✅ Works automatically
public function edit(User $user) { } // ✅ Works automatically
public function update(Request $request, User $user) { } // ✅ Works automatically
public function destroy(User $user) { } // ✅ Works automatically

// app/Http/Controllers/ServiceApi/ServiceTokenController.php
public function show(User $serviceUser) { } // ✅ Works automatically
public function edit(User $serviceUser) { } // ✅ Works automatically
public function update(Request $request, User $serviceUser) { } // ✅ Works automatically
```

**Step 5: Update Views**

Search and replace pattern:

```blade
<!-- BEFORE -->
<a href="{{ route('users.show', $user->id) }}">

<!-- AFTER -->
<a href="{{ route('users.show', $user->ulid) }}">

<!-- OR (if using model directly - Laravel is smart) -->
<a href="{{ route('users.show', $user) }}">
```

**Files to update:**

1. `resources/views/users/index.blade.php`
   ```blade
   <!-- Action buttons in table -->
   <a href="{{ route('users.show', $user) }}">View</a>
   <a href="{{ route('users.edit', $user) }}">Edit</a>

   <form action="{{ route('users.destroy', $user) }}" method="POST">
       @csrf
       @method('DELETE')
       <button type="submit">Delete</button>
   </form>
   ```

2. `resources/views/users/show.blade.php`
   ```blade
   <!-- Edit button -->
   <a href="{{ route('users.edit', $user) }}">Edit Profile</a>

   <!-- Delete form -->
   <form action="{{ route('users.destroy', $user) }}" method="POST">
       @csrf
       @method('DELETE')
       <button type="submit">Delete Account</button>
   </form>
   ```

3. `resources/views/users/edit.blade.php`
   ```blade
   <!-- Form action -->
   <form action="{{ route('users.update', $user) }}" method="POST">
       @csrf
       @method('PATCH')
       <!-- form fields -->
   </form>

   <!-- Cancel button -->
   <a href="{{ route('users.show', $user) }}">Cancel</a>
   ```

4. `resources/views/service-api/index.blade.php`
   ```blade
   <!-- Links to service user profiles -->
   <a href="{{ route('service-api.show', $serviceUser) }}">View</a>
   <a href="{{ route('service-api.edit', $serviceUser) }}">Edit</a>
   ```

5. `resources/views/service-api/show.blade.php`
   ```blade
   <!-- Edit button -->
   <a href="{{ route('service-api.edit', $serviceUser) }}">Edit</a>

   <!-- Token forms -->
   <form action="{{ route('service-api.tokens.issue', $serviceUser) }}" method="POST">
   <form action="{{ route('service-api.tokens.revoke', [$serviceUser, $token->id]) }}" method="POST">
   <form action="{{ route('service-api.tokens.revoke-all', $serviceUser) }}" method="POST">
   ```

6. `resources/views/service-api/edit.blade.php`
   ```blade
   <!-- Form action -->
   <form action="{{ route('service-api.update', $serviceUser) }}" method="POST">
       @csrf
       @method('PATCH')
   </form>
   ```

**Step 6: Testing Checklist**

- [ ] Migration runs successfully: `php artisan migrate`
- [ ] Existing users have ULIDs: `User::whereNull('ulid')->count()` returns 0
- [ ] New user creation auto-generates ULID
- [ ] User show page loads via ULID: `/users/{ulid}`
- [ ] User edit page loads via ULID: `/users/{ulid}/edit`
- [ ] User update works via ULID
- [ ] User deletion works via ULID
- [ ] Service API routes work with ULID: `/service-api/{ulid}`
- [ ] Polymorphic media still works: User profile pictures load correctly
- [ ] Foreign key relationships intact: User->contributions() works
- [ ] Authentication still works: Login/logout/2FA
- [ ] Spatie roles/permissions still work
- [ ] Sanctum API tokens still work for service users

**Step 7: Rollback Plan**

If issues arise:

```bash
# Rollback migration
php artisan migrate:rollback --step=1

# Revert route changes
git checkout routes/web.php

# Revert model changes
git checkout app/Models/User.php

# Revert views
git checkout resources/views/users/
git checkout resources/views/service-api/
```

---

### Phase 2: Investment System Models

#### 2.1 Investment Model (PRIORITY 2)

**Estimated Effort:** 2 hours
**Risk Level:** Low

**Current State:**
- Primary Key: `id` (integer)
- Route Parameter: `{investment}`
- Foreign Keys Used: None
- Foreign Keys Referenced By: `contributions.investment_id`
- Polymorphic Media: No
- Soft Deletes: Yes

**Routes Affected:**
```php
Route::resource('investments', InvestmentController::class);
// Generates: /investments/{investment}
```

**Implementation:**

1. **Migration:** `2026_04_21_HHMMSS_add_ulid_to_investments_table.php`
2. **Model:** Add ULID to `$fillable`, add `booted()` method
3. **Routes:** Change to `/{investment:ulid}`
4. **Views:** Update `resources/views/investments/*.blade.php`

**Testing Focus:**
- Investment CRUD operations
- Contribution relationship (foreign key `investment_id` should still use integer)
- Investment category relationship works

---

#### 2.2 InvestmentCategory Model (PRIORITY 3)

**Estimated Effort:** 2 hours
**Risk Level:** Low

**Current State:**
- Primary Key: `id` (integer)
- Route Parameter: `{investment_category}`
- Foreign Keys Used: None
- Foreign Keys Referenced By: `investments.investment_category_id`
- Polymorphic Media: No
- Soft Deletes: No

**Routes Affected:**
```php
Route::resource('investment-categories', InvestmentCategoryController::class);
// Generates: /investment-categories/{investment_category}
```

**Implementation:**

1. **Migration:** `2026_04_21_HHMMSS_add_ulid_to_investment_categories_table.php`
2. **Model:** Add ULID to `$fillable`, add `booted()` method
3. **Routes:** Change to `/{investment_category:ulid}`
4. **Views:** Update `resources/views/investment-categories/*.blade.php`

---

#### 2.3 Contribution Model (PRIORITY 2)

**Estimated Effort:** 2.5 hours
**Risk Level:** Low-Medium

**Current State:**
- Primary Key: `id` (integer)
- Route Parameter: `{contribution}`
- Foreign Keys Used: `user_id`, `investment_id`
- Foreign Keys Referenced By: None
- Polymorphic Media: No
- Soft Deletes: Yes
- Special Logic: `calculateUnitsPurchased()` in save() method

**Routes Affected:**
```php
Route::resource('contributions', ContributionController::class);
// Generates: /contributions/{contribution}
```

**Implementation:**

1. **Migration:** `2026_04_21_HHMMSS_add_ulid_to_contributions_table.php`
2. **Model:** Add ULID to `$fillable`, add ULID generation in `booted()`
3. **Routes:** Change to `/{contribution:ulid}`
4. **Views:** Update `resources/views/contributions/*.blade.php`

**Testing Focus:**
- Units calculation still works
- User relationship intact (foreign key remains integer)
- Investment relationship intact (foreign key remains integer)
- Equity Bank API integration (if contribution references use IDs)

---

### Phase 3: Financial Operations Models

#### 3.1 Dividend Model (PRIORITY 2)

**Estimated Effort:** 2 hours
**Risk Level:** Low

**Current State:**
- Primary Key: `id` (integer)
- Route Parameter: `{dividend}`
- Foreign Keys Used: `user_id`
- Foreign Keys Referenced By: None
- Polymorphic Media: No
- Soft Deletes: Yes

**Routes Affected:**
```php
Route::resource('dividends', DividendController::class);
// Generates: /dividends/{dividend}
```

**Implementation:**

1. **Migration:** `2026_04_21_HHMMSS_add_ulid_to_dividends_table.php`
2. **Model:** Add ULID to `$fillable`, add `booted()` method
3. **Routes:** Change to `/{dividend:ulid}`
4. **Views:** Update `resources/views/dividends/*.blade.php`

---

#### 3.2 Beneficiary Model (PRIORITY 3)

**Estimated Effort:** 1.5 hours
**Risk Level:** Low

**Current State:**
- Primary Key: `id` (integer)
- Route Parameter: `{beneficiary}` (if exposed)
- Foreign Keys Used: `user_id`
- Foreign Keys Referenced By: None
- Polymorphic Media: No
- Soft Deletes: Likely yes

**Routes Affected:**
- May be embedded in user profile routes
- Or dedicated: `Route::resource('beneficiaries', BeneficiaryController::class);`

**Implementation:**

1. **Migration:** `2026_04_21_HHMMSS_add_ulid_to_beneficiaries_table.php`
2. **Model:** Add ULID to `$fillable`, add `booted()` method
3. **Routes:** Change to `/{beneficiary:ulid}` if exposed
4. **Views:** Update relevant views

---

#### 3.3 BankAccount Model (PRIORITY 3)

**Estimated Effort:** 1.5 hours
**Risk Level:** Low

**Current State:**
- Primary Key: `id` (integer)
- Route Parameter: `{bank_account}` (if exposed)
- Foreign Keys Used: `user_id`
- Foreign Keys Referenced By: None
- Polymorphic Media: No
- Soft Deletes: Likely yes

**Routes Affected:**
- May be embedded in user profile routes
- Or dedicated: `Route::resource('bank-accounts', BankAccountController::class);`

**Implementation:**

1. **Migration:** `2026_04_21_HHMMSS_add_ulid_to_bank_accounts_table.php`
2. **Model:** Add ULID to `$fillable`, add `booted()` method
3. **Routes:** Change to `/{bank_account:ulid}` if exposed
4. **Views:** Update relevant views

---

### Phase 4: Supporting Models

#### 4.1 ServiceApiProfile Model (PRIORITY 1)

**Estimated Effort:** 1.5 hours
**Risk Level:** Low

**Current State:**
- Primary Key: `id` (integer)
- Route Access: Via `serviceUser` (User model)
- Foreign Keys Used: `user_id`
- Foreign Keys Referenced By: None
- Polymorphic Media: No
- Soft Deletes: No

**Routes Affected:**
```php
// Accessed via User (serviceUser parameter)
Route::get('/service-api/{serviceUser:ulid}', ...);
```

**Implementation:**

1. **Migration:** `2026_04_21_HHMMSS_add_ulid_to_service_api_profiles_table.php`
2. **Model:** Add ULID to `$fillable`, add `booted()` method
3. **Routes:** Already handled via User ULID
4. **Views:** If profile needs direct access, update views

**Note:** ServiceApiProfile is accessed through User model in current implementation. Direct ULID access may not be needed unless direct routes are added later.

---

#### 4.2 Corporate Model (PRIORITY 4)

**Estimated Effort:** 1 hour
**Risk Level:** Low

**Current State:**
- Primary Key: `id` (integer)
- Route Parameter: `{corporate}` (if exposed)
- Foreign Keys: Unknown (needs investigation)
- Polymorphic Media: Unknown
- Soft Deletes: Unknown

**Implementation:**
- Investigate current usage
- Follow standard ULID pattern if routes exist

---

#### 4.3 Director Model (PRIORITY 4)

**Estimated Effort:** 1 hour
**Risk Level:** Low

**Current State:**
- Primary Key: `id` (integer)
- Route Parameter: `{director}` (if exposed)
- Foreign Keys: Unknown (needs investigation)
- Polymorphic Media: Unknown
- Soft Deletes: Unknown

**Implementation:**
- Investigate current usage
- Follow standard ULID pattern if routes exist

---

## Migration Strategy

### Migration Naming Convention

```
2026_04_21_[sequence]_add_ulid_to_[table_name]_table.php
```

**Sequence:**
- `100000` - users (Phase 1)
- `110000` - investments (Phase 2)
- `110100` - investment_categories (Phase 2)
- `110200` - contributions (Phase 2)
- `120000` - dividends (Phase 3)
- `120100` - beneficiaries (Phase 3)
- `120200` - bank_accounts (Phase 3)
- `130000` - service_api_profiles (Phase 4)
- `130100` - corporates (Phase 4)
- `130200` - directors (Phase 4)

### Migration Template

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Add ULID column to [table] for secure route binding.
     *
     * Architecture Decision: ULID as separate column, NOT primary key.
     */
    public function up(): void
    {
        // 1. Add ULID column (nullable initially for backfill)
        Schema::table('[table]', function (Blueprint $table) {
            $table->string('ulid', 26)
                ->unique()
                ->index()
                ->after('id');
        });

        // 2. Generate ULIDs for existing records
        DB::table('[table]')->whereNull('ulid')->chunkById(100, function ($records) {
            foreach ($records as $record) {
                DB::table('[table]')
                    ->where('id', $record->id)
                    ->update(['ulid' => (string) Str::ulid()]);
            }
        });

        // 3. Make ULID non-nullable after backfill
        Schema::table('[table]', function (Blueprint $table) {
            $table->string('ulid', 26)->nullable(false)->change();
        });
    }

    /**
     * Remove ULID column.
     */
    public function down(): void
    {
        Schema::table('[table]', function (Blueprint $table) {
            $table->dropUnique(['[table]_ulid_unique']);
            $table->dropIndex(['[table]_ulid_index']);
            $table->dropColumn('ulid');
        });
    }
};
```

### Migration Execution Order

**Phase 1:**
```bash
php artisan make:migration add_ulid_to_users_table
php artisan migrate
# Test thoroughly before proceeding
```

**Phase 2:**
```bash
php artisan make:migration add_ulid_to_investments_table
php artisan make:migration add_ulid_to_investment_categories_table
php artisan make:migration add_ulid_to_contributions_table
php artisan migrate
# Test investment system thoroughly
```

**Phase 3:**
```bash
php artisan make:migration add_ulid_to_dividends_table
php artisan make:migration add_ulid_to_beneficiaries_table
php artisan make:migration add_ulid_to_bank_accounts_table
php artisan migrate
# Test financial operations
```

**Phase 4:**
```bash
php artisan make:migration add_ulid_to_service_api_profiles_table
php artisan make:migration add_ulid_to_corporates_table
php artisan make:migration add_ulid_to_directors_table
php artisan migrate
# Test supporting features
```

### Rollback Strategy

Each phase can be rolled back independently:

```bash
# Rollback entire phase
php artisan migrate:rollback --step=3  # Phase with 3 migrations

# Rollback specific migration
php artisan migrate:rollback --path=database/migrations/2026_04_21_100000_add_ulid_to_users_table.php
```

---

## Testing Strategy

### Unit Tests

Create test class per model:

```php
// tests/Unit/Models/UserUlidTest.php
namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserUlidTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_generates_ulid_on_user_creation()
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->ulid);
        $this->assertEquals(26, strlen($user->ulid));
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $user->ulid);
    }

    /** @test */
    public function it_does_not_override_provided_ulid()
    {
        $customUlid = '01HQ7ZQCR5XPMJ4GKZW8B2VN7E';

        $user = User::factory()->create([
            'ulid' => $customUlid,
        ]);

        $this->assertEquals($customUlid, $user->ulid);
    }

    /** @test */
    public function ulid_is_unique_across_users()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->assertNotEquals($user1->ulid, $user2->ulid);
    }

    /** @test */
    public function user_can_be_found_by_ulid()
    {
        $user = User::factory()->create();

        $found = User::where('ulid', $user->ulid)->first();

        $this->assertTrue($user->is($found));
    }
}
```

### Feature Tests

Create test class per controller:

```php
// tests/Feature/Controllers/UserControllerUlidTest.php
namespace Tests\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerUlidTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_show_page_works_with_ulid()
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        $user = User::factory()->create();

        $response = $this->actingAs($admin)
            ->get(route('users.show', $user->ulid));

        $response->assertOk();
        $response->assertSee($user->name);
    }

    /** @test */
    public function user_edit_page_works_with_ulid()
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        $user = User::factory()->create();

        $response = $this->actingAs($admin)
            ->get(route('users.edit', $user->ulid));

        $response->assertOk();
        $response->assertSee($user->email);
    }

    /** @test */
    public function user_update_works_with_ulid()
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        $user = User::factory()->create();

        $response = $this->actingAs($admin)
            ->patch(route('users.update', $user->ulid), [
                'name' => 'Updated Name',
                'email' => $user->email,
                'is_active' => 1,
                'role' => 'investor',
            ]);

        $response->assertRedirect();
        $this->assertEquals('Updated Name', $user->fresh()->name);
    }

    /** @test */
    public function user_delete_works_with_ulid()
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        $user = User::factory()->create();

        $response = $this->actingAs($admin)
            ->delete(route('users.destroy', $user->ulid));

        $response->assertRedirect();
        $this->assertSoftDeleted($user);
    }

    /** @test */
    public function integer_id_route_fails_with_404()
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        $user = User::factory()->create();

        $response = $this->actingAs($admin)
            ->get(route('users.show', $user->id));

        $response->assertNotFound();
    }
}
```

### Integration Tests

Test critical workflows:

```php
// tests/Feature/Integration/ContributionFlowUlidTest.php
namespace Tests\Feature\Integration;

use App\Models\{User, Investment, Contribution};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContributionFlowUlidTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_create_contribution_via_ulid_routes()
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        $investor = User::factory()->create();
        $investor->assignRole('investor');

        $investment = Investment::factory()->create();

        // Create contribution via ULID route
        $response = $this->actingAs($admin)
            ->post(route('contributions.store'), [
                'user_id' => $investor->id,  // Foreign key still uses integer
                'investment_id' => $investment->id,  // Foreign key still uses integer
                'amount' => 10000,
                'contribution_date' => now(),
            ]);

        $response->assertRedirect();

        $contribution = Contribution::latest()->first();
        $this->assertNotNull($contribution->ulid);

        // View contribution via ULID
        $response = $this->actingAs($admin)
            ->get(route('contributions.show', $contribution->ulid));

        $response->assertOk();
    }

    /** @test */
    public function investor_can_view_own_contributions_via_ulid()
    {
        $investor = User::factory()->create();
        $investor->assignRole('investor');

        $investment = Investment::factory()->create();

        $contribution = Contribution::factory()->create([
            'user_id' => $investor->id,
            'investment_id' => $investment->id,
        ]);

        $response = $this->actingAs($investor)
            ->get(route('contributions.show', $contribution->ulid));

        $response->assertOk();
        $response->assertSee(number_format($contribution->amount, 2));
    }
}
```

### Manual Testing Checklist

**Per Model Implementation:**

- [ ] Migration runs without errors
- [ ] Existing records have ULIDs generated
- [ ] New records auto-generate ULIDs
- [ ] ULID is 26 characters (uppercase alphanumeric)
- [ ] ULID is unique across all records
- [ ] Show route works: `/model/{ulid}`
- [ ] Edit route works: `/model/{ulid}/edit`
- [ ] Update form works with ULID in action
- [ ] Delete form works with ULID
- [ ] Index page links use ULID
- [ ] Foreign key relationships still work
- [ ] Polymorphic media still works (User model)
- [ ] Soft deletes still work
- [ ] Integer ID routes return 404
- [ ] Direct database queries work: `Model::where('ulid', $ulid)->first()`

---

## Rollout Timeline

### Week 1: Foundation (Phase 1)

**Day 1-2: User Model Implementation**
- Create migration
- Update model
- Update routes
- Update controllers (verify no changes needed)
- Update views

**Day 3: Testing & Bug Fixes**
- Run unit tests
- Run feature tests
- Manual testing
- Fix any issues

**Day 4: Documentation & Code Review**
- Update API docs if applicable
- Document route changes
- Code review
- Merge to main branch

**Day 5: Buffer for Issues**

### Week 2: Investment System (Phase 2)

**Day 1: Investment Model**
- Migration + Model + Routes + Views
- Testing

**Day 2: InvestmentCategory Model**
- Migration + Model + Routes + Views
- Testing

**Day 3: Contribution Model**
- Migration + Model + Routes + Views
- Testing (pay attention to units calculation)

**Day 4: Integration Testing**
- Test investment creation → contribution flow
- Test investor dashboard
- Test admin reports

**Day 5: Buffer for Issues**

### Week 3: Financial Operations (Phase 3)

**Day 1: Dividend Model**
- Migration + Model + Routes + Views
- Testing

**Day 2: Beneficiary Model**
- Migration + Model + Routes + Views
- Testing

**Day 3: BankAccount Model**
- Migration + Model + Routes + Views
- Testing

**Day 4: Integration Testing**
- Test dividend disbursement flow
- Test beneficiary management
- Test bank account management

**Day 5: Buffer for Issues**

### Week 4: Supporting Models & Finalization (Phase 4)

**Day 1-2: ServiceApiProfile, Corporate, Director**
- Migrations + Models + Routes + Views
- Testing

**Day 3: Full System Integration Testing**
- Test all workflows end-to-end
- Performance testing (ULID lookup vs integer)
- Security audit

**Day 4: Documentation Finalization**
- Update all documentation
- Create developer guide for future models
- Update deployment procedures

**Day 5: Production Deployment**
- Deploy to staging environment
- Final smoke tests
- Deploy to production
- Monitor for issues

---

## Risk Assessment

### High Risk Areas

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| **Polymorphic relationships break** | Critical | Low | Use separate ULID column (not PK), test extensively with Mediable |
| **Foreign keys fail** | Critical | Very Low | Foreign keys remain integer-based, only route parameters use ULID |
| **Performance degradation** | Medium | Very Low | ULID lookup is indexed, <10ns difference from integer |
| **API clients break** | High | Medium | API routes may need versioning, document changes |
| **Search functionality breaks** | Medium | Low | Ensure search uses name/email, not ULID |

### Medium Risk Areas

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| **Old bookmarks fail** | Medium | High | Document URL changes, consider redirect strategy |
| **External links break** | Medium | Medium | Audit external integrations (email links, etc.) |
| **Reporting queries fail** | Medium | Low | Audit reporting queries, ensure they don't rely on sequential IDs |
| **Testing coverage gaps** | Medium | Medium | Write comprehensive tests per model |

### Low Risk Areas

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| **Migration rollback needed** | Low | Low | Clear rollback procedures documented |
| **ULID collisions** | Critical | Extremely Low | ULID spec: 128-bit entropy, collision probability negligible |

### Risk Mitigation Strategy

1. **Phase-Based Rollout**: Implement one model at a time, test thoroughly before proceeding
2. **Comprehensive Testing**: Unit, feature, and integration tests for each model
3. **Staging Environment**: Full testing on staging before production
4. **Rollback Plan**: Documented rollback procedures for each phase
5. **Monitoring**: Monitor error logs, performance metrics post-deployment
6. **Communication**: Notify all stakeholders of URL changes

---

## Success Criteria

### Technical Success Metrics

- [ ] All models in scope have ULID columns
- [ ] All ULID columns are indexed and unique
- [ ] All route model binding uses ULID
- [ ] Zero errors in production for 7 days post-deployment
- [ ] Response times remain <100ms for ULID lookups
- [ ] 100% test coverage for ULID functionality
- [ ] Zero foreign key constraint violations
- [ ] Polymorphic relationships (Mediable) work correctly

### Security Success Metrics

- [ ] User enumeration impossible via sequential IDs
- [ ] Cannot infer user count from IDs
- [ ] Cannot infer registration patterns from IDs
- [ ] Cannot infer transaction volumes from IDs
- [ ] ULID guessing computationally infeasible

### Business Success Metrics

- [ ] Zero user-facing errors from URL changes
- [ ] Zero data loss or corruption
- [ ] Zero performance complaints
- [ ] Investor confidence maintained
- [ ] Admin workflows unaffected
- [ ] Reporting accuracy maintained

### Documentation Success Metrics

- [ ] Developer guide updated
- [ ] API documentation updated (if applicable)
- [ ] Deployment runbook updated
- [ ] Rollback procedures documented
- [ ] Future model implementation guide created

---

## Performance Considerations

### ULID Lookup Performance

**Theoretical Performance:**
- Integer PK lookup: ~5-10ns (single B-tree operation)
- ULID indexed lookup: ~10-20ns (index scan + PK fetch)
- **Difference: <10ns per query (negligible)**

**Real-World Testing:**
```php
// Benchmark integer ID lookup
$start = microtime(true);
User::find(123);
$integerTime = microtime(true) - $start;

// Benchmark ULID lookup
$start = microtime(true);
User::where('ulid', '01HQ7ZQCR5XPMJ4GKZW8B2VN7E')->first();
$ulidTime = microtime(true) - $start;

// Expected: < 0.1ms difference
```

### Storage Overhead

- Integer ID: 8 bytes (BIGINT)
- ULID: 26 bytes (VARCHAR(26))
- **Overhead: 18 bytes per record**

For 1 million users:
- Storage overhead: ~18MB
- **Impact: Negligible on modern databases**

### Index Size

- Integer index: ~40MB for 1M records
- ULID index: ~110MB for 1M records
- **Difference: 70MB (acceptable)**

### Query Plan Analysis

```sql
-- Integer ID lookup
EXPLAIN SELECT * FROM users WHERE id = 123;
-- Type: const
-- Rows: 1
-- Extra: Using index

-- ULID lookup
EXPLAIN SELECT * FROM users WHERE ulid = '01HQ7ZQCR5XPMJ4GKZW8B2VN7E';
-- Type: ref
-- Rows: 1
-- Extra: Using index
```

**Conclusion:** Performance impact is negligible for typical workloads.

---

## Future Considerations

### New Model Pattern

When creating new models, follow this pattern:

1. **Migration:** Include ULID column from the start
```php
Schema::create('new_table', function (Blueprint $table) {
    $table->id();
    $table->string('ulid', 26)->unique()->index();
    // ... other columns
});
```

2. **Model:** Include ULID in fillable and booted()
```php
protected $fillable = ['ulid', ...];

protected static function booted(): void
{
    static::creating(function ($model) {
        if (empty($model->ulid)) {
            $model->ulid = (string) Str::ulid();
        }
    });
}
```

3. **Routes:** Use ULID from day one
```php
Route::get('/new-resource/{resource:ulid}', [Controller::class, 'show']);
```

### API Versioning

If external APIs use integer IDs, consider versioning:

```php
// v1 API - Legacy integer IDs
Route::prefix('api/v1')->group(function () {
    Route::get('/users/{user}', [ApiController::class, 'show']);  // Uses integer
});

// v2 API - ULID based
Route::prefix('api/v2')->group(function () {
    Route::get('/users/{user:ulid}', [ApiController::class, 'show']);  // Uses ULID
});
```

### GraphQL Considerations

If implementing GraphQL in the future:

```graphql
type User {
  id: ID!        # Expose ULID, not integer ID
  ulid: String!  # Explicit ULID field
  name: String!
  # ...
}

type Query {
  user(ulid: ID!): User
}
```

### Mobile App Considerations

- Mobile apps should use ULIDs in all API calls
- Cache ULID-to-name mappings to reduce lookups
- Update mobile app documentation with ULID patterns

---

## Appendix A: ULID Specification

### What is ULID?

ULID (Universally Unique Lexicographically Sortable Identifier) is a 128-bit identifier with the following properties:

- **128-bit compatibility** with UUID
- **Lexicographically sortable** (time-ordered)
- **Canonically encoded** as 26 character string (vs 36 for UUID)
- **Case insensitive** (uses Crockford's Base32)
- **No special characters** (URL safe)
- **Monotonic sort order** (within same millisecond)

### ULID Structure

```
 01HQ7ZQCR5XPMJ4GKZW8B2VN7E
 └─┬──┘ └────┬──────────────┘
   │         │
   │         Randomness (80 bits)
   │
   Timestamp (48 bits)
```

- **Timestamp (48 bits)**: Unix time in milliseconds
- **Randomness (80 bits)**: Cryptographically strong random values

### ULID vs UUID

| Feature | ULID | UUID v4 |
|---------|------|---------|
| Length | 26 characters | 36 characters |
| Sortable | ✅ Yes | ❌ No |
| Timestamp | ✅ Yes | ❌ No |
| Collision Risk | Negligible | Negligible |
| URL Safe | ✅ Yes | ❌ No (hyphens) |
| Case Sensitive | ❌ No | ✅ Yes |

### ULID Generation in Laravel

```php
use Illuminate\Support\Str;

// Generate new ULID
$ulid = Str::ulid();  // Returns Illuminate\Support\Str\Ulid instance
$ulidString = (string) Str::ulid();  // Returns string: "01HQ7ZQCR5XPMJ4GKZW8B2VN7E"

// Parse existing ULID
$ulid = Str::ulid('01HQ7ZQCR5XPMJ4GKZW8B2VN7E');
$timestamp = $ulid->toDateTime();  // Carbon instance

// Compare ULIDs
$ulid1 = Str::ulid();
$ulid2 = Str::ulid();
$ulid1->compare($ulid2);  // -1 (ulid1 is earlier)
```

---

## Appendix B: Blade Template Patterns

### Pattern 1: Explicit ULID in route() Helper

```blade
<!-- ✅ RECOMMENDED: Explicit and clear -->
<a href="{{ route('users.show', $user->ulid) }}">
    {{ $user->name }}
</a>

<form action="{{ route('users.update', $user->ulid) }}" method="POST">
    @csrf
    @method('PATCH')
    <!-- form fields -->
</form>
```

### Pattern 2: Model Instance in route() Helper

```blade
<!-- ✅ ALSO VALID: Laravel is smart -->
<a href="{{ route('users.show', $user) }}">
    {{ $user->name }}
</a>

<form action="{{ route('users.update', $user) }}" method="POST">
    @csrf
    @method('PATCH')
    <!-- form fields -->
</form>
```

Laravel automatically uses the ULID if route model binding is configured with `{user:ulid}`.

### Pattern 3: Array Parameters (Multiple Keys)

```blade
<!-- When route needs multiple parameters -->
<form action="{{ route('service-api.tokens.revoke', [$serviceUser->ulid, $token->id]) }}" method="POST">
    @csrf
    @method('DELETE')
    <button type="submit">Revoke Token</button>
</form>

<!-- OR with named parameters -->
<form action="{{ route('service-api.tokens.revoke', ['serviceUser' => $serviceUser, 'tokenId' => $token->id]) }}" method="POST">
    @csrf
    @method('DELETE')
    <button type="submit">Revoke Token</button>
</form>
```

### Pattern 4: Livewire Components

```blade
<!-- Livewire wire:click with ULID -->
<button wire:click="edit('{{ $user->ulid }}')">
    Edit User
</button>

<!-- Livewire wire:click with model (if Livewire understands ULID) -->
<button wire:click="edit({{ $user->id }})">
    Edit User
</button>
```

**Note:** Livewire components may need explicit ULID handling in component methods.

---

## Appendix C: Testing Utilities

### ULID Factory State

Add to model factories for testing:

```php
// database/factories/UserFactory.php
public function withUlid(string $ulid): static
{
    return $this->state(fn (array $attributes) => [
        'ulid' => $ulid,
    ]);
}

// Usage in tests
$user = User::factory()->withUlid('01HQ7ZQCR5XPMJ4GKZW8B2VN7E')->create();
```

### ULID Assertion Helper

Add to `tests/TestCase.php`:

```php
/**
 * Assert that a string is a valid ULID.
 *
 * @param string $value
 * @return void
 */
protected function assertValidUlid(string $value): void
{
    $this->assertEquals(26, strlen($value), 'ULID must be 26 characters');
    $this->assertMatchesRegularExpression(
        '/^[0-9A-HJKMNP-TV-Z]{26}$/',
        $value,
        'ULID must match Crockford Base32 alphabet'
    );
}

// Usage in tests
$this->assertValidUlid($user->ulid);
```

### ULID Database Seeder

For consistent test data:

```php
// database/seeders/TestUserSeeder.php
public function run(): void
{
    // Create predictable ULIDs for testing
    $ulidBase = '01HQ7ZQCR5XPMJ4GKZW8B2VN7';

    for ($i = 0; $i < 10; $i++) {
        User::factory()->create([
            'ulid' => $ulidBase . strtoupper(dechex($i)),
        ]);
    }
}
```

---

## Appendix D: Troubleshooting

### Issue: Route Not Found (404)

**Symptom:** Visiting `/users/01HQ7ZQCR5XPMJ4GKZW8B2VN7E` returns 404

**Possible Causes:**
1. Route not updated to use `{user:ulid}`
2. ULID column not indexed
3. ULID not generated for the user

**Solution:**
```bash
# Check route definition
php artisan route:list | grep users

# Should show: GET /users/{user:ulid}
# If shows: GET /users/{user}, update routes/web.php

# Check ULID exists
php artisan tinker
>>> User::find(1)->ulid
=> "01HQ7ZQCR5XPMJ4GKZW8B2VN7E"

# If null, run migration again or manually update
>>> User::whereNull('ulid')->update(['ulid' => DB::raw("CONCAT('01HQ', LPAD(id, 22, '0'))")]);
```

### Issue: Model Not Found by ULID

**Symptom:** `User::where('ulid', $ulid)->first()` returns null

**Possible Causes:**
1. ULID doesn't exist in database
2. Case sensitivity issue (MySQL may be case-sensitive depending on collation)
3. Whitespace in ULID

**Solution:**
```php
// Check exact ULID value
$ulid = trim($ulid);
$user = User::where('ulid', $ulid)->first();

// Check case-insensitive
$user = User::whereRaw('UPPER(ulid) = ?', [strtoupper($ulid)])->first();

// Check database
DB::table('users')->where('ulid', $ulid)->first();
```

### Issue: Foreign Key Constraint Violation

**Symptom:** Error when creating related models

**Possible Cause:** Trying to use ULID as foreign key value

**Solution:**
```php
// ❌ WRONG: Using ULID for foreign key
Contribution::create([
    'user_id' => $user->ulid,  // ERROR: string in integer column
    'investment_id' => $investment->id,
    'amount' => 10000,
]);

// ✅ CORRECT: Use integer ID for foreign keys
Contribution::create([
    'user_id' => $user->id,  // Integer ID for foreign key
    'investment_id' => $investment->id,
    'amount' => 10000,
]);

// Routes use ULID, database relationships use integer ID
```

### Issue: Mediable Polymorphic Relationship Broken

**Symptom:** `$user->attachMedia()` fails or media doesn't load

**Possible Cause:** Primary key changed to ULID (incorrect approach)

**Solution:**
Verify that User model still uses integer primary key:

```php
// app/Models/User.php
class User extends Authenticatable
{
    // ✅ Correct: Integer PK, ULID separate
    protected $keyType = 'int';
    public $incrementing = true;

    // ULID is just another column
    protected $fillable = ['ulid', ...];
}

// Check media table
DB::table('mediables')
    ->where('mediable_type', 'App\Models\User')
    ->where('mediable_id', $user->id)  // Should be integer
    ->get();
```

### Issue: Performance Degradation

**Symptom:** Slow queries after ULID implementation

**Possible Cause:** Missing index on ULID column

**Solution:**
```bash
# Check if index exists
php artisan db:show
# Or
mysql> SHOW INDEXES FROM users WHERE Key_name LIKE '%ulid%';

# If missing, add index
Schema::table('users', function (Blueprint $table) {
    $table->index('ulid');
});

# Check query plan
mysql> EXPLAIN SELECT * FROM users WHERE ulid = '01HQ7ZQCR5XPMJ4GKZW8B2VN7E';
# Should show: type=ref, key=users_ulid_index
```

---

## Conclusion

This implementation plan provides a comprehensive, risk-mitigated approach to adding ULID columns to QPIMS models. By following the proven architectural pattern of using ULID as a separate column (not as the primary key), we ensure:

✅ **Compatibility:** Works seamlessly with all existing packages
✅ **Simplicity:** Minimal code changes, leverages Laravel's route model binding
✅ **Security:** Prevents user enumeration and pattern inference
✅ **Performance:** Negligible overhead (<10ns per query)
✅ **Maintainability:** Clear pattern for future models

The phased rollout approach allows for thorough testing at each stage, with clear rollback procedures if issues arise. Starting with the User model (Phase 1) establishes the pattern and validates the approach before scaling to other models.

**Next Steps:**
1. Review and approve this plan
2. Begin Phase 1: User model implementation
3. Test thoroughly before proceeding to Phase 2

---

**Document Metadata:**
- **Author:** Claude (AI Assistant)
- **Reviewed By:** [Pending]
- **Approved By:** [Pending]
- **Status:** Draft
- **Last Updated:** 2026-04-21
