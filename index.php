<?php
error_reporting(0);
session_start();

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

require __DIR__ . '/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

ini_set('memory_limit', '512M');
set_time_limit(300);

$results = [];
$uniqueResults = [];
$duplicateResults = [];
$resultsErr = [];
$errResults = [];
$headerGlobal = [];

/* =========================
   PROCESS UPLOAD
========================= */
if (isset($_POST['upload'])) {

    if (!empty($_FILES['csv_files']['name'][0])) {

        foreach ($_FILES['csv_files']['tmp_name'] as $key => $tmpName) {

            if ($_FILES['csv_files']['error'][$key] === UPLOAD_ERR_OK) {

                $ext = strtolower(pathinfo($_FILES['csv_files']['name'][$key], PATHINFO_EXTENSION));

                /* =========================
                   HANDLE CSV
                ========================= */
                if ($ext === 'csv') {

                    if (($handle = fopen($tmpName, "r")) !== false) {

                        $header = fgetcsv($handle, 1000, ",");

                        if ($header) {

                            if (empty($headerGlobal)) {
                                $headerGlobal = $header;
                            }

                            $headerLower = array_map('strtolower', $header);
                            $epcIndex = array_search("epc", $headerLower);

                            if ($epcIndex !== false) {

                                while (($data = fgetcsv($handle, 1000, ",")) !== false) {

                                    $epc = trim((string)($data[$epcIndex] ?? ''));

                                    if ($epc !== '' && strpos($epc, "30") === 0) {
                                        $results[] = $data; // FULL ROW
                                    }

                                    if ($epc !== '' && strpos($epc, "E2") === 0) {
                                        $resultsErr[] = $data;
                                    }
                                }
                            }
                        }

                        fclose($handle);
                    }
                }

                /* =========================
                   HANDLE EXCEL
                ========================= */
                elseif (in_array($ext, ['xlsx', 'xls'])) {

                    $spreadsheet = IOFactory::load($tmpName);
                    $sheet = $spreadsheet->getActiveSheet();
                    $rows = $sheet->toArray();

                    if (!empty($rows)) {

                        if (empty($headerGlobal)) {
                            $headerGlobal = $rows[0];
                        }

                        $header = array_map('strtolower', $rows[0]);
                        $epcIndex = array_search("epc", $header);
                        $encodeIndex = array_search("encode success", $header);
                        $decodeIndex = array_search("decode success", $header);


                        if ($epcIndex !== false) {

                            foreach (array_slice($rows, 1) as $row) {

                                $epc = trim((string)($row[$epcIndex] ?? ''));
                                $encodeSuccess = strtolower(trim((string) ($row[$encodeIndex] ?? '')));
                                $decodeSuccess = strtolower(trim((string) ($row[$decodeIndex] ?? '')));
                                

                                if ($epc !== '' && strpos($epc, "3B") === 0 && $encodeSuccess == "true" && $decodeSuccess == "true" ) {
                                    $results[] = $row; // FULL ROW
                                }

                                if ($epc !== '' && strpos($epc, "E2") === 0) {
                                    $resultsErr[] = $row;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // Remove duplicate (FULL ROW)
    $temp = [];
$duplicateResults = [];

foreach ($results as $row) {
    $key = serialize($row);

    if (isset($temp[$key])) {
        $duplicateResults[] = $row; // masuk duplicate
    } else {
        $temp[$key] = true;
    }
}

$uniqueResults = array_map("unserialize", array_keys($temp));
    #$uniqueResults = array_map("unserialize", array_unique(array_map("serialize", $results)));
    $errResults = array_map("unserialize", array_unique(array_map("serialize", $resultsErr)));
    $totalDuplicate = count($duplicateResults);
    
}

/* =========================
   EXPORT (FIXED)
========================= */


if (isset($_POST['export']) && !empty($_POST['epc_data'])) {

    $data = json_decode($_POST['epc_data'], true);
    if (!is_array($data)) die("Invalid data");

    $type = $_POST['export_type'] ?? 'csv';

    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['export_name'] ?? '');
    if (empty($filename)) $filename = "hasil_epc_" . date("dmY");
   $headerGlobal = json_decode($_POST['header_data'], true);
   if (!is_array($headerGlobal)) $headerGlobal = [];
    $headerLower = array_map('strtolower', $headerGlobal);
    $epcIndex = array_search("epc", $headerLower);
    
    /* =========================
       CSV → EPC ONLY
    ========================= */
    if ($type === 'csv') {

        header('Content-Type: text/csv');
        header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");

        $output = fopen('php://output', 'w');
        #fputcsv($output, ['EPC']);

        foreach ($data as $row) {
            $epc = trim((string)($row[$epcIndex] ?? ''));
            fputcsv($output, [$epc]);
        }

        fclose($output);
        exit;
    }

    /* =========================
       EXCEL → FULL ROW
    ========================= */
    else {

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header
        $col = 'A';
        foreach ($headerGlobal as $head) {
            $sheet->setCellValue($col . '1', $head);
            $col++;
        }

        $rowNum = 2;
        foreach ($data as $row) {

            $col = 'A';
            foreach ($row as $cell) {
                $sheet->setCellValue($col . $rowNum, $cell);
                $col++;
            }

            $rowNum++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$filename}.xlsx\"");

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }
}

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>RFID TEAM</title>
<link rel="icon" type="image/x-icon" href="favicon.png">
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>

<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen p-6">

<div class="max-w-4xl mx-auto bg-white shadow-2xl rounded-2xl p-8">

<div class="flex justify-between items-center mb-6">

    <div class="flex items-center gap-2">

        <span class="text-sm text-gray-500">Login sebagai</span>

        <span class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-sm font-semibold shadow-sm">
            <?= $_SESSION['fullName'] ?? 'User' ?>
        </span>

    </div>

    <a href="logout.php"
       onclick="return confirm('Yakin mau logout?')"
       class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm shadow">
       Logout
    </a>

</div>

<h1 class="text-3xl font-bold text-center mb-2 text-gray-800">
EPC CSV & EXCEL
</h1>

<p class="text-center text-gray-500 mb-8">
Upload CSV / Excel → Filter EPC (30 dan 3B) → Export File
</p>

<form method="POST" enctype="multipart/form-data">

<div id="dropArea" class="border-2 border-dashed border-indigo-400 rounded-xl p-10 text-center cursor-pointer hover:bg-indigo-50 transition">
<p class="text-gray-600">Drag & Drop file CSV / Excel</p>
<p class="text-sm text-gray-400 mt-2">atau klik untuk pilih file</p>

<input type="file" name="csv_files[]" multiple accept=".csv,.xlsx,.xls" class="hidden" id="fileInput">
</div>

<button type="submit" name="upload" class="mt-6 w-full bg-indigo-600 text-white py-3 rounded-xl hover:bg-indigo-700 transition font-semibold">
Proses File
</button>

</form>

<?php if (!empty($uniqueResults)) : ?>
<div class="mt-10">

<div class="grid grid-cols-3 gap-4 mb-6">

<div class="bg-indigo-100 p-4 rounded-xl text-center">
<p class="text-sm text-gray-500">Total EPC</p>
<p class="text-2xl font-bold text-indigo-700"><?= count($uniqueResults) ?></p>
</div>

<div class="bg-yellow-100 p-4 rounded-xl text-center">
<p class="text-sm text-gray-500">Duplicate Removed</p>
<p class="text-2xl font-bold text-green-700"><?= count($results) - count($uniqueResults) ?></p>
</div>

<div class="bg-red-100 p-4 rounded-xl text-center">
<p class="text-sm text-gray-500">Total Error EPC</p>
<p class="text-2xl font-bold text-red-700"><?= count($errResults) ?></p>
</div>

</div>

<!-- VALID EPC -->
<div class="max-h-64 overflow-y-auto border rounded-xl mb-6">
<table class="w-full text-sm font-mono">

<thead class="bg-indigo-200 text-indigo-800 sticky top-0">
<tr>
    <th class="p-2 text-left">No</th>
    <th class="p-2 text-left">EPC</th>
    <th class="p-2 text-left">Status</th>
</tr>
</thead>

<tbody class="bg-indigo-50 divide-y">
    <?php if (empty($uniqueResults) === true) : ?>
  <tr class="hover:bg-indigo-100">
    <td class="p-2 text-center" colspan="3">Empty</td>
   </tr>
  <?php else : ?>
  
<?php $no = 1; foreach ($uniqueResults as $row) : ?>

<tr class="hover:bg-indigo-100">
    <td class="p-2"><?= $no++ ?></td>
    <td class="p-2"><?= htmlspecialchars($row[$epcIndex]) ?></td>
    <td class="p-2">        <span class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-sm font-semibold shadow-sm">
<?= "Success" ?></span></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>

</table>
</div>

<!-- DUPLICATE EPC -->
<div class="max-h-64 overflow-y-auto border rounded-xl mb-6">
<table class="w-full text-sm font-mono">

<thead class="bg-yellow-200 text-yellow-800 sticky top-0">
<tr>
    <th class="p-2 text-left">No</th>
    <th class="p-2 text-left">Duplicate EPC</th>
    <th class="p-2 text-left">Status</th>
</tr>
</thead>

<tbody class="bg-yellow-50 divide-y">
  <?php if (empty($duplicateResults) === true) : ?>
  <tr class="hover:bg-yellow-100">
    <td class="p-2 text-center" colspan="3">Empty</td>
   </tr>
  <?php else : ?>

<?php $no = 1; foreach ($duplicateResults as $row) : ?>

<tr class="hover:bg-yellow-100">
    <td class="p-2"><?= $no++ ?></td>
    <td class="p-2"><?= htmlspecialchars($row[$epcIndex]) ?></td>
    <td class="p-2">        <span class="bg-yellow-100 text-indigo-700 px-3 py-1 rounded-full text-sm font-semibold shadow-sm">
<?= "Deleted" ?></span></td>
</tr>

<?php endforeach; ?>
<?php endif; ?>
</tbody>

</table>
</div>

<!-- ERROR EPC -->
<div class="max-h-64 overflow-y-auto border rounded-xl mb-6">
<table class="w-full text-sm font-mono">

<thead class="bg-red-200 text-red-800 sticky top-0">
<tr>
    <th class="p-2 text-left">No</th>
    <th class="p-2 text-left">Error EPC</th>
    <th class="p-2 text-left">Status</th>
</tr>
</thead>

<tbody class="bg-red-50 divide-y">
    <?php if (empty($errResults) === true) : ?>
  <tr class="hover:bg-red-100">
    <td class="p-2 text-center" colspan="3">Empty</td>
   </tr>
  <?php else : ?>
<?php $no = 1; foreach ($errResults as $row) : ?>
<tr class="hover:bg-red-100">
    <td class="p-2"><?= $no++ ?></td>
    <td class="p-2"><?= htmlspecialchars($row[$epcIndex]) ?></td>
    <td class="p-2">        <span class="bg-red-100 text-indigo-700 px-3 py-1 rounded-full text-sm font-semibold shadow-sm">
<?= "Deleted" ?></span></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>

</table>
</div>

<form method="POST">
<input type="hidden" name="epc_data" value='<?= htmlspecialchars(json_encode($uniqueResults), ENT_QUOTES, 'UTF-8') ?>'>
<input type="hidden" name="header_data" 
value='<?= htmlspecialchars(json_encode($headerGlobal), ENT_QUOTES, "UTF-8") ?>'>
<label class="block text-sm font-medium text-gray-700 mb-2">Format Export</label>

<p class="block text-sm font-light text-yellow-600 mb-2">Note : </br>
Export *.Csv hanya akan menyimpan data EPC ber status Success Saja. </br>
Export *.Xlsx akan menyimpan Semua Data Sebelumnya dan Mengambil EPC ber Status Success.</p>
<select name="export_type" class="w-full border rounded-lg p-2 mb-4">
    <option value="xlsx">Excel (.xlsx)</option>
    <option value="csv">CSV (.csv)</option>
</select>
<input type="text" name="export_name" value="ID000" class="w-full border rounded-lg p-2 mb-4">

<button type="submit" name="export" class="w-full bg-green-600 text-white py-3 rounded-xl hover:bg-green-700">
Export File
</button>

</form>

</div>
<?php endif; ?>
<div class="flex justify-center mt-6 gap-2 flex-wrap">

    <span class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-xs font-semibold">
        🚀 Version App <?= $_SESSION["versiApp"] ?>.
    </span>
</div>
</div>

<footer class="text-center text-sm text-gray-400 mt-10">
© 2026 Miftakhul Kirom. All rights reserved.
</footer>
<script>
const dropArea = document.getElementById('dropArea');
const fileInput = document.getElementById('fileInput');

dropArea.addEventListener('click', () => fileInput.click());

dropArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropArea.classList.add('bg-indigo-100');
});

dropArea.addEventListener('dragleave', () => {
    dropArea.classList.remove('bg-indigo-100');
});

dropArea.addEventListener('drop', (e) => {
    e.preventDefault();
    dropArea.classList.remove('bg-indigo-100');
    fileInput.files = e.dataTransfer.files;
});
</script>

</body>
</html>