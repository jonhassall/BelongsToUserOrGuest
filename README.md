# BelongsToUserOrGuest

A Laravel package that provides traits for Eloquent models to support ownership by either authenticated users or guest sessions. This is perfect for applications where you want to allow both logged-in users and guests to interact with your application - for example, shopping carts, wishlists, favorites, or any feature that should work without requiring users to sign up first.

## Features

### BelongsToUserOrGuest Trait
Attach this trait to any model that can belong to either a logged-in user or a guest session.

**Key Features:**
- **Automatic Assignment**: Automatically assign models to the current user or guest session
- **Ownership Check**: Check if a model belongs to the current request
- **Query Scopes**: Retrieve only models owned by the current user or guest
- **Guest-to-User Migration**: Move guest-owned models to a user account when they log in
- **Flexible Relationships**: Support for custom guest model classes and foreign keys

### IsGuest Trait
Attach this trait to your guest session model to manage guest sessions with cookie-based tracking.

**Key Features:**
- **Cookie Handling**: Persistent cookies to track guests across visits
- **IP Tracking**: Optional IP address storage for security or analytics
- **Session Cleanup**: Remove old, expired guest sessions
- **Caching**: Request-level caching for performance
- **Configurable**: Customizable cookie names, durations, and behaviors

## Requirements

- **Laravel:** 10.x or higher
- **PHP:** 8.1 or higher
- **Cookies:** This package uses cookies to track guest sessions and does not require Laravel's session driver to be configured. All guest session management is handled entirely through cookies, making it lightweight and independent of your application's session configuration.

## Installation

### Option 1: Via Packagist (Recommended)

Install the package via Composer:

```bash
composer require jonhassall/belongs-to-user-or-guest
```

### Option 2: Install Directly from GitHub

You can install it directly from the GitHub repository. Add this to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/jonhassall/BelongsToUserOrGuest.git"
        }
    ],
    "require": {
        "jonhassall/belongs-to-user-or-guest": "dev-main"
    }
}
```

Then run:

```bash
composer install
```

## Quick Start

### Step 1: Set Up Your Guest Model

Create a migration for your guest sessions table:

```bash
php artisan make:migration create_guests_table
```

```php
Schema::create('guests', function (Blueprint $table) {
    $table->id();
    $table->string('session_token')->unique();
    $table->string('ip_address')->nullable(); // Optional
    $table->timestamps();
});
```

Create your Guest model:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use JonHassall\BelongsToUserOrGuest\IsGuest;

class Guest extends Model
{
    use IsGuest;

    protected $fillable = ['session_token', 'ip_address'];

    // Required: Cookie name for guest sessions
    protected function getCookieName(): string
    {
        return 'guest_session';
    }

    // Optional: Cookie duration in minutes (default: 1 year)
    protected function getCookieDuration(): int
    {
        return 525600; // 1 year
    }

    // Optional: Store guest IP addresses
    protected function shouldStoreIP(): bool
    {
        return true;
    }
}
```

### Step 2: Set Up Your Resource Model

Add the necessary columns to your resource table:

```php
Schema::create('favorites', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
    $table->foreignId('guest_id')->nullable()->constrained()->onDelete('cascade');
    $table->string('item_name');
    $table->timestamps();
});
```

Add the trait to your model:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use JonHassall\BelongsToUserOrGuest\BelongsToUserOrGuest;

class Favorite extends Model
{
    use BelongsToUserOrGuest;

    protected $fillable = ['item_name', 'user_id', 'guest_id'];

    // Required: Specify your guest model class
    protected function getGuestModel(): string
    {
        return Guest::class;
    }

    // Optional: Custom foreign key (default: 'guest_id')
    protected function getGuestForeignKey(): string
    {
        return 'guest_id';
    }
}
```

## Usage Examples

### Creating Records with Ownership

```php
// Method 1: Create and assign manually
$favorite = new Favorite(['item_name' => 'Laravel']);
$favorite->assignToCurrentOwner(); // Automatically assigns to user or guest

// Method 2: Create with ownership in one step
$favorite = Favorite::createWithOwnership([
    'item_name' => 'Laravel'
]);

// Method 3: Update or create with ownership
$favorite = Favorite::updateOrCreateWithOwnership(
    ['item_name' => 'Laravel'], // Search criteria
    ['updated_at' => now()]      // Additional values
);
```

### Querying Records

```php
// Get all favorites for current user or guest
$favorites = Favorite::forCurrentOwner()->get();

// Check if a specific favorite belongs to current user/guest
if ($favorite->isOwnedByCurrentRequest()) {
    // Allow editing or deletion
}

