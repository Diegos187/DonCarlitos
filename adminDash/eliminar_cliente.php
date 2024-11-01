<?php
session_start();
include('../conexion.php');

if ($_SESSION['user_cargo'] !== 'administrador' || !isset($_POST['password'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit();
}

// Recibimos los datos enviados desde el formulario
$password = $_POST['password']; // Contraseña del administrador
$clienteId = $_POST['clienteId']; // ID del cliente a eliminar
$rutCliente = $_POST['rutCliente']; // RUT del cliente a eliminar

// Validar la contraseña del administrador
$stmt = $conex->prepare("SELECT password FROM login WHERE id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($hashedPassword); // Obtenemos la contraseña almacenada en la base de datos
$stmt->fetch();
$stmt->close();

if (!password_verify($password, $hashedPassword)) {
    echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta.']);
    exit();
}

// Obtener todos los id_form asociados al cliente en la tabla Citas
$query_id_forms = "SELECT id_form FROM Citas WHERE rut_cliente = ?";
$stmt = $conex->prepare($query_id_forms);
$stmt->bind_param('s', $rutCliente);
$stmt->execute();
$result = $stmt->get_result();

// Guardar los id_form en un array
$id_forms = [];
while ($row = $result->fetch_assoc()) {
    $id_forms[] = $row['id_form'];
}
$stmt->close();

// Borrar todos los mensajes asociados en la tabla Mensajes
if (!empty($id_forms)) {
    // Usamos placeholders para los ID de formularios almacenados
    $placeholders = implode(',', array_fill(0, count($id_forms), '?'));
    $query_delete_messages = "DELETE FROM Mensajes WHERE id_form IN ($placeholders)";
    $stmt = $conex->prepare($query_delete_messages);
    // Asociamos dinámicamente cada ID de formulario en la consulta
    $stmt->bind_param(str_repeat('i', count($id_forms)), ...$id_forms);
    $stmt->execute();
    $stmt->close();
}

// Eliminar asociaciones en la tabla Citas
$stmt = $conex->prepare("DELETE FROM Citas WHERE rut_cliente = ?");
$stmt->bind_param('s', $rutCliente);
$stmt->execute();
$stmt->close();

// Obtener el correo y nombre del cliente para los mensajes de correo
$query_cliente = "SELECT nombre, apellido, email FROM login WHERE id = ?";
$stmt = $conex->prepare($query_cliente);
$stmt->bind_param('i', $clienteId);
$stmt->execute();
$stmt->bind_result($nombreCliente, $apellidoCliente, $correoCliente);
$stmt->fetch();
$stmt->close();

// Eliminar el usuario de la tabla login
$stmt = $conex->prepare("DELETE FROM login WHERE id = ?");
$stmt->bind_param('i', $clienteId);
$stmt->execute();
$stmt->close();

// Enviar correos de notificación
$correo_dueno = 'diegomarin939@gmail.com';
$asunto = "Notificación de Eliminación de Cuenta";

// Mensaje para el cliente
$mensaje_cliente = "
    <h3>Estimado/a $nombreCliente $apellidoCliente,</h3>
    <p>Le informamos que su cuenta ha sido eliminada del sistema. Si desea volver a gestionar citas, deberá registrarse nuevamente.</p>
    <p>Gracias por su comprensión.</p>
";

// Mensaje para el dueño/administrador
$mensaje_dueno = "
    <h3>Estimado/a Administrador,</h3>
    <p>La cuenta del cliente <strong>$nombreCliente $apellidoCliente</strong> con RUT <strong>$rutCliente</strong> ha sido eliminada del sistema.</p>
    <p>Correo del cliente eliminado: $correoCliente</p>
    <p>Atentamente, el equipo de soporte.</p>
";

// Configurar el encabezado de los correos
$headers = "From: Servicio Técnico <no-reply@doncarlos.com>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-type: text/html; charset=UTF-8\r\n";

// Enviar correos
mail($correoCliente, $asunto, $mensaje_cliente, $headers);
mail($correo_dueno, $asunto, $mensaje_dueno, $headers);

echo json_encode(['success' => true, 'message' => 'Cliente eliminado exitosamente y notificaciones enviadas.']);
?>
