<?php

declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Models\User;
use App\Modules\TravelTours\Bookings\Models\BookingParticipant;
use App\Modules\TravelTours\Customers\Models\Traveler;
use App\Modules\TravelTours\Database\Seeders\TravelToursAccessSeeder;
use App\Modules\TravelTours\Database\Seeders\TravelToursDemoOperatorSeeder;
use App\Modules\TravelTours\Support\TravelToursRole;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionMethod;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests the actual TravelTours migration metadata and Eloquent model boundary.
 *
 * These SQLite checks do not claim to verify production row-lock contention.
 */
final class TravelToursFoundationTest extends TestCase
{
    use RefreshDatabase;

    /** Use only the isolated PHPUnit connection and an ephemeral encryption key. */
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
    }

    /** Every documented entity resolves to its own migrated table and identifier. */
    public function test_all_thirty_four_models_have_their_owned_schema(): void
    {
        $models = $this->models();
        $this->assertCount(34, $models);

        foreach ($models as $model) {
            $table = $model->getTable();
            $this->assertTrue(Schema::hasTable($table), $table);
            $this->assertTrue(Schema::hasColumns($table, ['id', 'created_at', 'updated_at']), $table);

            if ($table !== 'travel_promotion_tour') {
                $this->assertTrue(Schema::hasColumn($table, 'ulid'), $table);
            }
        }

        $this->assertFalse((bool) config('commerce.enabled'));
        $this->assertFalse((bool) config('property-booking.enabled'));
    }

    /** Column comments must belong to the Blueprint column, not a foreign command. */
    public function test_all_actual_blueprint_columns_have_comments(): void
    {
        $blueprints = [];
        $connection = DB::connection();
        Schema::shouldReceive('create')->andReturnUsing(
            function (string $table, Closure $callback) use (&$blueprints, $connection): void {
                $blueprints[] = new Blueprint($connection, $table, $callback);
            },
        );

        foreach (glob(app_path('Modules/TravelTours/Database/Migrations/*.php')) as $path) {
            (require $path)->up();
        }

        $this->assertCount(34, $blueprints);
        foreach ($blueprints as $blueprint) {
            foreach ($blueprint->getColumns() as $column) {
                $this->assertNotEmpty($column->comment, $blueprint->getTable().'.'.$column->name);
            }
            foreach ($blueprint->getCommands() as $command) {
                if (is_string($command->index)) {
                    $this->assertLessThanOrEqual(64, strlen($command->index), $command->index);
                }
            }
        }
    }

    /** Validate inferred or explicit Eloquent keys against real schema columns. */
    public function test_declared_relationship_keys_exist_in_the_schema(): void
    {
        foreach ($this->models() as $model) {
            $reflection = new ReflectionClass($model);
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $reflection->getName()
                    || $method->getNumberOfRequiredParameters() !== 0) {
                    continue;
                }
                $returnType = $method->getReturnType();
                if ($returnType === null || ! is_a((string) $returnType, Relation::class, true)) {
                    continue;
                }

                $relation = $method->invoke($model);
                if ($relation instanceof BelongsTo) {
                    $this->assertTrue(Schema::hasColumn($model->getTable(), $relation->getForeignKeyName()), $reflection->getName().'::'.$method->getName());
                } elseif ($relation instanceof HasOneOrMany) {
                    $this->assertTrue(Schema::hasColumn($relation->getRelated()->getTable(), $relation->getForeignKeyName()), $reflection->getName().'::'.$method->getName());
                }
            }
        }
    }

    /** Every explicit Eloquent cast must refer to a real persisted attribute. */
    public function test_declared_cast_attributes_exist_in_the_schema(): void
    {
        foreach ($this->models() as $model) {
            foreach (array_keys($model->getCasts()) as $attribute) {
                $this->assertTrue(
                    Schema::hasColumn($model->getTable(), $attribute),
                    $model::class.' casts missing column '.$attribute,
                );
            }
        }
    }

    /** Encrypted casts must not reveal plaintext through routine serialization. */
    public function test_sensitive_traveler_fields_are_encrypted_and_hidden(): void
    {
        foreach ([
            Traveler::class,
            BookingParticipant::class,
        ] as $class) {
            $model = new $class;
            foreach (['identity_number', 'medical_notes', 'dietary_requirements', 'accessibility_requirements'] as $field) {
                $model->setAttribute($field, 'private-fixture-value');
                $this->assertNotSame('private-fixture-value', $model->getAttributes()[$field]);
                $this->assertSame('private-fixture-value', $model->getAttribute($field));
                $this->assertArrayNotHasKey($field, $model->toArray());
            }
        }
    }

    /** Every domain model must have a module-owned factory that persists valid rows. */
    public function test_every_domain_model_factory_persists_a_valid_record(): void
    {
        foreach ($this->models() as $model) {
            $class = $model::class;
            $factory = $class::factory();

            $this->assertSame($class, $factory->modelName(), $class);

            $record = $factory->create();
            $this->assertTrue($record->exists, $class);
            $this->assertSame($model->getTable(), $record->getTable(), $class);
        }
    }

    /** Access reconciliation must be identity-free while demo operators remain opt-in. */
    public function test_access_and_demonstration_seeders_have_separate_side_effects(): void
    {
        $initialUsers = User::query()->count();

        $this->seed(TravelToursAccessSeeder::class);

        $this->assertSame($initialUsers, User::query()->count());
        $this->assertTrue(Role::query()->where('name', TravelToursRole::MANAGER)->exists());
        $this->assertTrue(Role::query()->where('name', TravelToursRole::BOOKING_AGENT)->exists());
        $this->assertTrue(Role::query()->where('name', TravelToursRole::TOUR_EDITOR)->exists());

        $this->seed(TravelToursDemoOperatorSeeder::class);

        $this->assertTrue(User::query()->where('email', 'travel.manager@example.test')->exists());
        $this->assertTrue(User::query()->where('email', 'booking.agent@example.test')->exists());
    }

    /** Discover only module model files, excluding dependencies and generated assets.
     *
     * @return list<Model>
     */
    private function models(): array
    {
        $models = [];
        foreach (glob(app_path('Modules/TravelTours/*/Models/*.php')) as $path) {
            $relative = str_replace('\\', '/', substr($path, strlen(app_path()) + 1));
            $class = 'App\\'.str_replace('/', '\\', substr($relative, 0, -4));
            $models[] = new $class;
        }

        return $models;
    }
}
