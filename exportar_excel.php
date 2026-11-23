<?php
require_once 'config.php';
require_admin();

/**
 * Exporta un libro Excel XML 2003 (.xls) con dos hojas:
 * Existencias e Historial de préstamos.
 * No requiere librerías externas ni cambios en la base de datos.
 */
function excelXml($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function excelCell($value, bool $numeric = false): string
{
    if ($numeric && is_numeric($value)) {
        return '<Cell><Data ss:Type="Number">' . excelXml($value) . '</Data></Cell>';
    }

    return '<Cell><Data ss:Type="String">' . excelXml($value) . '</Data></Cell>';
}

function excelRow(array $values, array $numericColumns = []): string
{
    $xml = '<Row>';
    foreach (array_values($values) as $index => $value) {
        $xml .= excelCell($value, in_array($index, $numericColumns, true));
    }
    return $xml . '</Row>';
}

$categoria = $_GET['categoria'] ?? '';
$estado = $_GET['estado'] ?? '';
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';

if ($categoria !== '' && !ctype_digit((string)$categoria)) {
    $categoria = '';
}
if (!in_array($estado, ['Bueno', 'Regular', 'Dañado/Baja'], true)) {
    $estado = '';
}
if ($desde !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
    $desde = '';
}
if ($hasta !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    $hasta = '';
}
if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
    http_response_code(400);
    exit('La fecha inicial no puede ser posterior a la fecha final.');
}

/* Hoja 1: existencias actuales. */
$stockConditions = [];
$stockParams = [];

if ($categoria !== '') {
    $stockConditions[] = 'm.category_id = ?';
    $stockParams[] = (int)$categoria;
}
if ($estado !== '') {
    $stockConditions[] = 'm.condition_status = ?';
    $stockParams[] = $estado;
}

$stockWhere = $stockConditions ? ' WHERE ' . implode(' AND ', $stockConditions) : '';

$stockSql = "SELECT m.code, m.name, c.name AS category,
                    m.brand, m.total_qty, m.available_qty,
                    m.condition_status
             FROM materials m
             LEFT JOIN categories c ON c.id = m.category_id
             $stockWhere
             ORDER BY m.name";

$stmt = $pdo->prepare($stockSql);
$stmt->execute($stockParams);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Hoja 2: historial, con los mismos filtros aplicables. */
$loanConditions = [];
$loanParams = [];

if ($desde !== '') {
    $loanConditions[] = 'l.loan_date >= ?';
    $loanParams[] = $desde . ' 00:00:00';
}
if ($hasta !== '') {
    $loanConditions[] = 'l.loan_date < DATE_ADD(?, INTERVAL 1 DAY)';
    $loanParams[] = $hasta;
}
if ($categoria !== '') {
    $loanConditions[] = 'm.category_id = ?';
    $loanParams[] = (int)$categoria;
}
if ($estado !== '') {
    $loanConditions[] = 'm.condition_status = ?';
    $loanParams[] = $estado;
}

$loanWhere = $loanConditions ? ' WHERE ' . implode(' AND ', $loanConditions) : '';

$loanSql = "SELECT u.full_name, m.name, l.quantity,
                   l.loan_date, l.returned_at, l.status
            FROM loans l
            JOIN users u ON u.id = l.teacher_id
            JOIN materials m ON m.id = l.material_id
            $loanWhere
            ORDER BY l.loan_date DESC";

$stmt = $pdo->prepare($loanSql);
$stmt->execute($loanParams);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

$workbook = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$workbook .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
$workbook .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">';

$workbook .= '<Styles>
 <Style ss:ID="Header"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#174F79" ss:Pattern="Solid"/></Style>
 <Style ss:ID="Title"><Font ss:Bold="1" ss:Size="14" ss:Color="#173F5F"/></Style>
</Styles>';

/* Hoja de existencias */
$workbook .= '<Worksheet ss:Name="Existencias"><Table>';
$workbook .= '<Row><Cell ss:StyleID="Title" ss:MergeAcross="6"><Data ss:Type="String">SIGED-KENNEDY - EXISTENCIAS</Data></Cell></Row>';
$workbook .= '<Row><Cell><Data ss:Type="String">Generado: ' . excelXml(date('d/m/Y H:i')) . '</Data></Cell></Row>';
$workbook .= '<Row ss:StyleID="Header">';
foreach (['Código','Material','Categoría','Marca','Cantidad total','Disponible','Estado físico'] as $heading) {
    $workbook .= '<Cell><Data ss:Type="String">' . excelXml($heading) . '</Data></Cell>';
}
$workbook .= '</Row>';

foreach ($materials as $m) {
    $workbook .= excelRow([
        $m['code'], $m['name'], $m['category'], $m['brand'],
        $m['total_qty'], $m['available_qty'], $m['condition_status']
    ], [4, 5]);
}
if (!$materials) {
    $workbook .= excelRow(['No existen materiales para los filtros seleccionados.']);
}
$workbook .= '</Table><WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><FreezePanes/><FrozenNoSplit/><SplitHorizontal>3</SplitHorizontal><TopRowBottomPane>3</TopRowBottomPane></WorksheetOptions></Worksheet>';

/* Hoja de historial */
$workbook .= '<Worksheet ss:Name="Historial"><Table>';
$workbook .= '<Row><Cell ss:StyleID="Title" ss:MergeAcross="5"><Data ss:Type="String">SIGED-KENNEDY - HISTORIAL DE PRÉSTAMOS</Data></Cell></Row>';
$workbook .= '<Row><Cell><Data ss:Type="String">Desde: ' . excelXml($desde ?: 'Todas') . ' | Hasta: ' . excelXml($hasta ?: 'Todas') . '</Data></Cell></Row>';
$workbook .= '<Row ss:StyleID="Header">';
foreach (['Docente','Material','Cantidad','Fecha de préstamo','Fecha de devolución','Estado'] as $heading) {
    $workbook .= '<Cell><Data ss:Type="String">' . excelXml($heading) . '</Data></Cell>';
}
$workbook .= '</Row>';

foreach ($history as $r) {
    $workbook .= excelRow([
        $r['full_name'], $r['name'], $r['quantity'],
        $r['loan_date'], $r['returned_at'], $r['status']
    ], [2]);
}
if (!$history) {
    $workbook .= excelRow(['No existen préstamos para los filtros seleccionados.']);
}
$workbook .= '</Table><WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><FreezePanes/><FrozenNoSplit/><SplitHorizontal>3</SplitHorizontal><TopRowBottomPane>3</TopRowBottomPane></WorksheetOptions></Worksheet>';

$workbook .= '</Workbook>';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="reporte_SIGED_KENNEDY.xls"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
echo $workbook;
exit;
