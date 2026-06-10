<?php
// db_functions.php

function getDatabaseConnection() {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "flover";

    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Ошибка подключения к базе данных: " . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

function getCatalogWithCategories() {
    $conn = getDatabaseConnection();

    $sql = "SELECT id, name, price, image, categori, kol_vo, opisanie FROM flovers";
    $result = $conn->query($sql);

    $catalogData = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categoryName = trim($row['categori'] ?? '') ?: 'uncategorized';

            // Приведение типов и обеспечение полей
            $row['id'] = isset($row['id']) ? (int)$row['id'] : 0;
            $row['kol_vo'] = isset($row['kol_vo']) ? (int)$row['kol_vo'] : 0;
            $row['price'] = isset($row['price']) ? (float)$row['price'] : 0.0;
            $row['name'] = $row['name'] ?? '';
            $row['image'] = $row['image'] ?? '';
            $row['opisanie'] = $row['opisanie'] ?? '';

            if (!isset($catalogData[$categoryName])) {
                $catalogData[$categoryName] = [];
            }

            $catalogData[$categoryName][] = $row;
        }

        // Опционально: отсортируем категории по имени и товары внутри категории по name
        ksort($catalogData, SORT_STRING | SORT_FLAG_CASE);
        foreach ($catalogData as &$items) {
            usort($items, function($a, $b) {
                return mb_strtolower($a['name']) <=> mb_strtolower($b['name']);
            });
        }
        unset($items);
    }

    if ($result) $result->free();
    $conn->close();

    return $catalogData;
}
?>
