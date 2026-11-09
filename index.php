<?php
require __DIR__ . '/conexion.php';

$consulta = $conexion->query(
    'SELECT id, nombre, faccion, puntos FROM miniaturas ORDER BY id ASC'
);
$miniaturas = $consulta->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Catálogo de Miniaturas</title>
</head>
<body>
  <h1>Catálogo de Miniaturas</h1>
  <table border="1" cellpadding="8">
    <tr>
      <th>ID</th>
      <th>Nombre</th>
      <th>Facción</th>
      <th>Puntos</th>
    </tr>
    <?php foreach ($miniaturas as $m): ?>
      <tr>
        <td><?= (int) $m['id'] ?></td>
        <td><?= htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($m['faccion'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= (int) $m['puntos'] ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <h2>Registrar miniatura</h2>
  <form action="guardar.php" method="post">
    <label>Nombre: <input type="text" name="nombre" required maxlength="100"></label><br><br>
    <label>Facción: <input type="text" name="faccion" required maxlength="100"></label><br><br>
    <label>Puntos: <input type="number" name="puntos" required min="1" max="5000"></label><br><br>
    <button type="submit">Guardar</button>
  </form>
</body>
</html>