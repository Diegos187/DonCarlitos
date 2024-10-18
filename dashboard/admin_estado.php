<?php
session_start();
include('../conexion.php');

// Verificar si el usuario es administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_cargo'] !== 'administrador') {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit();
}

if (isset($_POST['id_form']) && isset($_POST['nuevo_estado'])) {
    $id_form = $_POST['id_form'];
    $nuevo_estado = $_POST['nuevo_estado'];

    // Iniciar transacción
    $conex->begin_transaction();

    // Actualizar el estado de la cita
    $query = "UPDATE Formulario SET estado = ? WHERE id_form = ?";
    $stmt = $conex->prepare($query);
    $stmt->bind_param('si', $nuevo_estado, $id_form);

    if ($stmt->execute()) {
        if ($nuevo_estado === 'cancelado') {
            // Obtener el id_horario de la cita
            $query_horario = "SELECT id_horario FROM Formulario WHERE id_form = ?";
            $stmt_horario = $conex->prepare($query_horario);
            $stmt_horario->bind_param('i', $id_form);
            $stmt_horario->execute();
            $result_horario = $stmt_horario->get_result();

            if ($row_horario = $result_horario->fetch_assoc()) {
                $id_horario = $row_horario['id_horario'];

                // Cambiar el estado del horario a disponible
                $query_liberar_horario = "UPDATE Horario SET estado = 'disponible' WHERE id_horario = ?";
                $stmt_liberar = $conex->prepare($query_liberar_horario);
                $stmt_liberar->bind_param('i', $id_horario);

                if (!$stmt_liberar->execute()) {
                    // Si ocurre un error al liberar el horario, deshacer la transacción
                    $conex->rollback();
                    echo json_encode(['success' => false, 'message' => 'Error al liberar el horario']);
                    exit();
                }
            }
        }
        // Confirmar transacción
        $conex->commit();
        echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
    } else {
        // Deshacer la transacción en caso de error
        $conex->rollback();
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el estado']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
}
?>
