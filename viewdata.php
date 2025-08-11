<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'database', 3307);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$masterList = [
    'D01. PDA RELATED/CYCLE COUNT' => ['priority' => 'P2'],
    'D02. CREDIT CARD MACHINE' => ['priority' => 'P1'],
    'D03. TNG READER' => ['priority' => 'P1'],
    'D04. PC / POS RELATED (M1/M2)' => ['priority' => 'P1'],
    'D05. TIDAK LINK (M1/M2)' => ['priority' => 'P1'],
    'D06. MASALAH INTERNET' => ['priority' => 'P1'],
    'D07. DRAWER MASALAH' => ['priority' => 'P1'],
    'D08. UPS / BACKUP BATTERY ROSAK' => ['priority' => 'P2'],
    'D09. CUSTOMER DISPLAY MASALAH' => ['priority' => 'P2'],
    'D10. BARCODE PRINTER ISSUE' => ['priority' => 'P2'],
    'D11. RECEIPT PRINTER MASALAH' => ['priority' => 'P1'],
    'D12. KKMIS BACKEND' => ['priority' => 'P2'],
    'D13. GRN PRINTER MASALAH' => ['priority' => 'P2'],
    'D14. SCANNER ROSAK' => ['priority' => 'P2'],
    'D15. MOUSE / KEYBOARD ROSAK' => ['priority' => 'P2'],
    'D16. OUTLET CABLE TAK KEMAS' => ['priority' => 'P2'],
    'D17. MYKASIH DEVICE' => ['priority' => 'P1'],
    'D99. UNCATEGORIZED' => ['priority' => '-'],
];

$months = [
    1 => 'January',
    2 => 'February',
    3 => 'March',
    4 => 'April',
    5 => 'May',
    6 => 'June',
    7 => 'July',
    8 => 'August',
    9 => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December'
];

$filterMonth = $_GET['month'] ?? date('Y-m');

$year = substr($filterMonth, 0, 4);
$monthNum = (int)substr($filterMonth, 5, 2);

$monthName = $months[$monthNum] ?? '';
$monthNameWithYear = $monthName . ' ' . $year;

// Main work orders query
$sql = "SELECT * FROM work_orders WHERE DATE_FORMAT(created_date, '%Y-%m') = ? ORDER BY ref_no ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $filterMonth);
$stmt->execute();
$result = $stmt->get_result(); // Keep this as $result

// Summary by category query
$summarySql = "
    SELECT 
        TRIM(category) AS category,
        COUNT(*) AS reported,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS closed,
        SUM(CASE WHEN status IN ('Open', 'In Progress', 'On Hold','Called Off') THEN 1 ELSE 0 END) AS pending
    FROM work_orders
    WHERE DATE_FORMAT(created_date, '%Y-%m') = ?
    GROUP BY category
";
$stmtSummary = $conn->prepare($summarySql);
$stmtSummary->bind_param("s", $filterMonth);
$stmtSummary->execute();
$summaryResult = $stmtSummary->get_result();

$counts = [];
if ($summaryResult) {
    while ($row = $summaryResult->fetch_assoc()) {
        $counts[$row['category']] = [
            'reported' => $row['reported'],
            'closed' => $row['closed'],
            'pending' => $row['pending']
        ];
    }
}

// Last updated
$lastUpdatedResult = $conn->query("SELECT MAX(upload_timestamp) AS latest FROM work_orders");
$lastUpdatedRow = $lastUpdatedResult->fetch_assoc();
$lastUpdated = $lastUpdatedRow['latest'] ?? null;

if ($lastUpdated) {
    $lastUpdatedDate = new DateTime($lastUpdated);
    $lastUpdatedFormatted = $lastUpdatedDate->format('j M Y H:i');
} else {
    $lastUpdatedFormatted = "No uploads yet";
}

// Monthly summary - use a different variable name for the result to avoid overwriting $result
$allMonths = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$monthlySummary = [];

foreach ($allMonths as $month) {
    $monthlySummary[$month] = ['reported' => 0, 'closed' => 0, 'pending' => 0];
}

