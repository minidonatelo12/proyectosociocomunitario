
<?php
require_once 'config.php';
require_admin();

$page_title = 'Préstamos y devoluciones';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    // REGISTRAR PRÉSTAMO
    if ($action === 'loan') {

        $teacher = (int)($_POST['teacher_id'] ?? 0);
        $material = (int)($_POST['material_id'] ?? 0);
        $qtyText = $_POST['quantity'] ?? '';
        $expected = $_POST['expected_return'] ?? '';

        if (!preg_match('/^[1-9][0-9]*$/', (string)$qtyText)) {
            flash('La cantidad prestada debe ser un número entero mayor que cero.', 'error');
            header('Location: loans.php');
            exit;
        }

        $qty = (int)$qtyText;

        try {

            $pdo->beginTransaction();

            $u = $pdo->prepare(
                "SELECT id FROM users
                 WHERE id = ? AND role = 'docente' AND active = 1"
            );
            $u->execute([$teacher]);

            $mat = $pdo->prepare(
                "SELECT id, available_qty
                 FROM materials
                 WHERE id = ?
                 FOR UPDATE"
            );
            $mat->execute([$material]);
            $stock = $mat->fetch();

            if (!$u->fetch()) {
                throw new Exception('Debe seleccionar un docente válido.');
            }

            if (!$stock) {
                throw new Exception('El material seleccionado no existe.');
            }

            $available = (int)$stock['available_qty'];

            if ($qty <= 0) {
                throw new Exception('La cantidad debe ser mayor que cero.');
            }

            if ($qty > $available) {
                throw new Exception(
                    'Stock insuficiente. Disponible: ' . $available
                );
            }

            if ($expected !== '') {
                $date = DateTime::createFromFormat('Y-m-d\TH:i', $expected);

                if (!$date || $date->format('Y-m-d\TH:i') !== $expected) {
                    throw new Exception('Fecha de devolución no válida.');
                }
            }

            $s = $pdo->prepare(
                "INSERT INTO loans
                (teacher_id, material_id, quantity, expected_return, registered_by)
                VALUES (?, ?, ?, ?, ?)"
            );

            $s->execute([
                $teacher,
                $material,
                $qty,
                $expected !== '' ? str_replace('T', ' ', $expected) . ':00' : null,
                $_SESSION['user']['id']
            ]);

            $update = $pdo->prepare(
                "UPDATE materials
                 SET available_qty = available_qty - ?
                 WHERE id = ? AND available_qty >= ?"
            );

            $update->execute([$qty, $material, $qty]);

            if ($update->rowCount() !== 1) {
                throw new Exception('No se pudo actualizar el stock.');
            }

            $pdo->commit();

            flash('Préstamo registrado correctamente.');

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            flash($e->getMessage(), 'error');
        }
    }

    // REGISTRAR DEVOLUCIÓN
    elseif ($action === 'return') {

        $id = (int)($_POST['loan_id'] ?? 0);
        $condition = $_POST['return_condition'] ?? '';
        $obs = trim($_POST['observations'] ?? '');

        try {

            if (!in_array($condition, ['Óptimo', 'Con desgaste', 'Inservible/Dañado'], true)) {
                throw new Exception('Estado de devolución no válido.');
            }

            $pdo->beginTransaction();

            $s = $pdo->prepare(
                "SELECT * FROM loans
                 WHERE id = ? AND status = 'Activo'
                 FOR UPDATE"
            );
            $s->execute([$id]);

            $loan = $s->fetch();

            if (!$loan) {
                throw new Exception('No se encontró un préstamo activo.');
            }

            $usable = $condition !== 'Inservible/Dañado';

            $newCondition = $condition === 'Óptimo'
                ? 'Bueno'
                : ($condition === 'Con desgaste' ? 'Regular' : 'Dañado/Baja');

            $update = $pdo->prepare(
                "UPDATE materials
                 SET available_qty = available_qty + ?,
                     condition_status = ?
                 WHERE id = ?"
            );

            $update->execute([
                $usable ? $loan['quantity'] : 0,
                $newCondition,
                $loan['material_id']
            ]);

            $updateLoan = $pdo->prepare(
                "UPDATE loans
                 SET returned_at = NOW(),
                     return_condition = ?,
                     observations = ?,
                     status = 'Concluido'
                 WHERE id = ?"
            );

            $updateLoan->execute([
                $condition,
                $obs,
                $id
            ]);

            $pdo->commit();

            flash('Devolución registrada correctamente.');

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            flash($e->getMessage(), 'error');
        }
    }

    header('Location: loans.php');
    exit;
}

