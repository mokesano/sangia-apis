# Admin — Revoke API Key

Permanently revoke an API key so it can no longer authenticate requests.

**Method:** `POST`  
**Path:** `/api/v1/admin/keys/revoke`  
**Auth:** `X-API-Key` required (must be the Sangia Sikola service key)  
**Timeout:** default (no heavy processing)

---

## Security Note

This endpoint is intended exclusively for server-to-server calls from Sangia Sikola's backend. Never call it from frontend JavaScript. The `X-API-Key` header must contain a valid service-level key issued to the Sangia Sikola backend.

---

## Request Body

```json
{
  "key": "wz_42_1719000000_a3f8e2c1d5b7"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `key` | string | Yes | The full API key to revoke (format: `wz_{user_id}_{timestamp}_{hmac16}`) |

---

## Response

```json
{
  "status":  "success",
  "message": "Key revoked"
}
```

---

## Error Responses

| Code | Message | Cause |
|------|---------|-------|
| 400 | `key is required` | Empty or missing `key` field |
| 401 | `Invalid or expired API key.` | The service key in `X-API-Key` header is invalid |

---

## How Revocation Works

Revoked keys are appended to `writable/revoked_keys.txt` on the sangia-apis server. The `ApiKeyMiddleware` reads this file on every request to check if the presented key has been revoked.

---

## Usage in Sangia Sikola

### 1. User-Initiated Revocation

When a user revokes their own key from the Sangia Sikola profile page:

```php
class ApiKeyService
{
    private string $serviceKey; // the Sangia Sikola service-level API key

    public function __construct()
    {
        $this->serviceKey = config('services.sangia.service_key');
    }

    public function revokeKey(string $userApiKey): bool
    {
        $response = Http::withHeaders([
            'X-API-Key'    => $this->serviceKey,
            'Content-Type' => 'application/json',
        ])->post(config('services.sangia.url') . '/api/v1/admin/keys/revoke', [
            'key' => $userApiKey,
        ]);

        return $response->json('status') === 'success';
    }
}
```

### 2. Admin-Initiated Revocation

When an admin revokes a user's key from the Sangia Sikola admin panel:

```php
public function revokeUserKey(User $user): void
{
    if ($user->api_key) {
        $this->apiKeyService->revokeKey($user->api_key);

        $user->update([
            'api_key'    => null,
            'key_revoked_at' => now(),
        ]);

        // Optionally notify the user
        $user->notify(new ApiKeyRevokedNotification());
    }
}
```

### 3. Automatic Revocation on Account Suspension

```php
class UserObserver
{
    public function updated(User $user): void
    {
        if ($user->isDirty('status') && $user->status === 'suspended') {
            if ($user->api_key) {
                app(ApiKeyService::class)->revokeKey($user->api_key);
                $user->update(['api_key' => null]);
            }
        }
    }
}
```

### 4. Key Lifecycle Flow

```
User registers
    → Sangia Sikola calls ApiKeyMiddleware::generateKey()
    → Key stored in users.api_key
    → Key displayed once to user

User loses key / security concern
    → User clicks "Revoke" or Admin suspends account
    → Sangia Sikola calls POST /api/v1/admin/keys/revoke
    → Key added to revoked_keys.txt on sangia-apis
    → users.api_key set to NULL

User requests new key
    → Sangia Sikola generates new key
    → Stored and displayed to user
```