$queryMonthlySummary = "
    SELECT 
        MONTH(created_date) AS month_num,
        MONTHNAME(created_date) AS month_name,
        COUNT(*) AS reported,
        SUM(CASE WHEN LOWER(status) = 'completed' THEN 1 ELSE 0 END) AS closed,
        SUM(CASE WHEN LOWER(status) != 'completed' THEN 1 ELSE 0 END) AS pending
    FROM work_orders
    WHERE YEAR(created_date) = 2025
    GROUP BY month_num
    ORDER BY month_num
";
$monthlySummaryResult = $conn->query($queryMonthlySummary);

if ($monthlySummaryResult && $monthlySummaryResult->num_rows > 0) {
    while ($row = $monthlySummaryResult->fetch_assoc()) {
        $monthShort = substr($row['month_name'], 0, 3);
        $monthlySummary[$monthShort] = [
            'reported' => $row['reported'],
            'closed'   => $row['closed'],
            'pending'  => $row['pending']
        ];
    }
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>View Imported Data</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #ffffffff;
            color: white;
            padding: 15px;
        }
        html {
            scroll-behavior: smooth;
        }
        h1 {
            text-align: center;
            margin-bottom: 30px;
            font-weight: 900;
            color:red;
        }
        
        /* Table styling */
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #1e1e1e;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 0 25px #35343399;
        }
        th, td {
            padding: 15px;
            text-align: left;
            border: 1px solid #f26522; 
            border-bottom: none;
        }
        th {
            background: #f26522;
            font-weight: 900;
            font-size: 16px;
            border: 1px solid #b32a00; 
        }
        tr:hover {
            background-color: #2c2c2c;
        }
        /* Back button styling */
        .btn-back {
            display: inline-block;
            margin-bottom: 20px;
            margin-top:20px;
            padding: 12px 30px;
            border-radius: 15px;
            color: white;
            font-weight: 900;
            font-size: 15px;
            text-decoration: none;
            background: linear-gradient(45deg, #6ba163ff, #4fc275ff);
            box-shadow: 0 0 25px #043d1c99;
            transition: box-shadow 0.3s ease, transform 0.3s ease;
            font-family: 'Arial Black', Arial, sans-serif;
            letter-spacing: 1.5px;
        }
        .btn-back:hover {
            box-shadow: 0 0 40px #2a973caa;
            transform: scale(1.1);
        }
        /* Responsive */
        @media(max-width: 768px) {
            table, thead, tbody, th, td, tr {
                display: block;
            }
            th {
                display: none;
            }
            tr {
                margin-bottom: 15px;
                background-color: #1e1e1e;
                border-radius: 10px;
                padding: 15px;
            }
            td {
                padding-left: 50%;
                position: relative;
                text-align: right;
                border-bottom: none;
            }
            td::before {
                content: attr(data-label);
                position: absolute;
                left: 15px;
                width: 45%;
                padding-left: 15px;
                font-weight: 700;
                text-align: left;
                color: #f26522;
            }
        }

        .btn-scroll-top, .btn-scroll-bottom {
                position: fixed;
                right: 20px;
                background: #ff6161ff;
                color: white;
                padding: 12px 16px;
                border-radius: 50%;
                font-size: 20px;
                text-decoration: none;
                box-shadow: 0 0 10px #f26522aa;
                transition: background-color 0.3s ease;
                z-index: 1000;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .btn-scroll-top {
                bottom: 80px; /* higher up */
            }

            .btn-scroll-bottom {
                bottom: 20px; /* below top button */
            }

            .btn-scroll-top:hover,
            .btn-scroll-bottom:hover {
                background: #3f3e3f0c;
            }
            .sidebar-menu-container {
                position: fixed;
                top: 60px;
                right: 20px;
                z-index: 10000;
                font-family: Arial, sans-serif;
                }

                /* Hamburger icon styling */
                .sidebar-icon {
                width: 35px;
                height: 30px;
                cursor: pointer;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                }

                .sidebar-icon span {
                display: block;
                height: 5px;
                background-color: #f26522; /* Your red/orange */
                border-radius: 3px;
                }

                /* The dropdown menu (hidden by default) */
                .sidebar-menu {
                display: none;
                position: absolute;
                top: 40px;  /* Below the hamburger icon */
                right: 0;
                background-color: #1e1e1e;
                border-radius: 8px;
                box-shadow: 0 0 10px #f26522;
                min-width: 160px;
                overflow: hidden;
                z-index: 10001;
                flex-direction: column;
                }

                /* Show menu when active */
                .sidebar-menu.show {
                display: flex;
                flex-direction: column;
                }

                /* Menu links styling */
                .sidebar-menu a {
                padding: 12px 16px;
                color: white;
                text-decoration: none;
                border-bottom: 1px solid #f26522aa;
                transition: background-color 0.3s ease;
                }

                .sidebar-menu a:last-child {
                border-bottom: none;
                }

                .sidebar-menu a:hover {
                background-color: #f26522;
                color: black;
                }

        .top-left-section {
            margin-bottom: 30px; /* adjust spacing here */
        }
        .top-controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 10px;
        }
         /* Container for logo + heading */
        .logo-heading-row {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }
        .logo-heading-row h1 {
            flex-grow: 1;  /* make h1 take remaining space */
            text-align: center; /* center text horizontally */
            margin: 0; /* remove default h1 margin */
            margin-right:150px;
        }
        #statusDropdown {
    display: none;
    position: absolute;
    background: #1e1e1e;
    border: 1px solid #f26522;
    border-radius: 8px;
    padding: 10px;
    font-size: 12px;
    text-align: left;
    min-width: 150px;
    z-index: 9999;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
  }
  #statusDropdown label {
    color: white;
    display: block;
    margin-bottom: 5px;
  }
  #noResultsRow {
    text-align: center;
    font-style: italic;
    background-color: #f0f0f0;
  }
    </style>
