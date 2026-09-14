<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\Schema;

/**
 * Inspect the real migration Blueprint metadata without mutating any database.
 *
 * Run with --markdown for the exact implemented schema inventory. This is not
 * a concurrency or full-foundation acceptance test and never invokes seeders.
 */
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$connection = new SQLiteConnection(new PDO('sqlite::memory:'));
$connection->useDefaultSchemaGrammar();

/** Collect definitions without calling the schema builder's SQL execution. */
$collector = new class($connection) extends Builder
{
    /** @var list<Blueprint> */
    public array $definitions = [];

    /** Capture a migration's callback using the installed framework Blueprint. */
    public function create($table, Closure $callback): void
    {
        $this->definitions[] = new Blueprint($this->connection, $table, $callback);
    }
};

Schema::swap($collector);
foreach (glob(app_path('Modules/TravelTours/Database/Migrations/*.php')) as $path) {
    (require $path)->up();
}

$missing = [];
$columns = 0;
foreach ($collector->definitions as $definition) {
    foreach ($definition->getColumns() as $column) {
        $columns++;
        if (! is_string($column->comment) || trim($column->comment) === '') {
            $missing[] = $definition->getTable().'.'.$column->name;
        }
    }
}

if ($missing !== [] || count($collector->definitions) !== 34) {
    fwrite(STDERR, json_encode(['tables' => count($collector->definitions), 'missing_comments' => $missing], JSON_THROW_ON_ERROR).PHP_EOL);
    exit(1);
}

if (! in_array('--markdown', $argv, true)) {
    echo json_encode([
        'scope' => 'Migration metadata only; no database writes or seed activity',
        'tables' => count($collector->definitions),
        'documented_columns' => $columns,
        'missing_comments' => $missing,
        'full_foundation_accepted' => false,
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
    exit(0);
}

echo "# TravelTours Implemented Schema Inventory\n\n";
echo "Generated from the installed Laravel Blueprint and current module migrations.\n";
echo "This records the draft's actual fields, not acceptance of the target foundation.\n";
echo "Compare travel-tours-data-model.md for planned additions and invariants.\n\n";
echo "Regenerate: php scripts/probe-travel-tours-foundation.php --markdown\n\n";

foreach ($collector->definitions as $definition) {
    echo '## '.$definition->getTable()."\n\n";
    echo "| Column | Blueprint type | Nullable | Default | Purpose |\n";
    echo "| --- | --- | --- | --- | --- |\n";
    foreach ($definition->getColumns() as $column) {
        $type = $column->type;
        if ($column->length !== null) {
            $type .= '('.$column->length.')';
        }
        if ($column->unsigned) {
            $type .= ' unsigned';
        }
        $default = $column->default === null ? '-' : json_encode($column->default, JSON_THROW_ON_ERROR);
        $purpose = str_replace(['|', "\n"], ['/', ' '], $column->comment);
        echo '| '.$column->name.' | '.$type.' | '.($column->nullable ? 'yes' : 'no').' | '.$default.' | '.$purpose." |\n";
    }
    echo "\nForeign keys and explicit index commands:\n\n";
    foreach ($definition->getCommands() as $command) {
        if ($command->name === 'foreign') {
            echo '- '.implode(', ', $command->columns).' -> '.$command->on.'('.implode(', ', (array) $command->references).'); delete: '.($command->onDelete ?? 'database default').".\n";
        } elseif (in_array($command->name, ['index', 'unique', 'primary'], true)) {
            echo '- '.$command->name.' ('.implode(', ', $command->columns).'): '.(is_string($command->index) ? $command->index : 'framework-generated name').".\n";
        }
    }
    echo "\nInline column-level unique/index modifiers are also applied by Laravel.\n\n";
}
