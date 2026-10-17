<?php

namespace App\Auth;

use App\Models\Customer;
use App\Services\Auth\CustomerJwtService;
use Exception;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;

class CustomerJwtGuard implements Guard
{
    use GuardHelpers;

    protected Request $request;

    protected CustomerJwtService $jwtService;

    public function __construct(
        UserProvider $provider,
        Request $request,
        CustomerJwtService $jwtService
    ) {
        $this->provider = $provider;
        $this->request = $request;
        $this->jwtService = $jwtService;
    }

    /**
     * Get the currently authenticated user.
     */
    public function user(): ?Authenticatable
    {
        $request = app()->bound('request') ? app('request') : $this->request;
        $token = $this->jwtService->extractToken($request);

        if (! $token) {
            $this->user = null;

            return null;
        }

        try {
            $payload = $this->jwtService->decode($token, 'access');
        } catch (Exception $e) {
            $this->user = null;

            return null;
        }

        if ($this->user !== null && (string) $this->user->getAuthIdentifier() === (string) $payload->sub) {
            return $this->user;
        }

        $customer = $this->provider->retrieveById($payload->sub);

        if (! $customer instanceof Customer) {
            $this->user = null;

            return null;
        }

        if (! $customer->is_active) {
            $this->user = null;

            return null;
        }

        $this->user = $customer;

        return $this->user;
    }

    /**
     * Validate a user's credentials.
     */
    public function validate(array $credentials = []): bool
    {
        if (empty($credentials['token'])) {
            return false;
        }

        try {
            $payload = $this->jwtService->decode($credentials['token'], 'access');
            $customer = $this->provider->retrieveById($payload->sub);

            return $customer instanceof Customer && $customer->is_active;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Set the current request instance.
     */
    public function setRequest(Request $request): self
    {
        $this->request = $request;
        $this->user = null;

        return $this;
    }
}
