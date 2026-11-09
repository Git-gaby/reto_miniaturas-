<?php
require __DIR__ . '/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

$nombre = trim($_POST['nombre'] ?? '');
$faccion = trim($_POST['faccion'] ?? '');
$puntos = filter_var($_POST['puntos'] ?? null, FILTER_VALIDATE_INT);

if (
    $nombre === '' || 
    $faccion === '' || 
    $puntos === false || 
    $puntos <= 0 ||
    strlen($nombre) > 100 || 
    strlen($faccion) > 100
) {
    http_response_code(400);
    exit('Datos inválidos. Verifique los datos del formulario.');
}

try {
    $sql = 'INSERT INTO miniaturas (nombre, faccion, puntos) VALUES (:nombre, :faccion, :puntos)';
    $sentencia = $conexion->prepare($sql);
    $sentencia->execute([
        ':nombre'  => $nombre,
        ':faccion' => $faccion,
        ':puntos'  => $puntos
    ]);
    header('Location: index.php', true, 303);
    exit;
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('No se pudo registrar la miniatura.');
}