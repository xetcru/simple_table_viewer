<?php
class DatabaseViewer {
    private $conn;

    public function __construct($servername, $username, $password, $dbname) {
        $this->conn = new mysqli($servername, $username, $password, $dbname);
        if ($this->conn->connect_error) {
            die("Ошибка подключения: " . $this->conn->connect_error);
        }
        // Устанавливаем отчетность о всех ошибках
        $this->conn->set_charset("utf8mb4");
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    public function render() {
        $tableName = isset($_GET['table']) ? $_GET['table'] : '';
        $this->renderTableList($tableName);
        if (!empty($tableName)) {
            $this->renderTableContent($tableName);
        } else {
            $this->renderWelcomePage();
        }
    }

    private function renderTableList($currentTable) {
        $tablesQuery = "SHOW TABLES";
        $tablesResult = $this->conn->query($tablesQuery);

        // Вывод боковой панели с таблицами
        echo <<<HTML
        <!DOCTYPE html>
        <html lang="ru">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>DB Viewer</title>
            <style>
                body { font-family: Arial, sans-serif; }
                .sidebar { position: fixed; float: left; width: 200px; height: 90%; padding: 10px; border: 1px solid #000; overflow-x: scroll; overflow-y: scroll; }
                .sidebar ul { list-style-type:none;margin:0;padding:0;}
                .sidebar ul li { display: flex; }
                .content { margin-left: 220px; padding: 10px; }
                .active { color: #f00; }
                table { border-collapse: collapse; width: 100%; margin-top: 20px; }
                th, td { border: 1px solid #000; padding: 5px; text-align: left; }
                .pagination a { margin: 0 5px; text-decoration: none; }
            </style>
        </head>
        <body>
        <div class="sidebar">
            <h3><a href="{$_SERVER['PHP_SELF']}">Table list:</a></h3>
            <ul>
HTML;
        while ($row = $tablesResult->fetch_row()) {
            $tableName = $row[0];
            $isActive = ($tableName === $currentTable) ? 'class="active" style="color:#f00"' : '';
            echo "<li><a href='?table=$tableName' $isActive>$tableName</a> <a href='?delete_table=$tableName'>X</a></li>";
        }
        echo "</ul></div>"; // Закрытие боковой панели
    }

    private function renderTableContent($tableName) {
        // Проверка существования таблицы
        $tableExists = $this->conn->query("SHOW TABLES LIKE '$tableName'");
        if ($tableExists->num_rows === 0) {
            echo "<div class='content'><h3>Таблица '$tableName' не существует.</h3></div>";
            return;
        }

        // Пагинация
        $recordsPerPage = 50; // Количество записей на странице
        $currentPage = max(1, intval($_GET['page'] ?? 1));
        $totalRecords = $this->conn->query("SELECT COUNT(*) as total FROM `$tableName`")->fetch_assoc()['total'] ?? 0;
        $totalPages = max(1, ceil($totalRecords / $recordsPerPage));
        $currentPage = min($currentPage, $totalPages);
        $offset = ($currentPage - 1) * $recordsPerPage;

        // Структура таблицы
        $columns = [];
        $structureResult = $this->conn->query("DESCRIBE `$tableName`");
        if ($structureResult) {
            while ($row = $structureResult->fetch_assoc()) {
                $columns[] = $row['Field'];
            }
        }

        // Данные таблицы
        $result = $this->conn->query("SELECT * FROM `$tableName` ORDER BY ID ASC LIMIT $offset, $recordsPerPage");

        if ($result && $result->num_rows > 0) {
            // Вывод таблицы с данными
            echo "<div class='content'><table><tr>";
            foreach ($columns as $column) {
                echo "<th>{$column}</th>";
            }
            echo "</tr>";

            // Отображение строк таблицы
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                foreach ($columns as $column) {
                    echo "<td>" . htmlspecialchars($row[$column]) . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";

            // Пагинация
            echo "<div class='pagination'>";
            for ($i = 1; $i <= $totalPages; $i++) {
                $class = ($i === $currentPage) ? 'style="font-weight:bold;"' : '';
                echo "<a href='?table=$tableName&page=$i' $class>$i</a>";
            }
            echo "</div>";
        } else {
            echo "<div class='content'><p>Нет данных для отображения.</p></div>";
        }
    }

    private function renderWelcomePage() {
        echo "<div class='content'>
            <h1>Привет! Это скрипт для просмотра SQL таблиц. Наслаждайтесь!</h1>
            <form method='post' action=''>
                <label for='sqlQuery'>Введите SQL-запрос:</label>
                <textarea name='sqlQuery' id='sqlQuery' style='width: 400px; height: 100px;' placeholder='Например, CREATE TABLE IF NOT EXISTS ...'></textarea>
                <button type='submit'>Выполнить</button>
            </form>";
    
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $sqlQueries = explode(';', $_POST['sqlQuery']);
            foreach ($sqlQueries as $sqlQuery) {
                $sqlQuery = trim($sqlQuery);
                if (empty($sqlQuery)) continue;
    
                try {
                    $result = $this->conn->query($sqlQuery);
    
                    if (is_object($result)) {
                        if ($result->num_rows > 0) {
                            echo "<br><br><h2>Результаты запроса:</h2><table border='1'><tr>";
    
                            $fields = $result->fetch_fields();
                            foreach ($fields as $field) {
                                echo "<th>" . htmlspecialchars($field->name) . "</th>";
                            }
                            echo "</tr>";
    
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>";
                                foreach ($row as $value) {
                                    echo "<td>" . htmlspecialchars($value) . "</td>";
                                }
                                echo "</tr>";
                            }
                            echo "</table>";
                        } else {
                            echo "<br><br><h2>Результаты запроса:</h2><p>Запрос выполнен, но данных нет.</p>";
                        }
                    } else {
                        echo "<br><br><h2>Результаты запроса:</h2><p>Запрос(ы) успешно выполнен(ы).</p>";
                    }
                } catch (Exception $e) {
                    echo "<br><br>Ошибка выполнения SQL-запроса: " . htmlspecialchars($e->getMessage());
                    break;
                }
            }
        }
    
        echo "</div>";
    }

    public function deleteTable($tableName) {
        $deleteQuery = "DROP TABLE `$tableName`";
        $this->conn->query($deleteQuery);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    public function deleteRow($tableName, $rowID) {
        $deleteQuery = "DELETE FROM `$tableName` WHERE ID = ?";
        $stmt = $this->conn->prepare($deleteQuery);
        $stmt->bind_param("i", $rowID);
        $stmt->execute();
        $stmt->close();
    
        header("Location: " . $_SERVER['PHP_SELF'] . "?table=" . urlencode($tableName));
        exit;
    }

    public function __destruct() {
        $this->conn->close();
    }
}

// Конфигурация подключения
$viewer = new DatabaseViewer("localhost", "username", "password", "dbname");

// Удаление таблицы или строки, если переданы соответствующие параметры
if (isset($_GET['delete_table'])) {
    $viewer->deleteTable($_GET['delete_table']);
}

if (isset($_GET['delete_row']) && isset($_GET['table'])) {
    $viewer->deleteRow($_GET['table'], $_GET['delete_row']);
}

// Отображение интерфейса
$viewer->render();
?>
