# CRUD.php — Lightweight PHP Query Builder

Query builder minimalis berbasis PDO. Zero dependency, satu file, langsung jalan.

## Struktur Project

```
htdocs/
├── index.php
└── system/
    ├── CRUD.php
    └── config.php
```

## Setup

**`system/config.php`**
```php
<?php

return [
    'default'   => 'mysql',
    'debugging' => true, // ganti ke false di production
    'connections' => [
        'mysql' => [
            'driver'   => 'mysql',
            'host'     => '127.0.0.1',
            'port'     => '3306',
            'database' => 'nama_database',
            'username' => 'root',
            'password' => '',
            'charset'  => 'utf8mb4',
        ],
    ],
];
```

**`index.php`**
```php
<?php

require 'system/CRUD.php';

$db = new CRUD();
```

---

## SELECT

### Ambil semua data
```php
$users = $db->table('users')->get();
```

### Pilih kolom tertentu
```php
$users = $db->table('users')
            ->select('id', 'nama', 'email')
            ->get();
```

### Ambil satu baris pertama
```php
$user = $db->table('users')->first();
// return array|null
```

---

## WHERE

### WHERE dasar (operator default `=`)
```php
$db->table('users')
   ->where('status', 'aktif')
   ->get();
```

### WHERE dengan operator
```php
$db->table('users')
   ->where('umur', '>=', 18)
   ->get();
```

Operator yang didukung: `=` `!=` `<>` `>` `<` `>=` `<=` `LIKE` `NOT LIKE`

### WHERE berantai (AND)
```php
$db->table('users')
   ->where('status', 'aktif')
   ->where('role', 'admin')
   ->get();
// WHERE status = ? AND role = ?
```

### OR WHERE
```php
$db->table('users')
   ->where('role', 'admin')
   ->orWhere('role', 'editor')
   ->get();
// WHERE role = ? OR role = ?
```

### WHERE + LIKE (pencarian)
```php
$db->table('users')
   ->where('nama', 'LIKE', '%gilang%')
   ->get();
```

### WHERE gabungan
```php
$user = $db->table('users')
           ->select('id', 'nama', 'email')
           ->where('status', 'aktif')
           ->where('umur', '>=', 18)
           ->orWhere('role', 'admin')
           ->first();
```

---

## ORDER BY

```php
// ASC (default)
$db->table('users')->orderBy('nama')->get();

// DESC
$db->table('users')->orderBy('created_at', 'DESC')->get();

// Multiple order
$db->table('users')
   ->orderBy('role')
   ->orderBy('nama', 'DESC')
   ->get();
```

---

## LIMIT & OFFSET (Pagination)
```php
// Ambil 10 data
$db->table('users')->limit(10)->get();

// Halaman 2 (skip 10, ambil 10)
$db->table('users')->limit(10)->offset(10)->get();
```

### Contoh pagination lengkap
```php
$page     = (int) ($_GET['page'] ?? 1);
$perPage  = 10;
$offset   = ($page - 1) * $perPage;

$users = $db->table('users')
            ->select('id', 'nama', 'email')
            ->where('status', 'aktif')
            ->orderBy('created_at', 'DESC')
            ->limit($perPage)
            ->offset($offset)
            ->get();

$total     = $db->table('users')->where('status', 'aktif')->count();
$totalPage = ceil($total / $perPage);
```

---

## COUNT & EXISTS

```php
// Hitung total baris
$total = $db->table('users')->count();

// Hitung dengan filter
$totalAktif = $db->table('users')->where('status', 'aktif')->count();

// Cek apakah data ada (return bool)
$ada = $db->table('users')->where('email', 'gilang@example.com')->exists();

if ($ada) {
    echo 'Email sudah terdaftar';
}
```

---

## INSERT

Return `int` (last insert ID) kalau berhasil, `false` kalau gagal.

```php
$newId = $db->table('users')->insert([
    'nama'   => 'Gilang',
    'email'  => 'gilang@example.com',
    'status' => 'aktif',
]);

echo "User baru ID: {$newId}";
```

---

## UPDATE

`where()` **wajib** sebelum `update()` — untuk mencegah update seluruh tabel.

```php
$db->table('users')
   ->where('id', 1)
   ->update([
       'nama'  => 'Gilang Baru',
       'email' => 'baru@example.com',
   ]);
```

Update dengan beberapa kondisi:
```php
$db->table('users')
   ->where('status', 'nonaktif')
   ->where('last_login', '<', '2024-01-01')
   ->update(['deleted' => 1]);
```

---

## DELETE

`where()` **wajib** sebelum `delete()` — untuk mencegah hapus seluruh tabel.

```php
$db->table('users')
   ->where('id', 1)
   ->delete();
```

---

## RAW QUERY

Untuk query kompleks yang tidak bisa ditangani builder. Tetap aman karena pakai binding.

```php
$hasil = $db->raw(
    'SELECT * FROM users WHERE tahun BETWEEN ? AND ?',
    [2020, 2024]
);
```

---

## Chaining Lengkap

Semua method bisa di-chain dalam satu ekspresi:

```php
$data = $db->table('produk')
           ->select('id', 'nama', 'harga', 'stok')
           ->where('aktif', 1)
           ->where('harga', '<=', 500000)
           ->orWhere('stok', '>', 100)
           ->orderBy('harga', 'ASC')
           ->limit(20)
           ->offset(0)
           ->get();
```

---

## Keamanan

| Fitur | Status |
|---|---|
| SQL Injection | ✅ Aman — semua value pakai PDO prepared statement |
| Operator Whitelist | ✅ Operator di-validasi, tidak bisa dimanipulasi |
| Update tanpa WHERE | ✅ Diblokir, throw exception |
| Delete tanpa WHERE | ✅ Diblokir, throw exception |
| Error di production | ✅ Pesan generic, detail tidak terekspos |

> **Catatan:** Column name di `where('column', ...)` tidak di-escape.  
> Pastikan nama kolom selalu dari kode lo sendiri, **bukan dari input user**.

---

## Debugging

Set `'debugging' => true` di `config.php` untuk melihat detail error (SQL, file, line).  
Set `false` di production — user hanya melihat halaman `500 Internal Server Error`.

---

## Referensi Method

| Method | Return | Keterangan |
|---|---|---|
| `table(string $table)` | `static` | Set target tabel, reset semua state |
| `select(string ...$cols)` | `static` | Pilih kolom |
| `where($col, $op, $val?)` | `static` | AND WHERE |
| `orWhere($col, $op, $val?)` | `static` | OR WHERE |
| `orderBy($col, $dir?)` | `static` | ORDER BY |
| `limit(int $n)` | `static` | LIMIT |
| `offset(int $n)` | `static` | OFFSET |
| `get()` | `array` | Ambil semua baris |
| `first()` | `array\|null` | Ambil baris pertama |
| `count()` | `int` | Hitung baris |
| `exists()` | `bool` | Cek keberadaan data |
| `insert(array $data)` | `int\|false` | Insert, return last ID |
| `update(array $data)` | `bool` | Update (wajib where) |
| `delete()` | `bool` | Delete (wajib where) |
| `raw(string $sql, array $bindings)` | `array` | Raw query dengan binding |