</head>



<body id="top">
    <!-- Sidebar menu container -->
<div class="sidebar-menu-container">
  <a href="javascript:void(0)" class="sidebar-icon" title="Menu" id="sidebarToggle">
    <span></span>
    <span></span>
    <span></span>
  </a>
  
  <div class="sidebar-menu" id="sidebarMenu">
    <a href="index.html">Upload file</a>
    
  </div>
</div>


<a href="#top" class="btn-scroll-top" title="Go to top">↑</a>
<div class="logo-heading-row">
  <img src="KK_LOGO.png" alt="KK Logo" style="height: 80px; margin-right: 20px; " />
  <h1 id="summary">SERVEDECK SUMMARY TABLE</h1>
</div>

<div style="display: flex; align-items: center; justify-content: space-between;">

  <!-- Search by form -->
  <form method="GET" action="viewdata.php" id="monthForm" style="margin: 0;; min-width: 250px; display: flex; align-items: center;">
    <label for="month" style="font-size: 18px; font-weight: bold; color: #000;">Search by:</label>
    <input 
      type="month" 
      id="month" 
      name="month" 
      value="<?= htmlspecialchars($filterMonth) ?>" 
      style="padding: 10px; border-radius: 8px; border: none; font-size: 16px; background-color: #2c2c2c; color: #fff; font-weight: bold; text-align: center; margin-left: 10px;"
    />
  </form>

  <!-- Last updated text -->
  <div style="text-align: center; font-weight: 450; font-size: 18px; color: #555;">
    Last Updated: <?php echo $lastUpdatedFormatted; ?>
  </div>

  <!-- Button to jump to summary -->
  <a href="#workorder-table" class="btn-back" style="
    margin-bottom: 20px;
    padding: 8px 20px;
    background-color: #4CAF50;
    color: white;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    display: inline-block;
    float: right; /* aligns right */
    ">
    Go to Details Table
    </a>
    </div>

    <a href="#workorder-table" class="btn-scroll-bottom" title="Go to bottom">↓</a>

    <div id="top"></div>



    </div>

<!-- SUMMARY TABLE FIRST -->

<?php
$totalReported = 0;
$totalClosed = 0;
$totalPending = 0;

foreach ($masterList as $category => $details) {
    $reported = $counts[$category]['reported'] ?? 0;
    $closed = $counts[$category]['closed'] ?? 0;
    $pending = $counts[$category]['pending'] ?? 0;

    $totalReported += $reported;
    $totalClosed += $closed;
    $totalPending += $pending;
}
?>

