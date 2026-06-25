<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class AuditLogger
{
    /**
     * @var list<string>
     */
    private array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'current_password',
        'client_secret',
        'secret',
        'token',
        'code',
        'recovery_codes',
    ];

    public function log(Request $request, string $action, ?Model $subject = null, ?Tenant $tenant = null, array $metadata = []): void
    {
        AuditLog::query()->create([
            'tenant_id' => $tenant?->id,
            'user_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip_address' => $request->ip(),
            'metadata' => $this->metadata($request, $metadata),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function metadata(Request $request, array $metadata): array
    {
        return [
            ...$this->sanitize($metadata),
            'request' => $this->sanitize($request->all()),
        ];
    }

    private function sanitize(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return '[file]';
        }

        if (! is_array($value)) {
            return $value;
        }

        $sanitized = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && in_array(strtolower($key), $this->sensitiveKeys, true)) {
                $sanitized[$key] = '[filtered]';

                continue;
            }

            $sanitized[$key] = $this->sanitize($item);
        }

        return $sanitized;
    }
}