// DATOS PARA FORMULARIOS
$teachers = $pdo->query(
    "SELECT id, full_name
     FROM users
     WHERE role = 'docente' AND active = 1
     ORDER BY full_name"
)->fetchAll();

$materials = $pdo->query(
    "SELECT id, name, available_qty
     FROM materials
     WHERE available_qty > 0
     ORDER BY name"
)->fetchAll();

$active = $pdo->query(
    "SELECT l.*, u.full_name AS teacher, m.name AS material,
            m.available_qty
     FROM loans l
     JOIN users u ON u.id = l.teacher_id
     JOIN materials m ON m.id = l.material_id
     WHERE l.status = 'Activo'
     ORDER BY l.loan_date DESC"
)->fetchAll();

require 'header.php';
?>

<h1>Préstamos y devoluciones</h1>

<div class="panel">

<h2>Registrar préstamo</h2>

<form method="POST" class="form-grid" id="loanForm">

<input type="hidden" name="action" value="loan">

<div>
<label>Docente</label>

<select name="teacher_id" required>
<option value="">Seleccione docente</option>

<?php foreach ($teachers as $t): ?>
<option value="<?= $t['id'] ?>">
<?= e($t['full_name']) ?>
</option>
<?php endforeach; ?>

</select>
</div>

<div>
<label>Material deportivo</label>

<select name="material_id" id="material" required>
<option value="">Seleccione material</option>

<?php foreach ($materials as $m): ?>
<option value="<?= $m['id'] ?>"
data-stock="<?= $m['available_qty'] ?>">

<?= e($m['name']) ?>
(Disponible: <?= $m['available_qty'] ?>)

</option>
<?php endforeach; ?>

</select>
</div>

<div>
<label>Cantidad</label>
<input type="number" name="quantity" id="quantity"
min="1" step="1" value="1" required>
<small id="stockMessage" class="field-error"></small>
</div>

<div>
<label>Fecha estimada de devolución</label>
<input type="datetime-local" name="expected_return">
</div>

<div class="span-all">
<button class="btn primary" id="loanButton">
Registrar préstamo
</button>
</div>

</form>

</div>

<div class="panel">

<h2>Préstamos activos</h2>

<div class="table-wrap">

<table>

<tr>
<th>ID</th>
<th>Docente</th>
<th>Material</th>
<th>Cantidad</th>
<th>Fecha</th>
<th>Devolución</th>
</tr>

<?php foreach ($active as $l): ?>

<tr>

<td><?= $l['id'] ?></td>
<td><?= e($l['teacher']) ?></td>
<td><?= e($l['material']) ?></td>
<td><?= $l['quantity'] ?></td>
<td><?= e($l['loan_date']) ?></td>

<td>

<form method="POST" class="return-form">

<input type="hidden" name="action" value="return">
<input type="hidden" name="loan_id" value="<?= $l['id'] ?>">

<select name="return_condition" required>
<option value="Óptimo">Óptimo</option>
<option value="Con desgaste">Con desgaste</option>
<option value="Inservible/Dañado">Dañado</option>
</select>

<input type="text" name="observations"
placeholder="Observaciones">

<button class="btn primary small">
Devolver
</button>

</form>

</td>

</tr>

<?php endforeach; ?>

<?php if (!$active): ?>
<tr><td colspan="6">No existen préstamos activos.</td></tr>
<?php endif; ?>

</table>

</div>
</div>

<script>
const material = document.getElementById('material');
const quantity = document.getElementById('quantity');
const loanButton = document.getElementById('loanButton');
const stockMessage = document.getElementById('stockMessage');

function validateLoan() {

    const option = material.options[material.selectedIndex];

    const stock = option && option.dataset.stock
        ? Number(option.dataset.stock)
        : 0;

    const qty = Number(quantity.value);

    const valid = material.value !== '' &&
        Number.isInteger(qty) &&
        qty > 0 &&
        qty <= stock;

    loanButton.disabled = !valid;

    stockMessage.textContent =
        material.value === '' ? 'Seleccione un material.' :
        qty <= 0 || !Number.isInteger(qty) ? 'La cantidad debe ser mayor que cero.' :
        qty > stock ? 'Stock insuficiente. Disponible: ' + stock : '';
}

material.addEventListener('change', validateLoan);
quantity.addEventListener('input', validateLoan);

document.getElementById('loanForm').addEventListener('submit', function(event) {
    validateLoan();

    if (loanButton.disabled) {
        event.preventDefault();
        alert('Verifique el material y la cantidad disponible.');
    }
});

validateLoan();
</script>

<?php require 'footer.php'; ?>
