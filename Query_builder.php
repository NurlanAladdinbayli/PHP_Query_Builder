<?php

class QueryBuilder
{
     protected string $table;
     protected array $selects = [];
     protected array $wheres = [];
     protected array $bindParams = [];  // Dəyərlər üçün array
     protected array $orders = [];
     protected ?int $limit = null;
     protected ?string $groupBy = null;
     protected ?string $having = null;
     protected array $joins = [];
     protected ?string $aggregate = null; // Sum, Count kimi funksiyalar üçün

     protected PDO $pdo;

     // PDO obyektini qəbul edir
     public function __construct(PDO $pdo)
     {
          $this->pdo = $pdo;
     }

     // Cədvəli təyin edir
     public function table(string $table): self
     {
          $this->table = $table;
          return $this;
     }

     // Seçim üçün sahələri təyin edir
     public function select(array $fields): self
     {
          $this->selects = $fields;
          return $this;
     }

     // WHERE şərtləri əlavə edir (bind parametrlərlə birlikdə)
     public function where(string $field, string $operator, $value): self
     {
          $this->wheres[] = "$field $operator :param" . count($this->bindParams);
          $this->bindParams[] = $value;
          return $this;
     }

     // OR WHERE şərtləri əlavə edir
     public function orWhere(callable $callback): self
     {
          $query = new static($this->pdo);
          call_user_func($callback, $query);
          $this->wheres[] = "(" . implode(' AND ', $query->wheres) . ")";
          $this->bindParams = array_merge($this->bindParams, $query->bindParams);
          return $this;
     }

     // JOIN əlavə edir
     public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
     {
          $this->joins[] = "$type JOIN $table ON $first $operator $second";
          return $this;
     }

     public function orderBy(string $field, string $direction = 'ASC'): self
     {
          $this->orders[] = "$field $direction";
          return $this;
     }

     public function limit(int $limit): self
     {
          $this->limit = $limit;
          return $this;
     }

     public function groupBy(string $field): self
     {
          $this->groupBy = $field;
          return $this;
     }

     public function having(string $field, string $operator, $value): self
     {
          $this->having = "$field $operator :havingParam";
          $this->bindParams[] = $value;
          return $this;
     }

     // SQL ifadələri (DB::raw bənzəri)
     public function raw(string $expression): string
     {
          return $expression;
     }

     public function sum(string $expression): self
     {
          $this->aggregate = "SUM($expression)";
          return $this;
     }

     public function toSql(): string
     {
          $selectClause = $this->aggregate ? $this->aggregate : implode(', ', $this->selects);

          $sql = "SELECT $selectClause FROM $this->table";

          if ($this->joins) {
               $sql .= " " . implode(' ', $this->joins);
          }

          if ($this->wheres) {
               $sql .= " WHERE " . implode(' AND ', $this->wheres);
          }

          if ($this->groupBy) {
               $sql .= " GROUP BY " . $this->groupBy;
          }

          if ($this->having) {
               $sql .= " HAVING " . $this->having;
          }

          if ($this->orders) {
               $sql .= " ORDER BY " . implode(', ', $this->orders);
          }

          if ($this->limit) {
               $sql .= " LIMIT $this->limit";
          }
          return $sql;
     }

     // Sorğunu icra edir
     public function execute(): array
     {
          // Sorğunu string kimi yaradırıq
          $sql = $this->toSql();

          // Sorğunu hazırlayıb və icra edirik
          $statement = $this->pdo->prepare($sql);

          // Bütün parametrləri bind edirik
          foreach ($this->bindParams as $key => $value) {
               $statement->bindValue(":param$key", $value);
          }

          // Sorğunu icra edirik
          $statement->execute();

          // Nəticələri qaytarırıq
          return $statement->fetchAll(PDO::FETCH_ASSOC);
     }
}

// PDO bağlantısı
$pdo = new PDO('mysql:host=localhost;dbname=testdb', 'root', '');

// Task 1
$queryOne = (new QueryBuilder($pdo))
    ->table('product')
    ->select(['id', 'name', 'amount'])
    ->where('status', '=', 1)
    ->orderBy('amount')
    ->limit(10)
    ->toSql();

print_r($queryOne);


// Task 2
$queryTwo = (new QueryBuilder($pdo))
     ->table('product')
     ->where('status', '=', 1)
     ->orWhere(function($query) {
          $query->where('quantity', '>', 0)
               ->where('amount', '>', 0);
     })
     ->sum('quantity * amount')
     ->execute();
print_r($queryTwo);