<table border="1" style="border-collapse: collapse; width: 100%; text-align: center;">
    <thead>
        <!-- Month Title Row -->
        <tr>
            <th rowspan="3" style="text-align: center; color:black; font-size: 25px;">PRIORITY</th>
            <th rowspan="3" style="text-align: center; color:black; font-size: 25px;">CATEGORY</th>
            <th colspan="3" style="text-align: center; color:black; font-size: 25px;"><?= htmlspecialchars($monthNameWithYear) ?></th>
        </tr>

       
        <!-- Label Row -->
        <tr>
            <th style="text-align: center; background-color: #dfdc2eff; color:black; font-size: 20px;">Reported</th>
            <th style="text-align: center; background-color:#048d0bff; color:black; font-size: 20px;">Closed</th>
            <th style="text-align: center; background-color:#f32323ff; color:black; font-size: 20px;">Pending</th>
        </tr>
        <tr>
            <th style="text-align:center; font-weight:bold; background-color: white; color:black; font-size: 20px;"><?= $totalReported ?></th>
            <th style="text-align:center; font-weight:bold; background-color: white; color:black; font-size: 20px;">
                <?= $totalReported > 0 ? round(($totalClosed / $totalReported) * 100, 1) : 0 ?>%
            </th>
            <th style="text-align:center; font-weight:bold; background-color : white; color:black; font-size: 20px;"><?= $totalPending ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($masterList as $category => $details): ?>
            <?php
                $priority = $details['priority'];
                $reported = $counts[$category]['reported'] ?? 0;
                $closed = $counts[$category]['closed'] ?? 0;
                $pending = $counts[$category]['pending'] ?? 0;
            ?>
            <tr>
                <td data-label="Priority" style="text-align: center;"><?= htmlspecialchars($priority) ?></td>
                <td data-label="Category" style="text-align: left;"><?= htmlspecialchars($category) ?></td>
                <td data-label="Total Reported" style="text-align: center;"><?= $reported ?></td>
                <td data-label="Total Closed" style="text-align: center; "><?= $closed ?></td>
                <td data-label="Total Pending" style="text-align: center; "><?= $pending ?></td>
            </tr>
        <?php endforeach; ?>
        <tr style="font-weight: bold; background-color: #333; color: white;">
            <td colspan="2" style="text-align: center; font-weight:bold; font-size: 20px; color:black;">TOTAL</td>
            <td style="text-align: center; color:yellow;"><?= $totalReported ?></td>
            <td style="text-align: center; color:green;"><?= $totalClosed ?></td>
            <td style="text-align: center; color:red;"><?= $totalPending ?></td>
        </tr>
    </tbody>
</table>


<!-- 3. Render Yearly Summary Table -->
<h1 style="text-align: center; margin-top: 60px; font-size: 32px; font-weight: bold; color:red; ">YEARLY SUMMARY TABLE FOR 2025</h2>

<table border="1" style="border-collapse: collapse; width: 100%; text-align: center; margin-top: 20px; color:black">
    <thead>
        <tr>
        <th rowspan="2" style="background-color: #f26522; text-align: center; font-weight: bold; font-size: 25px;">MONTH</th>
        <th colspan="3" style="background-color: #f26522; text-align: center; font-weight: bold; font-size: 25px;">2025</th>
    </tr>
    <tr>
        <th style="background-color: #dfdc2e; text-align: center; font-weight: bold; font-size: 20px;">REPORTED</th>
        <th style="background-color: #048d0b; text-align: center; font-weight: bold; font-size: 20px;">CLOSED</th>
        <th style="background-color: #f32323; text-align: center; font-weight: bold; font-size: 20px;">PENDING</th>
    </tr>
    </thead>
    <tbody>
        <?php
        $totalReported = $totalClosed = $totalPending = 0;

        foreach ($allMonths as $month) {
            $reported = $monthlySummary[$month]['reported'];
            $closed   = $monthlySummary[$month]['closed'];
            $pending  = $monthlySummary[$month]['pending'];

            $totalReported += $reported;
            $totalClosed   += $closed;
            $totalPending  += $pending;

            echo "<tr>
                <td style='text-align: center; color:white;'>$month</td>
                <td style='text-align: center; color:white;'>$reported</td>
                <td style='text-align: center; color:white;'>$closed</td>
                <td style='text-align: center; color:white;'>$pending</td>
            </tr>";

        }
        ?>
        <!-- Optional Total Row -->
        <tr style="font-weight: bold; background-color: #333; color: white;">
            <td style="text-align: center;">TOTAL</td>
            <td style="color: yellow; text-align: center;"><?= $totalReported ?></td>
            <td style="color: lightgreen; text-align: center;"><?= $totalClosed ?></td>
            <td style="color: red; text-align: center;"><?= $totalPending ?></td>
        </tr>
    </tbody>
