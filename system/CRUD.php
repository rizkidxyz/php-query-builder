<?php

class CRUD
{
    private PDO    $pdo;
    private bool   $debug;
    private ?string $table    = null;
    private array   $columns  = ['*'];
    private array   $wheres   = [];
    private array   $bindings = [];
    private array   $orders   = [];
    private ?int    $limitVal = null;
    private ?int    $offsetVal = null;
    private const ALLOWED_OPERATORS = [
        '=', '!=', '<>', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE'
    ];
    
    public function __construct(string $configPath = __DIR__ . '/config.php')
    {
        $config      = require $configPath;
        $driver      = $config['default'] ?? 'mysql';
        $db          = $config['connections'][$driver];
        $this->debug = (bool) ($config['debugging'] ?? false);

        try {
            $dsn = sprintf(
                '%s:host=%s;port=%s;dbname=%s;charset=%s',
                $db['driver'],
                $db['host'],
                $db['port']    ?? 3306,
                $db['database'],
                $db['charset'] ?? 'utf8mb4',
            );

            $this->pdo = new PDO($dsn, $db['username'], $db['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (Throwable $e) {
            $this->abort($e);
        }
    }

    // ── Builder ────────────────────────────────────────────────────────────────

    public function table(string $table): static
    {
        $this->table    = $table;
        $this->columns  = ['*'];
        $this->wheres   = [];
        $this->bindings = [];
        $this->orders   = [];
        $this->limitVal  = null;
        $this->offsetVal = null;

        return $this;
    }

    public function select(string ...$columns): static
    {
        $this->columns = $columns ?: ['*'];
        return $this;
    }
    
    public function where(string $column, mixed $operator, mixed $value = null): static
    {
        return $this->addWhere('AND', $column, $operator, $value);
    }
    
    public function orWhere(string $column, mixed $operator, mixed $value = null): static
    {
        return $this->addWhere('OR', $column, $operator, $value);
    }
    
    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orders[] = $column . ' ' . strtoupper($direction);
        return $this;
    }

    public function limit(int $n): static
    {
        $this->limitVal = $n;
        return $this;
    }

    public function offset(int $n): static
    {
        $this->offsetVal = $n;
        return $this;
    }
    
    // ── Execution ──────────────────────────────────────────────────────────────

    public function get(): array
    {
        $this->needTable();
        [$where, $bindings] = $this->buildWhere();

        $sql = sprintf('SELECT %s FROM %s%s%s%s%s',
            implode(', ', $this->columns),
            $this->table,
            $where,
            $this->orders   ? ' ORDER BY ' . implode(', ', $this->orders) : '',
            $this->limitVal  !== null ? " LIMIT {$this->limitVal}"   : '',
            $this->offsetVal !== null ? " OFFSET {$this->offsetVal}" : '',
        );

        return $this->run($sql, $bindings)->fetchAll();
    }

    public function first(): ?array
    {
        $result = $this->limit(1)->get();
        return $result[0] ?? null;
    }
    
    public function count(): int
    {
        $this->needTable();
        [$where, $bindings] = $this->buildWhere();

        $row = $this->run(
            "SELECT COUNT(*) AS n FROM {$this->table}{$where}",
            $bindings
        )->fetch();

        return (int) ($row['n'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function insert(array $data): int|false
    {
        $this->needTable();
        $cols = array_keys($data);
        $ph   = array_fill(0, count($data), '?');
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $cols),
            implode(', ', $ph),
        );
        $this->run($sql, array_values($data));
        $id = $this->pdo->lastInsertId();
        return $id !== false ? (int) $id : false;
    }

    public function update(array $data): bool
    {
        $this->needTable();
        $this->needWhere('update');
        [$where, $whereBindings] = $this->buildWhere();
        $sets   = [];
        $values = [];
        foreach ($data as $col => $val) {
            $sets[]   = "{$col} = ?";
            $values[] = $val;
        }
        $sql = sprintf('UPDATE %s SET %s%s',
            $this->table,
            implode(', ', $sets),
            $where,
        );
        $this->run($sql, array_merge($values, $whereBindings));
        return true;
    }

    public function delete(): bool
    {
        $this->needTable();
        $this->needWhere('delete');

        [$where, $bindings] = $this->buildWhere();

        $this->run("DELETE FROM {$this->table}{$where}", $bindings);
        return true;
    }
    
    public function raw(string $sql, array $bindings = []): array
    {
        return $this->run($sql, $bindings)->fetchAll();
    }
    
    // ── Internals ──────────────────────────────────────────────────────────────

    private function addWhere(string $bool, string $col, mixed $op, mixed $val): static
    {
        if ($val === null) { $val = $op; $op = '='; }
        $op = strtoupper((string) $op);
        if (!in_array($op, self::ALLOWED_OPERATORS, true)) {
            throw new InvalidArgumentException("Operator '{$op}' tidak didukung.");
        }
        $this->wheres[]   = compact('bool', 'col', 'op');
        $this->bindings[] = $val;
        return $this;
    }

    private function buildWhere(): array
    {
        if (!$this->wheres) return ['', []];
        $parts    = [];
        $bindings = [];
        foreach ($this->wheres as $i => $w) {
            $parts[]    = ($i === 0 ? '' : " {$w['bool']} ") . "{$w['col']} {$w['op']} ?";
            $bindings[] = $this->bindings[$i];
        }
        return [' WHERE ' . implode('', $parts), $bindings];
    }
    
    private function run(string $sql, array $bindings = []): PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($bindings);
            return $stmt;
        } catch (Throwable $e) {
            $this->abort($e, $sql);
        }
    }

    private function needTable(): void
    {
        if (!$this->table) throw new LogicException('Panggil table() dulu sebelum query.');
    }

    private function needWhere(string $op): void
    {
        if (!$this->wheres) throw new LogicException("{$op}() wajib pakai where().");
    }

    private function abort(Throwable $e, string $sql = ''): never
    {
        if ($this->debug) {
            echo '<pre>';
            echo 'CRUD ERROR: ' . $e->getMessage() . "\n\n";
            if ($sql) echo "SQL:\n{$sql}\n\n";
            echo 'FILE: ' . $e->getFile() . ':' . $e->getLine();
            echo '</pre>';
        } else {
            http_response_code(500);
            echo '<h1>500 Internal Server Error</h1>';
        }
        exit(1);
    }
}