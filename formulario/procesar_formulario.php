<?php
include('../conexion.php');
session_start(); // Iniciar sesión para mostrar mensajes de éxito o error si es necesario

// Sanitización y validación de los datos recibidos del formulario
$nombre = htmlspecialchars(trim($_POST['nombre']));
$apellido = htmlspecialchars(trim($_POST['apellido']));
$rut = htmlspecialchars(trim($_POST['rut']));
$direccion = htmlspecialchars(trim($_POST['direccion']));
$telefono = filter_var(trim($_POST['telefono']), FILTER_SANITIZE_NUMBER_INT);
$correo = filter_var(trim($_POST['correo']), FILTER_SANITIZE_EMAIL);
$detalles = htmlspecialchars(trim($_POST['detalles']));
$servicio_id = filter_var($_POST['servicio'], FILTER_VALIDATE_INT);
$producto_id = filter_var($_POST['producto'], FILTER_VALIDATE_INT);
$horario_id = filter_var($_POST['horario'], FILTER_VALIDATE_INT);

// Validación adicional de datos
if (!$nombre || !$apellido || !$rut || !$direccion || !$telefono || !$correo || !$detalles || !$servicio_id || !$producto_id || !$horario_id) {
    $_SESSION['error'] = "Todos los campos son obligatorios y deben estar en el formato correcto.";
    header('Location: formulario.php');
    exit;
}

// Consultar el nombre del servicio usando consulta preparada
$queryServicio = "SELECT tipo_servicio FROM Servicio WHERE id_servicio = ?";
$stmtServicio = $conex->prepare($queryServicio);
$stmtServicio->bind_param("i", $servicio_id);
$stmtServicio->execute();
$resultServicio = $stmtServicio->get_result();
$servicio = $resultServicio->fetch_assoc()['tipo_servicio'];
$stmtServicio->close();

// Consultar el nombre del producto usando consulta preparada
$queryProducto = "SELECT tipo_producto FROM Producto WHERE id_producto = ?";
$stmtProducto = $conex->prepare($queryProducto);
$stmtProducto->bind_param("i", $producto_id);
$stmtProducto->execute();
$resultProducto = $stmtProducto->get_result();
$producto = $resultProducto->fetch_assoc()['tipo_producto'];
$stmtProducto->close();

// Consultar la hora del horario usando consulta preparada
$queryHorario = "SELECT hora_disponible FROM Horario WHERE id_horario = ?";
$stmtHorario = $conex->prepare($queryHorario);
$stmtHorario->bind_param("i", $horario_id);
$stmtHorario->execute();
$resultHorario = $stmtHorario->get_result();
$horario = $resultHorario->fetch_assoc()['hora_disponible'];
$stmtHorario->close();

// Insertar datos en la tabla Formulario usando consulta preparada
$query = "INSERT INTO Formulario (nombre, apellido, rut, direccion, telefono, correo, detalles, id_servicio, id_producto, id_horario) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmtInsert = $conex->prepare($query);
$stmtInsert->bind_param("sssssssiii", $nombre, $apellido, $rut, $direccion, $telefono, $correo, $detalles, $servicio_id, $producto_id, $horario_id);

if ($stmtInsert->execute()) {
    // Obtener el ID del formulario recién insertado
    $id_form = $conex->insert_id;

    // Verificar si el RUT ingresado ya está registrado en la tabla login usando consulta preparada
    $query_verificar_rut = "SELECT * FROM login WHERE rut = ?";
    $stmtVerificarRUT = $conex->prepare($query_verificar_rut);
    $stmtVerificarRUT->bind_param("s", $rut);
    $stmtVerificarRUT->execute();
    $resultRUT = $stmtVerificarRUT->get_result();

    if ($resultRUT->num_rows > 0) {
        // Si el RUT está registrado en login, asociar la cita a la tabla Citas usando consulta preparada
        $query_insert_cita = "INSERT INTO Citas (id_form, rut_cliente) VALUES (?, ?)";
        $stmtInsertCita = $conex->prepare($query_insert_cita);
        $stmtInsertCita->bind_param("is", $id_form, $rut);
        $stmtInsertCita->execute();
        $stmtInsertCita->close();
    }
    $stmtVerificarRUT->close();

    // Actualizar el horario a 'reservado' usando consulta preparada
    $queryUpdateHorario = "UPDATE Horario SET estado = 'reservado' WHERE id_horario = ?";
    $stmtUpdateHorario = $conex->prepare($queryUpdateHorario);
    $stmtUpdateHorario->bind_param("i", $horario_id);
    $stmtUpdateHorario->execute();
    $stmtUpdateHorario->close();

    // Enviar el correo electrónico de confirmación
    $subject = "Siguiente paso de Cita";
    $body = "Hola $nombre $apellido,\n\nGracias por agendar una cita con nosotros.\n\nDetalles de la cita:\n\nServicio: $servicio\nProducto: $producto\nFecha y hora: $horario\n\n ESTE ES SOLO UN MENSAJE DE PRUEBA \n\nNos pondremos en contacto con usted en el teléfono: $telefono.\n\nSaludos,\nCentro Técnico DC";
    $headers = "From: diegomarin939@gmail.com";

    if (mail($correo, $subject, $body, $headers)) {
        echo "El correo electrónico se envió correctamente.";
    } else {
        echo "Hubo un error al enviar el correo electrónico.";
    }

    // Redirigir a la página de éxito
    header('Location: cita_exitosa.html');
    exit;
} else {
    // Redirigir a la página de error
    header('Location: cita_mal.html');
    exit;
}

// Cerrar conexiones y liberar recursos
$stmtInsert->close();
$conex->close();
?>
