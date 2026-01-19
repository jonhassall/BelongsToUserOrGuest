<?php

namespace JonHassall\BelongsToUserOrGuest;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Auth;
use function request;

/**
 * Trait for models that can belong to either a user or a guest session.
 * Use this on models that can be owned by logged-in users OR guest sessions.
 */
trait BelongsToUserOrGuest
{
    /**
     * Specify the guest session model class.
     * e.g. 'App\Models\guest'
     */
    abstract protected function getGuestModel(): string;

    /**
     * Get the foreign key name for the guest session.
     * Defaults to 'guest_id', override if different.
     */
    protected function getGuestForeignKey(): string
    {
        return 'guest_id';
    }

    /**
     * Assign this model to the current user or guest session.
     */
    public function assignToCurrentOwner(?Request $request = null, bool $persist = true): bool
    {
        $request = $request ?: request();
        if ($request->user()) {
            // Logged in user
            $this->user_id = $request->user()->id;
            $this->{$this->getGuestForeignKey()} = null;
        } else {
            // Guest session
            $sessionModel = $this->getGuestModel();
            $guest = $sessionModel::getOrCreateGuest($request);

            $this->user_id = null;
            $this->{$this->getGuestForeignKey()} = $guest->id;
        }

        if ($persist) {
            $this->save();
        }

        return true;
    }

    /**
     * Remove ownership (clear user_id and guest session).
     */
    public function removeOwnership(bool $persist = true): bool
    {
        $this->user_id = null;
        $this->{$this->getGuestForeignKey()} = null;

        if ($persist) {
            $this->save();
        }

        return true;
    }

    /**
     * Scope to get models for current user or guest session.
     */
    public function scopeForCurrentOwner($query, ?Request $request = null)
    {
        $request = $request ?: request();

        if ($request->user()) {
            return $query->where('user_id', $request->user()->id);
        } else {
            $sessionModel = $this->getGuestModel();
            $guest = $sessionModel::getOrCreateGuest($request);
            return $query->where($this->getGuestForeignKey(), $guest->id);
        }
    }

    /**
     * Check if this model is owned by the current request (user or guest session).
     */
    public function isOwnedByCurrentRequest(?Request $request = null): bool
    {
        $request = $request ?: request();

        if ($request->user()) {
            return $this->user_id === $request->user()->id;
        } else {
            $sessionModel = $this->getGuestModel();
            $guest = $sessionModel::getGuest($request, false);
            return $guest && $this->{$this->getGuestForeignKey()} === $guest->id;
        }
    }

    /**
     * Move guest session models to user (for when user logs in).
     * Use IsGuest::deleteGuestSession to delete the guest session after moving.
     * 
     * @param Request|null $request
     * @return bool True if any models were moved, false otherwise
     *
     */
    public static function moveGuestToUser(?Request $request = null): bool
    {
        $request = $request ?: request();
        if (!$request->user()) {
            return false;
        }

        $instance = new static();
        $sessionModel = $instance->getGuestModel();
        $guest = $sessionModel::getGuest($request, false, true);

        if (!$guest) {
            return false;
        }

        // Move all models from guest session to user
        $foreignKey = $instance->getGuestForeignKey();
        $updatedCount = static::where($foreignKey, $guest->id)->update([
            'user_id' => $request->user()->id,
            $foreignKey => null
        ]);

        return $updatedCount > 0;
    }

    /**
     * Relationship to user.
     */
    public function user()
    {
        $userModel = config('auth.providers.users.model', 'App\Models\User');
        return $this->belongsTo($userModel);
    }

    /**
     * Relationship to guest session.
     */
    public function guest()
    {
        return $this->belongsTo($this->getGuestModel(), $this->getGuestForeignKey());
    }

    /**
    * Get the owner of the model, either user or guest.
     */
    public function owner()
    {
        return $this->user_id ? $this->user() : $this->guest();
    }

    /**
     * Create a model with proper ownership in one step.
     */
    public static function createWithOwnership(array $data, ?Request $request = null): static
    {
        $request = $request ?: request();
        $ownershipData = static::getOwnershipData($request);
        return static::create(array_merge($data, $ownershipData));
    }

    /**
     * Create or update a model with proper ownership in one step.
     * Useful for upsert scenarios.
     */
    public static function updateOrCreateWithOwnership(array $attributes, array $values = [], ?Request $request = null): static
    {
        $request = $request ?: request();
        $ownershipData = static::getOwnershipData($request);
        return static::updateOrCreate(
            array_merge($attributes, $ownershipData),
            array_merge($values, $ownershipData)
        );
    }

    /**
     * Get data needed to create a model with proper ownership.
     * Useful for mass assignment scenarios.
     */
    private static function getOwnershipData(?Request $request = null): array
    {
        $request = $request ?: request();
        $instance = new static();

        if ($request->user()) {
            return [
                'user_id' => $request->user()->id,
                $instance->getGuestForeignKey() => null
            ];
        } else {
            $sessionModel = $instance->getGuestModel();
            $guest = $sessionModel::getOrCreateGuest($request);

            return [
                'user_id' => null,
                $instance->getGuestForeignKey() => $guest->id
            ];
        }
    }
}
