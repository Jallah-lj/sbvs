<?php
/**
 * course_api.php — Course CRUD API
 * Supports multi-currency, schema migration, stats.
 */
session_start();
ob_start();
header('Content-Type: application/json; charset=utf-8');

set_error_handler(function($no,$str,$file,$line){
    ob_clean(); echo json_encode(['success'=>false,'message'=>"PHP [{$no}]: {$str} in ".basename($file).":".$line]); exit;
});
set_exception_handler(function(Throwable $e){
    ob_clean(); http_response_code(500);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]); exit;
});

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit;
}

function findRootFile(string $f): string {
    $dir = __DIR__;
    for ($i=0;$i<8;$i++) {
        if (file_exists($dir.DIRECTORY_SEPARATOR.$f)) return $dir.DIRECTORY_SEPARATOR.$f;
        $p = dirname($dir); if ($p===$dir) break; $dir=$p;
    }
    throw new RuntimeException("Cannot locate {$f}");
}
require_once findRootFile('config.php');
require_once findRootFile('database.php');

// Course.php lives in models/, not the root — search specifically
$_coursePath = null;
$_candidates = [
    __DIR__ . '/../models/Course.php',
    __DIR__ . '/../../models/Course.php',
    dirname(findRootFile('config.php')) . '/config/controllers/models/Course.php',
    dirname(findRootFile('config.php')) . '/controllers/models/Course.php',
];
foreach ($_candidates as $_c) {
    if (file_exists($_c)) { $_coursePath = $_c; break; }
}
if (!$_coursePath) {
    // Walk up looking for models/Course.php
    $dir = __DIR__;
    for ($i = 0; $i < 6; $i++) {
        if (file_exists($dir . '/models/Course.php')) { $_coursePath = $dir . '/models/Course.php'; break; }
        $dir = dirname($dir);
    }
}
if (!$_coursePath) fail('Course model (Course.php) not found. Place it in config/controllers/models/', 500);
require_once $_coursePath;

$db   = (new Database())->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$role   = $_SESSION['role'] ?? '';
$branchId = (int)($_SESSION['branch_id'] ?? 0);
$isSA   = ($role === 'Super Admin');
$isBA   = ($role === 'Branch Admin');
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if (!in_array($role,['Super Admin','Branch Admin','Admin'])) {
    http_response_code(403); echo json_encode(['success'=>false,'message'=>'Forbidden']); exit;
}

$model = new Course($db);

function ok(array $p=[]): void { ob_clean(); echo json_encode(array_merge(['success'=>true,'status'=>'success'],$p)); exit; }
function fail(string $m, int $c=400): void { ob_clean(); http_response_code($c); echo json_encode(['success'=>false,'status'=>'error','message'=>$m]); exit; }

// ── LIST ──────────────────────────────────────────────────────
if ($action === 'list') {
    $bid    = $isSA ? ((int)($_GET['branch_id']??0)||null) : $branchId;
    $status = $_GET['status'] ?? 'Active';
    $data   = $model->getAll($bid, $status);

    // Client-side currency filter (cheap, data is small)
    if (!empty($_GET['currency'])) {
        $fc = strtoupper($_GET['currency']);
        $data = array_values(array_filter($data, fn($c) => strtoupper($c['currency_code']??'USD') === $fc));
    }
    if (!empty($_GET['search'])) {
        $q = strtolower($_GET['search']);
        $data = array_values(array_filter($data, fn($c) =>
            str_contains(strtolower($c['name']??''), $q) ||
            str_contains(strtolower($c['code']??''), $q)));
    }
    ok(['data' => $data]);
}

// ── GET SINGLE ────────────────────────────────────────────────
if ($action === 'get') {
    $id   = (int)($_GET['id'] ?? 0);
    if (!$id) fail('ID required.');
    $row  = $model->getById($id);
    if (!$row) fail('Course not found.', 404);
    ok(['data' => $row]);
}

// ── STATS ─────────────────────────────────────────────────────
if ($action === 'stats') {
    $bid  = $isSA ? ((int)($_GET['branch_id']??0)||null) : $branchId;
    ok(['data' => $model->getStats($bid)]);
}

// ── CREATE ────────────────────────────────────────────────────
if ($action === 'create' && $_SERVER['REQUEST_METHOD']==='POST') {
    if (!$isSA && !$isBA) fail('Forbidden.',403);
    if (empty($_POST['name']))      fail('Course name is required.');
    if (empty($_POST['duration']))  fail('Duration is required.');

    $data = $_POST;
    if (!$isSA) $data['branch_id'] = $branchId;
    if (empty($data['branch_id'])) fail('Branch is required.');

    $id = $model->create($data);
    $id ? ok(['message'=>'Course created.','id'=>$id]) : fail('Could not create course.');
}

// ── UPDATE ────────────────────────────────────────────────────
if ($action === 'update' && $_SERVER['REQUEST_METHOD']==='POST') {
    if (!$isSA && !$isBA) fail('Forbidden.',403);
    $id = (int)($_POST['id']??0);
    if (!$id) fail('ID required.');
    $data = $_POST;
    if (!$isSA) $data['branch_id'] = $branchId;
    $model->update($id,$data) ? ok(['message'=>'Course updated.']) : fail('Update failed.');
}

// ── ARCHIVE (soft delete) ─────────────────────────────────────
if ($action === 'archive' && $_SERVER['REQUEST_METHOD']==='POST') {
    if (!$isSA && !$isBA) fail('Forbidden.',403);
    $id = (int)($_POST['id']??0);
    if (!$id) fail('ID required.');
    $result = $model->delete($id, false);
    if ($result === 'has_enrollments') fail('Course has active enrollments and cannot be archived.');
    $result ? ok(['message'=>'Course archived.']) : fail('Archive failed.');
}

// ── RESTORE ───────────────────────────────────────────────────
if ($action === 'restore' && $_SERVER['REQUEST_METHOD']==='POST') {
    if (!$isSA && !$isBA) fail('Forbidden.',403);
    $id = (int)($_POST['id']??0);
    $model->restore($id) ? ok(['message'=>'Course restored.']) : fail('Restore failed.');
}

// ── DELETE (hard, SA only) ────────────────────────────────────
if ($action === 'delete' && $_SERVER['REQUEST_METHOD']==='POST') {
    if (!$isSA) fail('Super Admin only.',403);
    $id = (int)($_POST['id']??0);
    $result = $model->delete($id, true);
    if ($result === 'has_enrollments') fail('Cannot delete: active enrollments exist. Archive instead.');
    $result ? ok(['message'=>'Course permanently deleted.']) : fail('Delete failed.');
}

fail('Unknown action: '.htmlspecialchars($action));