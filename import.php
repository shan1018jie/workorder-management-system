<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>

<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Shared\Date;

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();



function normalizeKey($string) {
    return strtoupper(trim($string));
}

function formatExcelDate($value) {
    if (empty($value)) return null;

    if (is_numeric($value)) {
        try {
            $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
            return $dt->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    } else {
        $timestamp = strtotime($value);
        if ($timestamp === false) return null;
        return date('Y-m-d', $timestamp);
    }
}

function formatDateTime($value) {
    if (empty($value)) return '';
    $timestamp = strtotime($value);
    return $timestamp !== false ? date('Y-m-d', $timestamp) : $value;
}

$host = "localhost";
$port = 3307;
$dbname = "database";
$user = "root";
$pass = "";

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB connection failed: " . $e->getMessage());
}


// Your master category & subcategory list:
$masterList = [
    'D01. PDA RELATED/CYCLE COUNT' => [
        'priority' => 'P2',
        'subs' => [
            'D01.01 CHARGER ROSAK',
            'D01.02 TAK BOLEH ON',
            'D01.03 WIFI TAK CONNECT',
            'D01.04 BATTERY LEMAH/TAK CHARGE',
            'D01.05 PDA SCANNER ROSAK',
            'D01.06 SCREEN PECAH',
            'D01.07 TAK BOLEH LOGIN',
            'D01.08 CYCLE COUNT NO DATA',
            'D01.09 CYCLE COUNT PAGE ERROR',
        ],
    ],
    'D02. CREDIT CARD MACHINE' => [
        'priority' => 'P1',
        'subs' => [
            'D02.01 TAK BOLEH ON',
            'D02.02 NO LINK',
            'D02.03 SETTLEMENT ISSUE',
            'D02.04 PROFILE EOK ERROR',
            'D02.05 PROFILE ROK ERROR',
            'D02.06 COVER ROSAK',
            'D02.07 PAYWAVE TAK BOLEH',
            'D02.08 SCREEN ROSAK',
            'D02.09 ALERT IRRUPTION'
        ],
    ],
    'D03. TNG READER' => [
        'priority' => 'P1',
        'subs' => [
            'D03.01 TAK BOLEH ON',
            'D03.02 CABLE ROSAK',
            'D03.03 MACHINE RESTART SENDIRI',
            'D03.04 MACHINE PECAH/ROSAK',
            'D03.05 TNG INVALID MAC',
            'D03.05 TNG COMMUNICATION ERROR',
        ],
    ],
    'D04. PC / POS RELATED (M1/M2)' => [
        'priority' => 'P1',
        'subs' => [
            'D04.01 POS TAK BOLEH LOGIN',
            'D04.02 PC TAK BOLEH ON',
            'D04.03 WINDOWS CORRUPTED',
            'D04.04 POS SCAN ITEM SLOW',
            'D04.05 ITEM NOT FOUND',
            'D04.06 ITEM NO PROMOTION',
            'D04.07 ITEM HARGA SALAH',
            'D04.08 POS TUNJUK ERROR PAYMENT',
            'D04.09 KENA VIRUS RANSOMWARE',
            'D04.10 EPAY ERROR INVALID MAC',
            'D04.11 DAH BAYAR RECEIPT TAK KELUAR',
        ],
    ],
    'D05. TIDAK LINK (M1/M2)' => [
        'priority' => 'P1',
        'subs' => [
            'D05.01 SALES TAK LINK',
            'D05.02 PROMOTION M1/M2 TAK SAMA',
        ],
    ],
    'D06. MASALAH INTERNET' => [
        'priority' => 'P1',
        'subs' => [
            'D06.01 PC M1/M2 NO INTERNET',
            'D06.02 INTERNET SLOW',
        ],
    ],
    'D07. DRAWER MASALAH' => [
        'priority' => 'P1',
        'subs' => [
            'D07.01 DRAWER ROSAK',
            'D07.02 DRAWER TAK BOLEH BUKA/JAM',
            'D07.03 COIN BOX MSSING',
            'D07.04 ROLLER ISSUE',
        ],
    ],
    'D08. UPS / BACKUP BATTERY ROSAK' => [
        'priority' => 'P2',
        'subs' => [
            'D08.01 UPS NO POWER / OUTPUT',
            'D08.02 UPS NOT CHARGING',
            'D08.03 UPS NO LIGHT',
            'D08.04 UPS LAMPU MERAH/BUNYI',
        ],
    ],
    'D09. CUSTOMER DISPLAY MASALAH' => [
        'priority' => 'P2',
        'subs' => [
            'D09.01 NO DISPLAY',
            'D09.02 SCREEN TUNJUK TAK BETUL',
            'D09.03 CABLE LONGGAR',
            'D09.04 NO POWER',
            'D09.05 SCREEN PECAH',
        ],
    ],
    'D10. BARCODE PRINTER ISSUE' => [
        'priority' => 'P2',
        'subs' => [
            'D10.01 BARCODE PRINT TAK KELUAR',
            'D10.02 BARCODE LAMPU MERAH',
            'D10.03 BARCODE TUNJUK 2 LAMPU HIJAU',
            'D10.04 NO POWER',
            'D10.05 BARCODE PRINT OUT SELANG SELANG',
        ],
    ],
    'D11. RECEIPT PRINTER MASALAH' => [
        'priority' => 'P1',
        'subs' => [
            'D11.01 POS TUNJUK PRINTER MASALAH',
            'D11.02 RECEIPT PRINTER LAMPU MERAH',
            'D11.03 RECEIPT PRINTER CUTTER ROSAK',
            'D11.04 RECEIPT PRINTER NO POWER',
            'D11.05 POS TUNJUK PRINTER ERROR',
            'D11.06 RECEIPT PRINTER DRIVER',
        ],
    ],
    'D12. KKMIS BACKEND' => [
        'priority' => 'P2',
        'subs' => [
            'D12.01 TAK DAPAT LOGIN',
            'D12.02 CANNOT CREATE ID',
            'D12.03 KKDC INVOICE TAK ADA',
            'D12.04 KKDC GRN TAK ADA',
            'D12.05 SUPPLIER INVOICE TAK ADA',
            'D12.06 SUPPLIER PO TAK ADA',
            'D12.07 GRTN TAK ADA DALAM SYSTEM',
            'D12.08 PROPOSE ORDER SYSTEM',
        ],
    ],
    'D13. GRN PRINTER MASALAH' => [
        'priority' => 'P2',
        'subs' => [
            'D13.01 GRN PRINTER TAK BOLEH PRINT',
            'D13.02 INSTALL GRN PRINTER DRIVER',
            'D13.03 CATRIDGE ERR',
            'D13.04 PRINT TAK CANTIK'
        ],
    ],
    'D14. SCANNER ROSAK' => [
        'priority' => 'P2',
        'subs' => [
            'D14.01 SCANNER TAK DAPAT SCAN',
            'D14.02 SCANNER USB CABLE PUTUS',
        ],
    ],
    'D15. MOUSE / KEYBOARD ROSAK' => [
        'priority' => 'P2',
        'subs' => [
            'D15.01 MOUSE ROSAK',
            'D15.02 KEYBOARD ROSAK',
        ],
    ],
    'D16. OUTLET CABLE TAK KEMAS' => [
        'priority' => 'P2',
        'subs' => [
            'D16.01 BAWAH COUNTER M1',
            'D16.02 BAWAH COUNTER M2',
            'D16.03 ATAS RACK ROKOK',
        ],
    ],
    'D17. MYKASIH DEVICE' => [
        'priority' => 'P1',
        'subs' => [
            'D17.01 SETTLEMENT ISSUE'
        ],
    ],
    'D99. UNCATEGORIZED' => [
        'priority' => '-',
        'subs' => [],
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['excelFile']) && $_FILES['excelFile']['error'] === UPLOAD_ERR_OK) {
        $uploadedFilePath = $_FILES['excelFile']['tmp_name'];
        $spreadsheet = IOFactory::load($uploadedFilePath);
        $sheet = $spreadsheet->getSheetByName('workOrderDataTable');

        if (!$sheet) die("❌ Sheet 'workOrderDataTable' not found in uploaded Excel file.");

        $data = $sheet->toArray(null, true, true, true);

        // Prepare SQL
        $stmtInsert = $pdo->prepare("INSERT INTO work_orders (ref_no, status, work_order, category, sub_category, created_date, completed_date, completion_duration, requestor, assignee, occupant, work_description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM work_orders WHERE ref_no = ?");

        foreach ($data as $i => $row) {
    if ($i < 5 || empty(array_filter($row))) continue;

    $ref_no = trim($row['A'] ?? '');
    $status = trim($row['B'] ?? '');
    $work_order = trim($row['E'] ?? '');
    $category = trim($row['I'] ?? '');
    $sub_category = trim($row['J'] ?? '');
    $created_date = formatExcelDate($row['M'] ?? '');
    $completed_date = formatExcelDate($row['U'] ?? '');
    $completion_duration = trim($row['V'] ?? '');
    $requestor = trim($row['AB'] ?? '');
    $assignee = trim($row['AD'] ?? '');
    $occupant = trim($row['AZ'] ?? '');
    $work_description = trim($row['BH'] ?? '');

    if ($ref_no === '' || $category === '') continue;

    // Delete any existing row with the same ref_no
    $stmtDelete = $pdo->prepare("DELETE FROM work_orders WHERE ref_no = ?");
    $stmtDelete->execute([$ref_no]);

    // Insert fresh row
    $stmtInsert = $pdo->prepare("INSERT INTO work_orders (
        ref_no, status, work_order, category, sub_category,
        created_date, completed_date, completion_duration,
        requestor, assignee, occupant, work_description
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmtInsert->execute([
        $ref_no, $status, $work_order, $category, $sub_category,
        $created_date, $completed_date, $completion_duration,
        $requestor, $assignee, $occupant, $work_description
    ]);

}

        // Determine target month & year
        $monthCounts = [];
        $yearCounts = [];
        foreach ($data as $i => $row) {
            if ($i < 2) continue;
            $created_date = formatExcelDate($row['M'] ?? '');
            if (!$created_date) continue;

            $timestamp = strtotime($created_date);
            $month = date('m', $timestamp);
            $year = date('Y', $timestamp);

            $monthCounts[$month] = ($monthCounts[$month] ?? 0) + 1;
            $yearCounts[$year] = ($yearCounts[$year] ?? 0) + 1;
        }

        if (empty($monthCounts) || empty($yearCounts)) die("❌ No valid created_date found in uploaded file.");

        arsort($monthCounts);
        arsort($yearCounts);
        $targetMonth = key($monthCounts);
        $targetYear = key($yearCounts);

        // FETCH AGGREGATED DATA
       $sqlAgg = "
            SELECT 
                category,
                sub_category,
                COUNT(*) AS reported,
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS closed,
                SUM(CASE WHEN status IN ('Open', 'In Progress','On Hold') THEN 1 ELSE 0 END) AS pending
            FROM work_orders
            WHERE MONTH(created_date) = :month AND YEAR(created_date) = :year
            GROUP BY category, sub_category
        ";

        $stmt = $pdo->prepare($sqlAgg);
        $stmt->execute([':month' => $targetMonth, ':year' => $targetYear]);
        $aggData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $aggLookup = [];
        foreach ($aggData as $row) {
            $catKey = normalizeKey($row['category']);
            $subKey = normalizeKey($row['sub_category']);
            $aggLookup[$catKey][$subKey] = [
                'reported' => (int)$row['reported'],
                'closed' => (int)$row['closed'],
                'pending' => (int)$row['pending'],
            ];
        }

        // FETCH DETAILED DATA
        $stmt = $pdo->prepare("
            SELECT ref_no, status, work_order, category, sub_category, created_date, completed_date, completion_duration, requestor, assignee, occupant, work_description
            FROM work_orders
            WHERE MONTH(created_date) = :month AND YEAR(created_date) = :year
            ORDER BY created_date ASC
        ");
        $stmt->execute([':month' => $targetMonth, ':year' => $targetYear]);
        $detailsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $spreadsheet = new Spreadsheet();

        // Sheet 1 - Detailed Data
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('workOrderDataTable');
        $headers1 = ['Ref#', 'Status', 'Work Order', 'Category', 'Sub Category', 'Created Date', 'Completed Date', 'Completion Duration', 'Requestor', 'Assignee', 'Occupant', 'Work Description'];
        $sheet1->fromArray($headers1, NULL, 'A1');

        // Apply your original styling here exactly
        $lastCol1 = Coordinate::stringFromColumnIndex(count($headers1));
        $sheet1->getStyle("A1:{$lastCol1}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'name' => 'Helvetica'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF66CCFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet1->getRowDimension(1)->setRowHeight(64);
        $sheet1->setAutoFilter("A1:{$lastCol1}1");

       $rowNum = 2;
        foreach ($detailsData as $row) {
            $sheet1->fromArray([
                $row['ref_no'], $row['status'], $row['work_order'], $row['category'], $row['sub_category'],
                formatDateTime($row['created_date']), formatDateTime($row['completed_date']), $row['completion_duration'], $row['requestor'],
                $row['assignee'], $row['occupant'], $row['work_description']
            ], NULL, "A$rowNum");
            $rowNum++;
        }
        
        for ($col = 1; $col <= count($headers1); $col++) {
            $sheet1->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
        }
    }

// Sheet 2 - Summary
        $summarySheet = $spreadsheet->createSheet();
        $summarySheet->setTitle('Summary');
        $headers2 = ['Priority', 'Category', 'Reported', 'Closed', 'Pending'];
        $summarySheet->fromArray($headers2, NULL, 'A3');

        $summarySheet->mergeCells('A1:A3');
        $summarySheet->setCellValue('A1', 'Priority');
        $summarySheet->getStyle('A4:A121')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $summarySheet->getStyle('A4:A121')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);



    // Merge Problem Description (Category) header from B1 to B3
    $summarySheet->mergeCells('B1:B3');
    $summarySheet->setCellValue('B1', 'Problem Description');

                // Set custom header section (top rows of Summary sheet)
        $monthYearFormatted = date("F-Y", mktime(0, 0, 0, $targetMonth, 1, $targetYear));
        $totalReported = array_sum(array_column($aggData, 'reported'));
        $totalClosed = array_sum(array_column($aggData, 'closed'));
        $totalPending = array_sum(array_column($aggData, 'pending'));
        $closedPercent = $totalReported > 0 ? round(($totalClosed / $totalReported) * 100, 1) : 0;


        // Header row 1: Month-Year
        $summarySheet->mergeCells("C1:E1");
        $summarySheet->setCellValue("C1", $monthYearFormatted);
        $summarySheet->getStyle("C1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 16],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Header row 2: Reported, Closed %, Pending
        $summarySheet->setCellValue("C2", $totalReported);
        $summarySheet->setCellValue("D2", $closedPercent . "%");
        $summarySheet->setCellValue("E2", $totalPending);

        // Colors: Reported = default, Closed = green, Pending = red
        $summarySheet->getStyle("D2")->getFont()->getColor()->setARGB(Color::COLOR_DARKGREEN);
        $summarySheet->getStyle("E2")->getFont()->getColor()->setARGB(Color::COLOR_RED);

        // Labels row below values
        $summarySheet->setCellValue("C3", "REPORTED");
        $summarySheet->setCellValue("D3", "CLOSED");
        $summarySheet->setCellValue("E3", "PENDING");

        $summarySheet->getStyle("C2:E2")->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $summarySheet->getStyle("C3:E3")->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $summarySheet->getStyle('A1:E3')->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]);

        $summarySheet->getStyle('A4:E122')->applyFromArray([
            'borders' => [
                'top' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
                'bottom' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
                'left' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
                'right' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ]
        ]);

         $summarySheet->getStyle('A4:A122')->applyFromArray([
            'borders' => [
                'right' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],

            ]
            ]);

            $summarySheet->getStyle('B4:B122')->applyFromArray([
            'borders' => [
                'right' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],

            ]
            ]);

            $summarySheet->getStyle('C4:C122')->applyFromArray([
            'borders' => [
                'right' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],

            ]
            ]);

            $summarySheet->getStyle('D4:D122')->applyFromArray([
            'borders' => [
                'right' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],

            ]
            ]);

           

        $lastCol2 = Coordinate::stringFromColumnIndex(count($headers2));
        $summarySheet->getStyle("A1:{$lastCol2}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF66CCFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $summarySheet->setAutoFilter("A3:B3");
        
$rowNum2 = 4;

foreach ($masterList as $category => $data) {
    $priority = $data['priority'] ?? '';
    $subs = $data['subs'] ?? [];

    // Calculate totals for category
    $totalReported = 0;
    $totalClosed = 0;
    $totalPending = 0;

    $catKey = normalizeKey($category);

    foreach ($subs as $subcat) {
        $subKey = normalizeKey($subcat);

        // Include subcategory counts
        $totalReported += $aggLookup[$catKey][$subKey]['reported'] ?? 0;
        $totalClosed   += $aggLookup[$catKey][$subKey]['closed'] ?? 0;
        $totalPending  += $aggLookup[$catKey][$subKey]['pending'] ?? 0;
    }

    // ✅ Also check for blank or '-' subcategories under this category
    foreach (['', '-'] as $emptySub) {
        $subKey = normalizeKey($emptySub);
        $totalReported += $aggLookup[$catKey][$subKey]['reported'] ?? 0;
        $totalClosed   += $aggLookup[$catKey][$subKey]['closed'] ?? 0;
        $totalPending  += $aggLookup[$catKey][$subKey]['pending'] ?? 0;
    }

    // Category row - bold font
    $summarySheet->setCellValue("A$rowNum2", $priority);
    $summarySheet->setCellValue("B$rowNum2", $category);
    $summarySheet->setCellValue("C$rowNum2", $totalReported);
    $summarySheet->setCellValue("D$rowNum2", $totalClosed);
    $summarySheet->setCellValue("E$rowNum2", $totalPending);

    $summarySheet->getStyle("A$rowNum2:E$rowNum2")->getFont()->setBold(true);
    $rowNum2++;

    foreach ($subs as $subcat) {
    $subKey = normalizeKey($subcat);
    $values = $aggLookup[$catKey][$subKey] ?? ['reported' => 0, 'closed' => 0, 'pending' => 0];

    $displaySubcat = ucwords(strtolower($subcat));
    $summarySheet->setCellValue("B$rowNum2", $displaySubcat);
    $summarySheet->getStyle("B$rowNum2")->getAlignment()->setIndent(1);
    $summarySheet->getStyle("B$rowNum2")->getFont()->setItalic(true)->getColor()->setARGB('FF808080');

    $summarySheet->setCellValue("C$rowNum2", $values['reported']);
    $summarySheet->setCellValue("D$rowNum2", $values['closed']);
    $summarySheet->setCellValue("E$rowNum2", $values['pending']);

    $summarySheet->getRowDimension($rowNum2)->setOutlineLevel(1)->setVisible(true)->setCollapsed(true);
    $rowNum2++;
}

// ✅ ADD: Handle no-subcategory rows ('' or '-') manually
$foundEmptySub = false;
foreach (['', '-'] as $emptySub) {
    $subKey = normalizeKey($emptySub);
    if (isset($aggLookup[$catKey][$subKey])) {
        $values = $aggLookup[$catKey][$subKey];
        
        $summarySheet->setCellValue("B$rowNum2", 'No Subcategories');
        $summarySheet->getStyle("B$rowNum2")->getAlignment()->setIndent(1);
        $summarySheet->getStyle("B$rowNum2")->getFont()->setItalic(true)->getColor()->setARGB('FF808080');

        $summarySheet->setCellValue("C$rowNum2", $values['reported']);
        $summarySheet->setCellValue("D$rowNum2", $values['closed']);
        $summarySheet->setCellValue("E$rowNum2", $values['pending']);

        $summarySheet->getRowDimension($rowNum2)->setOutlineLevel(1)->setVisible(true)->setCollapsed(true);
        $rowNum2++;
        
        $foundEmptySub = true;
        break;
    }
}

// If not found, still print row with zeros
if (!$foundEmptySub) {
    $summarySheet->setCellValue("B$rowNum2", 'No Subcategories');
    $summarySheet->getStyle("B$rowNum2")->getAlignment()->setIndent(1);
    $summarySheet->getStyle("B$rowNum2")->getFont()->setItalic(true)->getColor()->setARGB('FF808080');

    $summarySheet->setCellValue("C$rowNum2", 0);
    $summarySheet->setCellValue("D$rowNum2", 0);
    $summarySheet->setCellValue("E$rowNum2", 0);

    $summarySheet->getRowDimension($rowNum2)->setOutlineLevel(1)->setVisible(true)->setCollapsed(true);
    $rowNum2++;
}
}

$summarySheet->setShowSummaryBelow(true);


// Autosize columns
foreach (range('A', $lastCol2) as $col) {
    $summarySheet->getColumnDimension($col)->setAutoSize(true);
}

         // Save file with timestamp
        $fileName = 'work_orders_export_' . time() . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($fileName);

echo <<<HTML
<div style="
    height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 30px;
    background-color: white;
    font-family: 'Arial Black', Arial, sans-serif;
    user-select: none;
">

  <!-- Logo -->
  <img src="KK_LOGO.png" alt="KK Logo" style="height: 100px;" />

 

  <!-- Button Row -->
  <div style="display: flex; gap: 20px;">
    <!-- Download Button -->
    <a href="$fileName" download style="
        background: linear-gradient(45deg, #f22222ff, #e44211ff);
        padding: 12px 30px;
        border-radius: 15px;
        color: white;
        font-weight: 900;
        font-size: 20px;
        text-decoration: none;
        box-shadow: 0 0 25px #f2652299;
        transition: box-shadow 0.3s ease, transform 0.3s ease;
        font-family: 'Arial Black', Arial, sans-serif;
        letter-spacing: 1.5px;
    " onmouseover="
        this.style.boxShadow='0 0 40px #f26522aa';
        this.style.transform='scale(1.1)';
    " onmouseout="
        this.style.boxShadow='0 0 25px #f2652299';
        this.style.transform='scale(1)';
    ">
      Download File
    </a>

    <!-- View Data Button -->
    <a href="viewdata.php" style="
        background: linear-gradient(45deg, #f22222ff, #e44211ff);
        padding: 12px 30px;
        border-radius: 15px;
        color: white;
        font-weight: 900;
        font-size: 20px;
        text-decoration: none;
        box-shadow: 0 0 25px #f2652299;
        transition: box-shadow 0.3s ease, transform 0.3s ease;
        font-family: 'Arial Black', Arial, sans-serif;
        letter-spacing: 1.5px;
    " onmouseover="
        this.style.boxShadow='0 0 40px #f26522aa';
        this.style.transform='scale(1.1)';
    " onmouseout="
        this.style.boxShadow='0 0 25px #f2652299';
        this.style.transform='scale(1)';
    ">
      View Data
    </a>
  </div>
</div>

<style>
@keyframes bounce {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-6px); }
}
</style>
HTML;
}