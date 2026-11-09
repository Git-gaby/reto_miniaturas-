# Catálogo de Miniaturas - Práctica XAMPP, PHP y MySQL

Aplicación web monolítica basada en arquitectura cliente-servidor para la consulta e inserción de registros en base de datos relacional, implementada con PHP (PDO), Apache y MariaDB/MySQL mediante XAMPP.

---

## 1. Arquitectura y Tecnologías

El flujo de ejecución del sistema sigue el estándar de aplicaciones web dinámicas:

```text
[ Navegador Web ]
       │  (Petición HTTP / Formulario POST)
       ▼
 [ Servidor Apache ]  (Puerto 80 / 8080)
       │  (Ejecución del intérprete)
       ▼
  [ Runtime PHP ]
       │  (Capa de abstracción PDO)
       ▼
[ MariaDB / MySQL ]  (Puerto TCP 3306)
```

| Componente | Función en el sistema | Puerto / Protocolo |
|---|---|---|
| Apache | Servidor web que atiende solicitudes HTTP y procesa los scripts PHP | Puerto 80 (HTTP) |
| PHP | Lenguaje de servidor que genera el HTML dinámico y maneja la lógica de negocio | Local / FastCGI |
| MariaDB | Motor de base de datos relacional que almacena esquemas y tablas | Puerto 3306 (TCP) |
| phpMyAdmin | Interfaz administrativa web para gestión de esquemas y usuarios | http://localhost/phpmyadmin/ |

> **Nota técnica sobre puertos:**
> La dirección `http://localhost:3306` es inválida en un navegador debido a que 3306 corresponde al canal binario TCP de MySQL y no a un protocolo web. La interfaz web corre exclusivamente sobre Apache (`http://localhost/reto_miniaturas/`).

---

## 2. Base de Datos y Seguridad de Acceso

Para garantizar el principio de mínimo privilegio, la aplicación no opera con la cuenta administrativa `root`. Se creó una base de datos específica (`catalogo_db`) y un usuario restringido (`reto_user`) con permisos exclusivos de `SELECT` e `INSERT`.

### Script de despliegue (DDL / DML)

```sql
-- 1. Creación del esquema
CREATE DATABASE IF NOT EXISTS catalogo_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE catalogo_db;

-- 2. Creación de la tabla
CREATE TABLE IF NOT EXISTS miniaturas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  faccion VARCHAR(100) NOT NULL,
  puntos INT NOT NULL
);

-- 3. Inserción de registros iniciales
INSERT INTO miniaturas (nombre, faccion, puntos) VALUES
('Trajann Valoris', 'Adeptus Custodes', 145),
('Allarus Custodians', 'Adeptus Custodes', 180);

-- 4. Creación y asignación del usuario de la aplicación
CREATE USER IF NOT EXISTS 'reto_user'@'localhost'
IDENTIFIED BY 'Reto_Miniaturas_2026!';

GRANT SELECT, INSERT ON catalogo_db.*
TO 'reto_user'@'localhost';

FLUSH PRIVILEGES;
```

---

## 3. Estructura del Proyecto

Los archivos se ubican en la raíz pública del servidor web: `C:\xampp\htdocs\reto_miniaturas\`

```plaintext
reto_miniaturas/
├── conexion.php   # Configuración de PDO, DSN y manejo de excepciones
├── index.php      # Renderizado de tabla (ASC), escape XSS y formulario HTML
└── guardar.php    # Validación en backend, sentencias preparadas y redirección PRG
```

---

## 4. Implementación del Código

### `conexion.php`

Centraliza la conexión con MariaDB implementando control de errores mediante bloques `try/catch`, logging en servidor y desactivación de sentencias emuladas.

```php
<?php
$servidor   = 'localhost';
$puerto     = '3306';
$baseDatos  = 'catalogo_db';
$usuario    = 'reto_user';
$contrasena = 'Reto_Miniaturas_2026!';

