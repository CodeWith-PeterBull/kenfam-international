<?php

namespace App\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\SystemActivity;
use App\Models\User;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;
use UnitEnum;

class SystemActivityService implements RecordsSystemActivity
{
    private const MAX_CONTEXT_DEPTH = 8;

    private const MAX_CONTEXT_ITEMS = 100;

    private const MAX_CONTEXT_STRING_LENGTH = 4000;

    private const MAX_USER_AGENT_LENGTH = 1000;

    /** @var list<string> */
    private const SENSITIVE_KEY_FRAGMENTS = [
        'authorization',
        'cookie',
        'credential',
        'password',
        'private_key',
        'recovery_code',
        'secret',
        'session',
        'token',
    ];

    public function record(
        string $activityType,
        string $description,
        ?User $actor = null,
        ?Model $subject = null,
        array $properties = [],
        SystemActivitySeverity $severity = SystemActivitySeverity::Info,
        ?string $source = null,
        ?string $batchUuid = null,
    ): SystemActivity {
        $activityType = $this->normalizeActivityType($activityType);
        $description = $this->normalizeDescription($description);
        $source = $this->normalizeSource($source);
        $batchUuid = $this->normalizeBatchUuid($batchUuid);
        $request = $this->currentHttpRequest();

        if ($subject !== null && $subject->getKey() === null) {
            throw new InvalidArgumentException('An activity subject must be persisted before it can be recorded.');
        }

        $sanitizedProperties = $this->sanitizeProperties($properties);

        return SystemActivity::query()->create([
            'batch_uuid' => $batchUuid,
            'user_id' => $actor?->getKey(),
            'activity_type' => $activityType,
            'severity' => $severity,
            'source' => $source ?? $this->inferSource($request),
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject === null ? null : (string) $subject->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $this->truncate($request?->userAgent(), self::MAX_USER_AGENT_LENGTH),
            'request_method' => $request?->method(),
            'route_name' => $request?->route()?->getName(),
            'request_url' => $request?->url(),
            'properties' => $sanitizedProperties === [] ? null : $sanitizedProperties,
        ]);
    }

    private function normalizeActivityType(string $activityType): string
    {
        $activityType = trim($activityType);

        if ($activityType === '' || strlen($activityType) > 150) {
            throw new InvalidArgumentException('Activity types must contain between 1 and 150 characters.');
        }

        if (preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $activityType) !== 1) {
            throw new InvalidArgumentException('Activity types must use lowercase dot notation.');
        }

        return $activityType;
    }

    private function normalizeDescription(string $description): string
    {
        $description = trim($description);

        if ($description === '') {
            throw new InvalidArgumentException('Activity descriptions cannot be empty.');
        }

        return $description;
    }

    private function normalizeSource(?string $source): ?string
    {
        if ($source === null) {
            return null;
        }

        $source = trim($source);

        if ($source === '' || strlen($source) > 50 || preg_match('/^[a-z0-9._-]+$/', $source) !== 1) {
            throw new InvalidArgumentException('Activity sources must be lowercase identifiers of 50 characters or fewer.');
        }

        return $source;
    }

    private function normalizeBatchUuid(?string $batchUuid): ?string
    {
        if ($batchUuid !== null && ! Str::isUuid($batchUuid)) {
            throw new InvalidArgumentException('Activity batch identifiers must be valid UUIDs.');
        }

        return $batchUuid;
    }

    private function currentHttpRequest(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();

        if (app()->runningInConsole() && $request->route() === null) {
            return null;
        }

        return $request;
    }

    private function inferSource(?Request $request): string
    {
        if ($request !== null) {
            return 'web';
        }

        return app()->runningInConsole() ? 'console' : 'application';
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function sanitizeProperties(array $properties): array
    {
        return $this->sanitizeArray($properties, 0);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function sanitizeArray(array $values, int $depth): array
    {
        if ($depth >= self::MAX_CONTEXT_DEPTH) {
            return ['_truncated' => 'Maximum context depth reached.'];
        }

        $sanitized = [];

        foreach (array_slice($values, 0, self::MAX_CONTEXT_ITEMS, true) as $key => $value) {
            $sanitized[$key] = $this->isSensitiveKey((string) $key)
                ? '[REDACTED]'
                : $this->sanitizeValue($value, $depth + 1);
        }

        if (count($values) > self::MAX_CONTEXT_ITEMS) {
            $sanitized['_truncated'] = sprintf(
                '%d additional context items were omitted.',
                count($values) - self::MAX_CONTEXT_ITEMS,
            );
        }

        return $sanitized;
    }

    private function sanitizeValue(mixed $value, int $depth): mixed
    {
        if ($value instanceof Model) {
            return [
                'type' => $value->getMorphClass(),
                'id' => $value->getKey() === null ? null : (string) $value->getKey(),
            ];
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        } elseif ($value instanceof JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        if (is_array($value)) {
            return $this->sanitizeArray($value, $depth);
        }

        if ($value instanceof Stringable) {
            $value = (string) $value;
        }

        if (is_string($value)) {
            return $this->truncate($value, self::MAX_CONTEXT_STRING_LENGTH);
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return sprintf('[Unsupported value: %s]', get_debug_type($value));
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalizedKey = Str::of($key)->snake()->lower()->toString();

        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (str_contains($normalizedKey, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function truncate(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        return Str::limit($value, $limit, '');
    }
}
