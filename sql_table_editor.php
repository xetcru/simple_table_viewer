<?php
$servername = "localhost";
$username = "username";
$password = "password";
$dbname = "dbname";
// Соединение с БД
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}
// Определение текущей выбранной таблицы
$table_name = isset($_GET['table']) ? $_GET['table'] : '';
// Запрос для получения списка таблиц в базе данных
$tablesQuery = "SHOW TABLES";
$tablesResult = $conn->query($tablesQuery);

echo "<!DOCTYPE html><html><head><title>DB viewer</title><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"></head><body style=\"margin:0;\"><div style=\"min-width:200px;max-width:300px;overflow:auto;float:left;border:1px solid #000;margin:5px;padding:5px;\">";
echo "<h3><a href=\"./viewer.php\">Table list:</a></h3>";
echo "<ul style=\"list-style-type:none;margin:0;padding:0;\">";
while ($row = $tablesResult->fetch_row()) {
    $tableName = $row[0];
    $isActive = ($tableName === $table_name) ? 'class="active" style="color:#f00"' : '';
    echo "<li><a href='?table=$tableName' $isActive>$tableName</a> <a href='?delete_table=$tableName'>X</a></li>";
}
echo "</ul></div>";
// Если таблица выбрана, отображаем её содержимое
if (!empty($table_name)) {
    // Весь код, связанный с отображением таблицы, остается без изменений
    $totalRecordsQuery = "SELECT COUNT(*) as total FROM " . $table_name;
    $totalRecordsResult = $conn->query($totalRecordsQuery);
    $totalRecords = $totalRecordsResult->fetch_assoc()["total"];
    // Количество записей на странице
    $recordsPerPage = 50;
    // Общее количество страниц
    $totalPages = ceil($totalRecords / $recordsPerPage);
    // Получаем текущую страницу из параметра "page" в URL
    if (isset($_GET["page"]) && is_numeric($_GET["page"])) {
        $currentPage = $_GET["page"];
    } else {
        $currentPage = 1;
    }
    // Проверяем, чтобы текущая страница не выходила за пределы доступных страниц
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    } elseif ($currentPage < 1) {
        $currentPage = 1;
    }
    // Вычисляем индекс первой записи на странице
    $offset = ($currentPage - 1) * $recordsPerPage;
    // Запрос для получения структуры таблицы
    $structureQuery = "DESCRIBE " . $table_name;
    $structureResult = $conn->query($structureQuery);
    if ($structureResult->num_rows > 0) {
        // Создаем массив для хранения имен полей
        $columns = array();

        // Получаем имена полей из структуры таблицы
        while ($row = $structureResult->fetch_assoc()) {
            $columns[] = $row["Field"];
        }
        // Запрос для получения записей на текущей странице
        $query = "SELECT * FROM ".$table_name." ORDER BY ID ASC LIMIT $offset, $recordsPerPage"; // прямая сортировка
        //$query = "SELECT * FROM ".$table_name." ORDER BY ID DESC LIMIT $offset, $recordsPerPage"; // обратная сортировка
        $result = $conn->query($query);

        if ($result->num_rows > 0) {
            // Выводим таблицу со списком записей
            echo "<table style=\"border: 1px solid;\">
                <tr>";
            // Выводим заголовки полей
            foreach ($columns as $column) {
                echo "<th style=\"border: 1px solid;max-width: 300px;overflow: auto;\">".$column."</th>";
            }
            echo "<th style=\"border: 1px solid;\">EDIT</th>"; // Добавлен столбец EDIT
            echo "</tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                // Выводим значения полей
                foreach ($columns as $column) {
                    echo "<td style=\"border: 1px solid;max-width: 300px;overflow: auto;\">".$row[$column]."</td>";
                }
                //echo "<td style=\"border: 1px solid;\"><a href='?table=$table_name&delete_row=".$row["ID"]."'>X</a></td>";
                echo "<td style=\"border: 1px solid;\"><form method=\"post\" action=\"\"><input type=\"hidden\" name=\"delete_row\" value=\"".$row["id"]."\"/><button type=\"submit\">X</button></form></td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "Нет доступных записей";
        }
        // Выводим постраничную навигацию
        echo "<div class=\"pagination\" style=\"position:fixed;display:flex;justify-content:center;flex-wrap:wrap;bottom:0;background-color:grey;width:100%;margin:0;max-height:100px;overflow-y:scroll;\">";
        for ($i = 1; $i <= $totalPages; $i++) {
            echo "<a href=\"?table=".urlencode($table_name)."&page=".$i."\" style=\"margin:0 1em;\"> [".$i."] </a>";
        }
        echo "</div>";
    } else {
        echo "Ошибка получения структуры таблицы";
    }
} else {
    // Отображение заглавной страницы
    echo "<div style='margin-left: 320px;'>
    <h1>Hello World! This is a script for viewing SQL tables. Enjoy using and have a nice day.</h1>
    <form method='post' action=''>
        <label for='sqlQuery'>Введите SQL-запрос:</label>
        <textarea name='sqlQuery' id='sqlQuery' style='width: 400px; height: 100px;' placeholder='Например, CREATE TABLE IF NOT EXISTS ...'></textarea>
        <button type='submit'>Выполнить</button>
    </form>";
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $sqlQueries = explode(';', $_POST['sqlQuery']);
        foreach ($sqlQueries as $sqlQuery) {
            $result = $conn->query($sqlQuery);
            if ($result === FALSE) {
                echo "<br><br>Ошибка выполнения SQL-запроса: " . $conn->error;
                break;
            }
        }
        echo "<br><br><h2>Результаты запроса:</h2><p>Запрос(ы) успешно выполнен(ы).</p>";
    }
    echo "</div>";
}
echo "</body></html>";
if(isset($_GET['delete_table'])){
    $deleteTable = $_GET['delete_table'];
    $deleteQuery = "DROP TABLE $deleteTable";
    $conn->query($deleteQuery);

    if ($conn->query($deleteQuery) === TRUE) {
        echo "Запись успешно удалена.";
    } else {
        echo "Ошибка при удалении записи: " . $conn->error;
    }
    header("Location: ".$_SERVER['PHP_SELF']);
}
if (isset($_GET['delete_row'])) {
    $deleteID = $_GET['delete_row'];
    $deleteQuery = "DELETE FROM $table_name WHERE ID = $deleteID";

    if ($conn->query($deleteQuery) === TRUE) {
        echo "Запись успешно удалена.";
    } else {
        echo "Ошибка при удалении записи: " . $conn->error;
    }
    header("Location: ".$_SERVER['PHP_SELF']."?table=$table_name");
}
$conn->close();
?>
