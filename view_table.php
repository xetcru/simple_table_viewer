<?php
$servername = "localhost";
$username = "username";
$password = "password";
$dbname = "dbname";

// Соединение с базой данных
$conn = new mysqli($dbConfig['servername'], $dbConfig['username'], $dbConfig['password'], $dbConfig['dbname']);
if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}

// Определение текущей выбранной таблицы
$tableName = $_GET['table'] ?? '';

// Получение списка таблиц
$tablesResult = $conn->query("SHOW TABLES");
if (!$tablesResult) {
    die("Ошибка получения списка таблиц: " . $conn->error);
}

// Вывод HTML
echo <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DB Viewer</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .sidebar { position: fixed; float: left; width: 200px; height: 90%; padding: 10px; background-color: #f1f1f1; border: 1px solid #000; overflow-x: scroll; overflow-y: scroll; }
        .content { margin-left: 220px; padding: 10px; }
        .active { color: #f00; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #000; padding: 5px; text-align: left; }
        .pagination a { margin: 0 5px; text-decoration: none; }
    </style>
</head>
<body>
<div class="sidebar">
    <h3>Список таблиц:</h3>
    <ul>
HTML;

while ($row = $tablesResult->fetch_row()) {
    $isActive = ($row[0] === $tableName) ? 'class="active"' : '';
    echo "<li><a href='?table={$row[0]}' $isActive>{$row[0]}</a></li>";
}

echo <<<HTML
    </ul>
</div>
<div class="content">
HTML;

if ($tableName) {
    // Проверка существования таблицы
    $tableExists = $conn->query("SHOW TABLES LIKE '$tableName'");
    if ($tableExists->num_rows === 0) {
        echo "<p>Таблица не существует.</p>";
    } else {
        // Пагинация
        $recordsPerPage = 50;
        $currentPage = max(1, intval($_GET['page'] ?? 1));
        $totalRecords = $conn->query("SELECT COUNT(*) as total FROM $tableName")->fetch_assoc()['total'] ?? 0;
        $totalPages = max(1, ceil($totalRecords / $recordsPerPage));
        $currentPage = min($currentPage, $totalPages);
        $offset = ($currentPage - 1) * $recordsPerPage;

        // Структура таблицы
        $columns = [];
        $structureResult = $conn->query("DESCRIBE $tableName");
        if ($structureResult) {
            while ($row = $structureResult->fetch_assoc()) {
                $columns[] = $row['Field'];
            }
        }

        // Данные таблицы
        $result = $conn->query("SELECT * FROM $tableName ORDER BY ID ASC LIMIT $offset, $recordsPerPage");

        if ($result && $result->num_rows > 0) {
            echo "<table><tr>";
            foreach ($columns as $column) {
                echo "<th>{$column}</th>";
            }
            echo "</tr>";

            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                foreach ($columns as $column) {
                    echo "<td>" . htmlspecialchars($row[$column]) . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";

            // Навигация
            echo "<div class='pagination'>";
            for ($i = 1; $i <= $totalPages; $i++) {
                $class = ($i === $currentPage) ? 'style="font-weight:bold;"' : '';
                echo "<a href='?table=$tableName&page=$i' $class>$i</a>";
            }
            echo "</div>";
        } else {
            echo "<p>Нет данных для отображения.</p>";
        }
    }
} else {
    echo "<h1>Добро пожаловать!</h1><p>Выберите таблицу для просмотра её содержимого.</p>";
}

echo <<<HTML
</div>
</body>
</html>
HTML;

$conn->close();
?>