$dsn = "mysql:host=$servidor;port=$puerto;dbname=$baseDatos;charset=utf8mb4";

try {
    $conexion = new PDO($dsn, $usuario, $contrasena, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('No fue posible conectar con la base de datos.');
}
```

### `index.php`

Consulta los datos en orden ascendente (`ORDER BY id ASC`), protege contra vulnerabilidades XSS mediante `htmlspecialchars()` y expone el formulario de entrada.

```php
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
```

### `guardar.php`

Aplica el patrón POST/Redirect/GET (PRG). Valida la petición, sanitiza campos, previene Inyección SQL mediante marcadores de posición (`prepare` y `execute`) y responde con código HTTP 303.

```php
<?php
require __DIR__ . '/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

$nombre  = trim($_POST['nombre'] ?? '');
$faccion = trim($_POST['faccion'] ?? '');
$puntos  = filter_var($_POST['puntos'] ?? null, FILTER_VALIDATE_INT);

if (
    $nombre === '' || $faccion === '' ||
    $puntos === false || $puntos <= 0 ||
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
```

---

## 5. Preguntas de Comprobación y Sustentación Técnica

### ¿Por qué `http://localhost:3306` no es una dirección válida para abrir phpMyAdmin?

Porque 3306 es el puerto TCP exclusivo del motor de datos (MySQL), el cual maneja un protocolo binario propietario. Los navegadores web únicamente interpretan protocolos HTTP/HTTPS, por lo que phpMyAdmin debe accederse a través del servidor Apache (puerto 80 u 8080).

### ¿Cuál es la diferencia entre Apache y MySQL?

Apache es un servidor web HTTP encargado de recibir solicitudes de clientes, resolver archivos estáticos y enviar scripts PHP al motor de ejecución. MySQL es un Sistema Gestor de Bases de Datos Relacionales (SGBD) encargado de organizar, almacenar y consultar datos en disco.

### ¿Por qué un archivo PHP no debe abrirse con doble clic?

Al hacer doble clic, el archivo se abre en el navegador mediante el protocolo local `file:///`. El navegador no posee un intérprete de PHP integrado, por lo que mostraría el código fuente como texto plano sin procesar. Para ejecutarse, requiere pasar por el servidor web (`http://localhost/...`).

### ¿Qué cambia en `conexion.php` si MySQL escucha en el puerto 3307?

Debe ajustarse la variable de puerto a `$puerto = '3307';`, modificando la cadena DSN de conexión para que el conector de PDO dirija la conexión TCP al socket correspondiente.

### ¿Qué diferencia existe entre `query()`, `prepare()` y `execute()`?

- **`query()`**: Ejecuta directamente una instrucción SQL en el motor sin parametrización (apropiado para consultas estáticas sin entrada de usuario).
- **`prepare()`**: Envía la estructura de la consulta al motor para ser precompilada con marcadores de posición (`:param`), aislando la lógica SQL de los datos.
- **`execute()`**: Envía los parámetros por separado para rellenar los marcadores y ejecutar la sentencia, neutralizando la inyección SQL.

### ¿Por qué no debemos mostrar directamente en HTML los datos de la base sin `htmlspecialchars()`?

Para mitigar ataques de Cross-Site Scripting (XSS). Si un registro contiene caracteres especiales como `<script>` o atributos HTML maliciosos, `htmlspecialchars()` los convierte en entidades inocuas (`&lt;script&gt;`), previniendo su ejecución en el navegador.

### ¿Por qué es preferible un usuario dedicado (`reto_user`) frente a `root`?

Por el principio de mínimo privilegio. Si la aplicación web sufre una vulnerabilidad de ejecución remota o inyección, el atacante solo tendrá acceso a ejecutar `SELECT` e `INSERT` en esa tabla específica, evitando que tome control administrativo del servidor, modifique otros esquemas o destruya datos con sentencias `DROP`.
