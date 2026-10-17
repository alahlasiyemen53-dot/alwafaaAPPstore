<?php

namespace App\Services\Auth;

use App\Models\Customer;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use UnexpectedValueException;

class CustomerJwtService
{
    private string $secret;

    private string $algo;

    private string $issuer;

    private string $audience;

    private int $ttl;

    private int $refreshTtl;

    private int $leeway;

    public function __construct()
    {
        $this->secret = (string) config('jwt.secret', config('app.key'));
        $this->algo = (string) config('jwt.algo', 'HS256');
        $this->issuer = (string) config('jwt.issuer', config('app.url', 'https://lamma.test'));
        $this->audience = (string) config('jwt.audience', 'lamma-customer-client');
        $this->ttl = (int) config('jwt.ttl', 60);
        $this->refreshTtl = (int) config('jwt.refresh_ttl', 20160);
        $this->leeway = (int) config('jwt.leeway', 0);
    }

    /**
     * Generate an access token for a customer.
     */
    public function generateAccessToken(Customer $customer, array $customClaims = []): string
    {
        $now = time();
        $payload = array_merge([
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'sub' => (string) $customer->getAuthIdentifier(),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + ($this->ttl * 60),
            'jti' => (string) Str::uuid(),
            'token_type' => 'access',
        ], $customClaims);

        return JWT::encode($payload, $this->secret, $this->algo);
    }

    /**
     * Generate a refresh token for a customer.
     */
    public function generateRefreshToken(Customer $customer, array $customClaims = []): string
    {
        $now = time();
        $payload = array_merge([
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'sub' => (string) $customer->getAuthIdentifier(),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + ($this->refreshTtl * 60),
            'jti' => (string) Str::uuid(),
            'token_type' => 'refresh',
        ], $customClaims);

        return JWT::encode($payload, $this->secret, $this->algo);
    }

    /**
     * Decode and validate a token.
     *
     * @throws ExpiredException
     * @throws SignatureInvalidException
     * @throws BeforeValidException
     * @throws UnexpectedValueException
     */
    public function decode(string $token, string $expectedType = 'access'): object
    {
        JWT::$leeway = $this->leeway;

        $payload = JWT::decode($token, new Key($this->secret, $this->algo));

        if (! isset($payload->token_type) || $payload->token_type !== $expectedType) {
            throw new UnexpectedValueException("Invalid token type. Expected: {$expectedType}");
        }

        if (isset($payload->iss) && $payload->iss !== $this->issuer) {
            throw new UnexpectedValueException('Invalid token issuer.');
        }

        if (isset($payload->aud) && $payload->aud !== $this->audience) {
            throw new UnexpectedValueException('Invalid token audience.');
        }

        if (! isset($payload->sub) || empty($payload->sub)) {
            throw new UnexpectedValueException('Token subject (sub) missing.');
        }

        if (isset($payload->jti) && Cache::has("jwt:revoked:{$payload->jti}")) {
            throw new UnexpectedValueException('Token has been revoked.');
        }

        return $payload;
    }

    /**
     * Revoke a token by caching its JTI until expiration.
     */
    public function revokeToken(string $token): void
    {
        try {
            $payload = $this->decode($token, 'access');
        } catch (\Throwable $e) {
            // Even if token decode throws (e.g. invalid type), attempt fallback extraction
            try {
                $parts = explode('.', $token);
                if (count($parts) === 3) {
                    $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')));
                }
            } catch (\Throwable) {
                return;
            }
        }

        if (isset($payload->jti, $payload->exp)) {
            $ttl = max(1, (int) ($payload->exp - time()));
            Cache::put("jwt:revoked:{$payload->jti}", true, $ttl);
        }
    }

    /**
     * Check if a token JTI is revoked.
     */
    public function isRevoked(string $jti): bool
    {
        return Cache::has("jwt:revoked:{$jti}");
    }

    /**
     * Extract bearer token from an HTTP request.
     */
    public function extractToken(Request $request): ?string
    {
        return $request->bearerToken();
    }
}
