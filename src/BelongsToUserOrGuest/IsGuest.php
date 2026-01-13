<?php

namespace JonHassall\BelongsToUserOrGuest;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use function request;
use function now;

/**
 * Trait for models that represent guest sessions.
 * Use this on your session model (e.g., ConvertSession, Guest, etc.)
 */
trait IsGuest
{
    /**
     * Cache for guest sessions during the current request lifecycle.
     * Key is the session token, value is the session instance.
     */
    protected static array $guestSessionCache = [];

    /**
     * Get the name of the cookie used to store this session.
     * Override this method in your session model.
     */
    abstract protected function getCookieName(): string;

    /**
     * Get the duration for the session cookie in minutes.
     * Defaults to 1 year, override if needed.
     */
    protected function getCookieDuration(): int
    {
        return 525600; // 1 year
    }

    /**
     * Should this session store the user's IP address?
     * Defaults to false. Stores in column named 'ip_address'
     */
    protected function shouldStoreIP(): bool
    {
        return false;
    }

    /**
     * Get the current user or guest session for the request.
     * 
     * @param Request|null $request
     * @param bool $createIfNeeded Whether to create a new session if none exists
     * @return User|static|null Returns the user model, session model, or null if not allowed to create
     */
    public static function getCurrentUserOrGuest(?Request $request = null, bool $createIfNeeded = true)
    {
        $request = $request ?: request();
        if ($request->user()) {
            return $request->user();
        }

        return static::getOrCreateGuest($request, $createIfNeeded);
    }

    /**
     * Get or create a session for the current request.
     * 
     * @param Request|null $request
     * @param bool $createIfNeeded Whether to create a new session if none exists
     * @return static|null Returns the session model or null if not allowed to create
     */
    public static function getOrCreateGuest(?Request $request = null, bool $createIfNeeded = true): ?static
    {
        $request = $request ?: request();
        $instance = new static();
        $cookieName = $instance->getCookieName();
        
        // Get session ID from request or cookie
        $sessionId = $request->attributes->get($cookieName, $request->cookies->get($cookieName));

        // Check cache first
        if ($sessionId && isset(static::$guestSessionCache[$sessionId])) {
            return static::$guestSessionCache[$sessionId];
        }

        // Try to find existing session
        $session = null;
        if ($sessionId) {
            $session = static::where('session_token', $sessionId)->first();
        }

        // Create new session if needed
        if (!$session && $createIfNeeded) {
            $session = new static();
            $session->save(); // Assumes session_token is auto-generated

            // Set cookie
            Cookie::queue($cookieName, $session->session_token, $instance->getCookieDuration());
            $request->attributes->set($cookieName, $session->session_token);
        }

        // Update existing session
        if ($session) {
            // Renew cookie
            Cookie::queue($cookieName, $session->session_token, $instance->getCookieDuration());
            $session->touch();

            // Store IP if enabled
            if ($instance->shouldStoreIP() && $request->ip() && $session->ip_address !== $request->ip()) {
                $session->ip_address = $request->ip();
                $session->save();
            }
            
            // Cache the session
            static::$guestSessionCache[$session->session_token] = $session;
        }

        return $session;
    }

    /**
     * Delete the guest session and its cookie.
     * 
     * @param Request|null $request
     * @return bool True if a guest session was found and deleted, false otherwise
     */
    public static function deleteGuestSession(?Request $request = null): bool
    {
        $request = $request ?: request();
        $instance = new static();
        $cookieName = $instance->getCookieName();
        $sessionId = $request->attributes->get($cookieName, $request->cookies->get($cookieName));
        if (!$sessionId) {
            return false;
        }
        $guest = static::where('session_token', $sessionId)->first();
        if (!$guest) {
            return false;
        }
        // Delete the session
        $guest->delete();
        // Clear the cookie
        Cookie::queue(Cookie::forget($cookieName));
        return true;
    }

    /**
     * Check if the current request is for a guest (not logged in).
     */
    public static function isGuest(?Request $request = null): bool
    {
        $request = $request ?: request();

        return !$request->user();
    }

    /**
     * Get guest session.
     * @param Request|null $request
     * @param bool $createIfNeeded Whether to create a new session if none exists
     * @param bool $ignoreLoggedIn If true, will return session even if user is logged in
     * @return static|null Returns the session model or null if not found
     */
    public static function getGuest(?Request $request = null, bool $createIfNeeded = true, bool $ignoreLoggedIn = false): ?static
    {
        $request = $request ?: request();

        if (!$ignoreLoggedIn && !static::isGuest($request)) {
            return null; // User is logged in, no guest session
        }

        return static::getOrCreateGuest($request, $createIfNeeded);
    }

    /**
     * Cleanup old sessions that haven't been updated recently.
     */
    public static function cleanup(): int
    {
        $instance = new static();
        $cutoffTime = now()->subMinutes($instance->getCookieDuration());
        
        return static::where('updated_at', '<', $cutoffTime)->delete();
    }
}
