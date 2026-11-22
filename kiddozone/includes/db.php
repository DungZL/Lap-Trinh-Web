<?php
// Kết nối MySQL bằng PDO. Luôn dùng prepared statement: db()->prepare(...)->execute([...]).
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // Đồng bộ giờ MySQL với PHP (Asia/Ho_Chi_Minh) để NOW() khớp khi so thời gian flash sale, mã giảm giá.
        $pdo->exec("SET time_zone = '+07:00'");
    }
    return $pdo;
}

/** Chạy truy vấn có tham số, trả về PDOStatement. */
function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
