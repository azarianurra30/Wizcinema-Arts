<?php
// File ini hanya meneruskan request ke struk_film.php
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
header("Location: struk_film.php?id=" . $id);
exit;