</table>


<!-- NOW THE WORK ORDER DATA TABLE BELOW -->
<div style="display: flex; align-items: center; justify-content: space-between; margin-top: 50px; margin-bottom:20px; position: relative; width: 100%;">
    <!-- Invisible spacer to balance the layout -->
    <div style="flex: 1;"></div>

    <!-- Centered Heading -->
    <h1 id=workorder-table style="margin: 0; font-weight: bold; font-size: 32px; color: red; position: absolute; left: 50%; transform: translateX(-50%);">
    WORK ORDER DATA TABLE
  </h1>


    <!-- Search Input on the Right -->
    <input 
        type="text" 
        id="searchInput" 
        placeholder="🔍 Search by Ref. No..." 
        style="
            padding: 10px 15px;
            font-size: 15px;
            width: 280px;
            border: 1px solid #ccc;
            border-radius: 25px;
            box-shadow: 0 2px 4px rgba(233, 10, 10, 0.1);
            outline: none;
            transition: all 0.2s ease;
            border-color:orange ;
        "
        onfocus="this.style.borderColor='#4A90E2'; this.style.boxShadow='0 0 5px rgba(74, 144, 226, 0.5)';"
        onblur="this.style.borderColor='#ccc'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.1)';"
    />
</div>


<?php if ($result && $result->num_rows > 0): ?>
<table id="workordertable">

    <thead>
        <tr>
            <th style="text-align: center; color:black">ID</th>
            <th style="text-align: center; color:black">REF NO.</th>
             <th id="statusHeader" style="text-align: center; color:black; position: relative; cursor: pointer;">
                STATUS <span style="color: #ffffffff;">▼</span>
                
            </th>


            <th style="text-align: center; color:black">WORK ORDER</th>
            <th style="text-align: center; color:black">CATEGORY</th>
            <th style="text-align: center; color:black">SUBCATEGORY</th>
            <th style="text-align: center; color:black">CREATED DATE</th>
            <th style="text-align: center; color:black">COMPLETED DATE</th>
            <th style="text-align: center; color:black">COMPLETION DURATION</th>
            <th style="text-align: center; color:black">REQUESTOR</th>
            <th style="text-align: center; color:black">ASSIGNEE</th>
            <th style="text-align: center; color:black">OCCUPANT</th>
            <th style="text-align: center; color:black">WORK DESCRIPTION</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $counter = 1; // Start counter at 1
        while ($row = $result->fetch_assoc()):
        ?>
            <tr>
                <td data-label="ID"><?= $counter++ ?></td>
                <td data-label="Ref.No"><?= htmlspecialchars($row['ref_no']) ?></td>
                <td 
                    data-label="Status" 
                    data-status="<?= strtolower(trim($row['status'])) ?>" 
                    style="text-align: center;"
                    >
                    <?= htmlspecialchars($row['status']) ?>
                    </td>
                <td data-label="Workorder No"><?= htmlspecialchars($row['work_order']) ?></td>
                <td data-label="Category"><?= htmlspecialchars($row['category']) ?></td>
                <td data-label="Subcategory"><?= htmlspecialchars($row['sub_category']) ?></td>
                <td data-label="Created Date"><?= htmlspecialchars($row['created_date']) ?></td>
                <td data-label="Completed Date"><?= htmlspecialchars($row['completed_date']) ?></td>
                <td data-label="Completion Duration"><?= htmlspecialchars($row['completion_duration']) ?></td>
                <td data-label="Requestor"><?= htmlspecialchars($row['requestor']) ?></td>
                <td data-label="Assignee"><?= htmlspecialchars($row['assignee']) ?></td>
                <td data-label="Occupant"><?= htmlspecialchars($row['occupant']) ?></td>
                <td data-label="Work Description"><?= htmlspecialchars($row['work_description']) ?></td>
            </tr>

            <?php endwhile; ?>
            <tr id="noResultsRow" style="display:none; color: white; background-color: #333;">
            <td colspan="13" style="text-align:center;">No results found</td>
            </tr>
        
    </tbody>
            