// Access the owner (returns User or Guest model)
$owner = $favorite->owner;
```

### Migrating Guest Data to User Account

When a guest logs in or signs up, transfer their data:

```php
// Option 1: In your login/registration controller
use App\Models\Favorite;
use App\Models\Guest;

public function login(Request $request)
{
    // After successful authentication...
    
    // Move all guest favorites to the logged-in user
    Favorite::moveGuestToUser($request);
    
    // Optionally delete the guest session
    Guest::deleteGuestSession($request);
}
```

```php
// Option 2: Automatically on login using AppServiceProvider
// In app/Providers/AppServiceProvider.php

use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Login;
use App\Models\Favorite;
use App\Models\ShoppingCart;
use App\Models\Guest;

public function boot(): void
{
    // Automatically move guest data when user logs in
    Event::listen(function (Login $event) {
        // Move guest-owned models to the logged-in user
        Favorite::moveGuestToUser();
        
        // Delete the guest session after migrating data
        Guest::deleteGuestSession();
        
        // Optional: Notify the user their data was saved
        if (request()->hasSession()) {
            session()->flash('success', 'Your favorites and cart have been saved to your account.');
        }
    });
}
```

### Working with Guest Sessions Directly

```php
use App\Models\Guest;

// Get or create guest for current request
$guest = Guest::getOrCreateGuest();

// Check if current request is a guest (not logged in)
if (Guest::isGuest()) {
    // Show guest-specific messaging
}

// Get current user or guest
$currentOwner = Guest::getCurrentUserOrGuest();

// Clean up old guest sessions (run in scheduled task)
$deletedCount = Guest::cleanup();
```

### Advanced Usage

```php
// Create without auto-saving
$favorite = new Favorite(['item_name' => 'Vue']);
$favorite->assignToCurrentOwner(persist: false); // Don't save yet
$favorite->some_other_field = 'value';
$favorite->save(); // Save when ready

// Remove ownership
$favorite->removeOwnership(); // Clears both user_id and guest_id

// Query scopes with explicit request
$favorites = Favorite::forCurrentOwner($customRequest)->get();
```

## Method Reference

### BelongsToUserOrGuest Methods

| Method | Description |
|--------|-------------|
| `assignToCurrentOwner(?Request $request, bool $persist = true)` | Assign model to current user or guest |
| `removeOwnership(bool $persist = true)` | Clear ownership from model |
| `isOwnedByCurrentRequest(?Request $request)` | Check if current user/guest owns this model |
| `scopeForCurrentOwner($query, ?Request $request)` | Query scope for current owner's models |
| `moveGuestToUser(?Request $request)` | Transfer all guest models to logged-in user |
| `createWithOwnership(array $data, ?Request $request)` | Create model with ownership in one step |
| `updateOrCreateWithOwnership(array $attributes, array $values, ?Request $request)` | Update or create with ownership |
| `user()` | Relationship to User model |
| `guest()` | Relationship to Guest model |
| `owner` | Attribute accessor for current owner (User or Guest) |

### IsGuest Methods

| Method | Description |
|--------|-------------|
| `getOrCreateGuest(?Request $request, bool $createIfNeeded = true)` | Get or create guest session |
| `getGuest(?Request $request, bool $createIfNeeded, bool $ignoreLoggedIn)` | Get guest session with options |
| `getCurrentUserOrGuest(?Request $request, bool $createIfNeeded)` | Get authenticated user or guest |
| `isGuest(?Request $request)` | Check if request is from a guest |
| `deleteGuestSession(?Request $request)` | Delete guest session and cookie |
| `cleanup()` | Remove expired guest sessions |

## Configuration

### Required Abstract Methods

**BelongsToUserOrGuest:**
- `getGuestModel(): string` - Return the fully qualified class name of your guest model

**IsGuest:**
- `getCookieName(): string` - Return the cookie name for guest sessions

### Optional Configuration Methods

**BelongsToUserOrGuest:**
- `getGuestForeignKey(): string` - Foreign key column name (default: `'guest_id'`)

**IsGuest:**
- `getCookieDuration(): int` - Cookie lifetime in minutes (default: 525600 = 1 year)
- `shouldStoreIP(): bool` - Enable IP address storage (default: false)

## Database Schema Requirements

### Resource Table (e.g., favorites)
```php
$table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
$table->foreignId('guest_id')->nullable()->constrained('guests')->onDelete('cascade');
```

### Guest Table
```php
$table->id();
$table->string('session_token')->unique();
$table->string('ip_address')->nullable(); // If using shouldStoreIP()
$table->timestamps();
```

## Scheduled Tasks

Add to `app/Console/Kernel.php` to clean up old guest sessions:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        \App\Models\Guest::cleanup();
    })->daily();
}
```


## License
This package is open-sourced software licensed under the MIT license.