</table>

<div id="statusDropdown">
  <label><input type="checkbox" id="selectAllStatus" checked> <strong>Select All</strong></label>
  <label><input type="checkbox" class="status-checkbox" value="completed" checked> Completed</label>
  <label><input type="checkbox" class="status-checkbox" value="open" checked> Open</label>
  <label><input type="checkbox" class="status-checkbox" value="in progress" checked> In Progress</label>
  <label><input type="checkbox" class="status-checkbox" value="on hold" checked> On Hold</label>
  <label><input type="checkbox" class="status-checkbox" value="called off" checked> Called Off</label>
</div>

<script>
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebarMenu = document.getElementById('sidebarMenu');

  sidebarToggle.addEventListener('click', () => {
    sidebarMenu.classList.toggle('show');
  });

  // Close menu if click outside
  document.addEventListener('click', (e) => {
    if (!sidebarMenu.contains(e.target) && !sidebarToggle.contains(e.target)) {
      sidebarMenu.classList.remove('show');
    }
  });
</script>

<script>
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll('table tbody tr');

        rows.forEach(row => {
            const refNoCell = row.querySelector('td[data-label="Ref.No"]');
            const refNo = refNoCell ? refNoCell.textContent.toLowerCase() : '';
            row.style.display = refNo.includes(filter) ? '' : 'none';
        });
    });
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const statusHeader = document.getElementById('statusHeader');
  const statusDropdown = document.getElementById('statusDropdown');
  const selectAll = document.getElementById('selectAllStatus');
  const checkboxes = document.querySelectorAll('.status-checkbox');

  // Toggle dropdown visibility and position it below STATUS header
  statusHeader.addEventListener('click', function () {
    const rect = statusHeader.getBoundingClientRect();
    const scrollTop = window.scrollY || document.documentElement.scrollTop;
    const scrollLeft = window.scrollX || document.documentElement.scrollLeft;

    statusDropdown.style.top = `${rect.bottom + scrollTop}px`;
    statusDropdown.style.left = `${rect.left + scrollLeft}px`;
    statusDropdown.style.display = (statusDropdown.style.display === 'block') ? 'none' : 'block';
  });

  // Hide dropdown when clicking outside
  document.addEventListener('click', function (e) {
    if (!statusHeader.contains(e.target) && !statusDropdown.contains(e.target)) {
      statusDropdown.style.display = 'none';
    }
  });

  function filterTableByStatus() {
    const selected = Array.from(checkboxes)
      .filter(cb => cb.checked)
      .map(cb => cb.value.toLowerCase());

    const rows = document.querySelectorAll('#workordertable tbody tr:not(#noResultsRow)');
    let visibleCount = 0;

    rows.forEach(row => {
      const statusCell = row.querySelector('td[data-label="Status"]');
      const status = statusCell?.getAttribute('data-status');
      if (status && selected.includes(status)) {
        row.style.display = '';
        visibleCount++;
      } else {
        row.style.display = 'none';
      }
    });

    const noResultsRow = document.getElementById('noResultsRow');
    if (visibleCount === 0) {
      noResultsRow.style.display = '';
    } else {
      noResultsRow.style.display = 'none';
    }
  }

  // Checkbox change handlers
  checkboxes.forEach(cb => {
    cb.addEventListener('change', () => {
      selectAll.checked = Array.from(checkboxes).every(cb => cb.checked);
      filterTableByStatus();
    });
  });

  selectAll.addEventListener('change', () => {
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    filterTableByStatus();
  });

  // Initial filter
  filterTableByStatus();
});
</script>



  

<script>
// Auto-submit the form when the month is changed
document.getElementById('month').addEventListener('change', function () {
    document.getElementById('monthForm').submit();
});
</script>


<?php else: ?>
<p>No imported data found.</p>
<?php endif; ?>

</body>
</html>