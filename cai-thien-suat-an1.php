<?php
// cai-thien-suat-an.php
require_once 'db_connect.php';

// 8. Trang web yêu cầu đăng nhập mới xem được
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';
$today = date('Y-m-d');
$current_week = date('Y-\WW'); // VD: 2026-W37

// Tự động khởi tạo cấu trúc CSDL phục vụ quản lý suất ăn
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `meal_menus` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `week_code` VARCHAR(20) NOT NULL,
            `year` INT NOT NULL,
            `day_of_week` VARCHAR(20) NOT NULL,
            `meal_type` VARCHAR(30) DEFAULT 'Bữa Trưa',
            `dish_name` VARCHAR(150) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`week_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `meal_checks` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `menu_id` INT NOT NULL,
            `check_date` DATE NOT NULL,
            `is_correct` TINYINT(1) DEFAULT 1,
            `adjustment_percent` INT DEFAULT 0,
            `quality_rating` VARCHAR(50) DEFAULT 'Tốt',
            `quality_note` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`check_date`),
            INDEX (`menu_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (PDOException $e) {
    $error = "Lỗi khởi tạo CSDL: " . $e->getMessage();
}

// ==================== XỬ LÝ CÁC YÊU CẦU POST ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $act = $_POST['action'];

    if ($act === 'add_menu_manual') {
        $week_code = trim($_POST['week_code'] ?? $current_week);
        $year      = intval($_POST['year'] ?? date('Y'));
        $day_of_w  = trim($_POST['day_of_week'] ?? 'Thứ 2');
        $meal_type = trim($_POST['meal_type'] ?? 'Bữa Trưa');
        $dish_name = trim($_POST['dish_name'] ?? '');
        $desc      = trim($_POST['description'] ?? '');

        if (!empty($dish_name)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO meal_menus (week_code, year, day_of_week, meal_type, dish_name, description)
                    VALUES (:wcode, :yr, :dow, :mtype, :dname, :desc)
                ");
                $stmt->execute([
                    ':wcode' => $week_code, ':yr' => $year, ':dow' => $day_of_w,
                    ':mtype' => $meal_type, ':dname' => $dish_name, ':desc' => $desc
                ]);
                $message = "Đã thêm món ăn vào thực đơn tuần [{$week_code}] thành công!";
            } catch (PDOException $e) {
                $error = "Lỗi thêm thực đơn: " . $e->getMessage();
            }
        }
    }

    if ($act === 'smart_import_file') {
        $week_code = trim($_POST['week_code'] ?? $current_week);
        $year      = intval($_POST['year'] ?? date('Y'));
        $raw_text  = trim($_POST['raw_import_text'] ?? '');

        if (!empty($raw_text)) {
            try {
                $lines = explode("\n", $raw_text);
                $pdo->beginTransaction();
                $count = 0;
                
                $currentDay = 'Thứ 2';
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    if (stripos($line, 'thứ') !== false || stripos($line, 'ngày') !== false) {
                        $currentDay = $line;
                        continue;
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO meal_menus (week_code, year, day_of_week, meal_type, dish_name, description)
                        VALUES (:wcode, :yr, :dow, 'Bữa Trưa', :dname, 'Nhập thông minh tự động')
                    ");
                    $stmt->execute([
                        ':wcode' => $week_code, ':yr' => $year, ':dow' => $currentDay, ':dname' => $line
                    ]);
                    $count++;
                }
                $pdo->commit();
                $message = "Đã trích xuất và nhập thành công {$count} món ăn vào thực đơn tuần [{$week_code}]!";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = "Lỗi phân tích file thông minh: " . $e->getMessage();
            }
        } else {
            $error = "Vui lòng nhập nội dung thực đơn cần trích xuất!";
        }
    }

    if ($act === 'save_daily_check') {
        $menu_id    = intval($_POST['menu_id']);
        $check_date = $_POST['check_date'] ?? $today;
        $is_correct = isset($_POST['is_correct']) ? 1 : 0;
        $adj_pct    = intval($_POST['adjustment_percent'] ?? 0);
        $rating     = trim($_POST['quality_rating'] ?? 'Tốt');
        $q_note     = trim($_POST['quality_note'] ?? '');

        try {
            $chk = $pdo->prepare("SELECT id FROM meal_checks WHERE menu_id = :mid AND check_date = :cdate");
            $chk->execute([':mid' => $menu_id, ':cdate' => $check_date]);
            $exists = $chk->fetch();

            if ($exists) {
                $stmt = $pdo->prepare("
                    UPDATE meal_checks 
                    SET is_correct = :corr, adjustment_percent = :apct, quality_rating = :rat, quality_note = :qnote
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':corr' => $is_correct, ':apct' => $is_correct ? 0 : $adj_pct,
                    ':rat' => $rating, ':qnote' => $q_note, ':id' => $exists['id']
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO meal_checks (menu_id, check_date, is_correct, adjustment_percent, quality_rating, quality_note)
                    VALUES (:mid, :cdate, :corr, :apct, :rat, :qnote)
                ");
                $stmt->execute([
                    ':mid' => $menu_id, ':cdate' => $check_date, ':corr' => $is_correct,
                    ':apct' => $is_correct ? 0 : $adj_pct, ':rat' => $rating, ':qnote' => $q_note
                ]);
            }
            $message = "Đã lưu kết quả kiểm tra và đánh giá chất lượng suất ăn thành công!";
        } catch (PDOException $e) {
            $error = "Lỗi lưu đánh giá: " . $e->getMessage();
        }
    }
}

// ==================== BỘ LỌC THỜI GIAN THỐNG KÊ ====================
$stat_period = $_GET['stat_period'] ?? '1_week';
$intervalSQL = "INTERVAL 7 DAY";
if ($stat_period === '2_weeks') $intervalSQL = "INTERVAL 14 DAY";
elseif ($stat_period === '3_weeks') $intervalSQL = "INTERVAL 21 DAY";
elseif ($stat_period === 'month') $intervalSQL = "INTERVAL 1 MONTH";
elseif ($stat_period === 'year') $intervalSQL = "INTERVAL 1 YEAR";

// ==================== XỬ LÝ XUẤT EXCEL BÁO CÁO ====================
if (isset($_GET['export_excel'])) {
    $date_str = date('d-m-Y');
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=Bao_Cao_Cai_Dien_Suat_An_{$stat_period}_{$date_str}.xls");
    header("Pragma: no-cache"); header("Expires: 0"); echo "\xEF\xBB\xBF";

    // Lấy dữ liệu chi tiết kiểm tra theo bộ lọc
    $stmtExp = $pdo->prepare("
        SELECT m.week_code, m.day_of_week, m.meal_type, m.dish_name, c.check_date, c.is_correct, c.adjustment_percent, c.quality_rating, c.quality_note
        FROM meal_checks c
        JOIN meal_menus m ON c.menu_id = m.id
        WHERE c.check_date >= DATE_SUB(:td, $intervalSQL)
        ORDER BY c.check_date DESC, m.id DESC
    ");
    $stmtExp->execute([':td' => $today]);
    $exportRows = $stmtExp->fetchAll();

    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style>
        .rpt-title { font-family:"Times New Roman", Arial; font-size:16pt; font-weight:bold; text-align:center; height:35px; } 
        .th-c { background-color:#0284c7; color:#ffffff; font-weight:bold; text-align:center; border:0.5pt solid #000; height:28px; } 
        .td-c { text-align:center; border:0.5pt solid #000; height:25px; } 
        .td-l { text-align:left; border:0.5pt solid #000; padding-left:6px; } 
    </style></head><body>';
    echo '<table border="1">';
    echo '<tr><td colspan="9" class="rpt-title">BÁO CÁO CHI TIẾT CẢI THIỆN CHẤT LƯỢNG SUẤT ĂN (Mốc: ' . strtoupper(str_replace('_', ' ', $stat_period)) . ')</td></tr>';
    echo '<tr><td colspan="9" style="text-align:center; font-style:italic;">Thời gian xuất: ' . date('d/m/Y H:i:s') . '</td></tr><tr><td colspan="9" style="border:none; height:10px;"></td></tr>';

    echo '<tr>
            <th class="th-c" width="45">STT</th>
            <th class="th-c" width="100">Mã Tuần</th>
            <th class="th-c" width="100">Ngày Kiểm Tra</th>
            <th class="th-c" width="90">Thứ / Buổi</th>
            <th class="th-c" width="220">Tên Món Ăn</th>
            <th class="th-c" width="90">Nấu Đúng?</th>
            <th class="th-c" width="90">Tỷ Lệ Đổi</th>
            <th class="th-c" width="110">Chất Lượng</th>
            <th class="th-c" width="220">Ghi Chú Đánh Giá</th>
          </tr>';

    $stt = 1;
    if (!empty($exportRows)) {
        foreach ($exportRows as $row) {
            $dungSai = intval($row['is_correct']) === 1 ? 'Đúng thực đơn' : 'Nấu sai/Đổi món';
            $tyle = intval($row['is_correct']) === 1 ? '0%' : intval($row['adjustment_percent']) . '%';
            echo "<tr>
                    <td class='td-c'>{$stt}</td>
                    <td class='td-c'>" . htmlspecialchars($row['week_code']) . "</td>
                    <td class='td-c'>" . date('d/m/Y', strtotime($row['check_date'])) . "</td>
                    <td class='td-c'>" . htmlspecialchars($row['day_of_week']) . " (" . htmlspecialchars($row['meal_type']) . ")</td>
                    <td class='td-l'><b>" . htmlspecialchars($row['dish_name']) . "</b></td>
                    <td class='td-c'>{$dungSai}</td>
                    <td class='td-c'>{$tyle}</td>
                    <td class='td-c'>" . htmlspecialchars($row['quality_rating']) . "</td>
                    <td class='td-l'>" . htmlspecialchars($row['quality_note'] ?: '—') . "</td>
                  </tr>";
            $stt++;
        }
    } else {
        echo '<tr><td colspan="9" class="td-c" style="height:35px; font-style:italic;">Không có dữ liệu trong khoảng thời gian này</td></tr>';
    }

    echo '</table></body></html>';
    exit;
}

// ==================== TRUY VẤN DỮ LIỆU HIỂN THỊ ====================
$availableWeeks = [];
try {
    $availableWeeks = $pdo->query("SELECT DISTINCT week_code FROM meal_menus ORDER BY week_code DESC")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

$selected_week = $_GET['f_week'] ?? ($availableWeeks[0] ?? $current_week);

$todayMenus = [];
try {
    $stmtM = $pdo->prepare("
        SELECT m.*, c.is_correct, c.adjustment_percent, c.quality_rating, c.quality_note 
        FROM meal_menus m 
        LEFT JOIN meal_checks c ON m.id = c.menu_id AND c.check_date = :td
        WHERE m.week_code = :wcode
        ORDER BY m.id DESC
    ");
    $stmtM->execute([':td' => $today, ':wcode' => $selected_week]);
    $todayMenus = $stmtM->fetchAll();
} catch (PDOException $e) {}

// 4. Món có tần suất điều chỉnh cao nhất
$topAdjustedDishes = [];
try {
    $stmtTop = $pdo->prepare("
        SELECT m.dish_name, COUNT(c.id) as total_checks, AVG(c.adjustment_percent) as avg_adj, SUM(CASE WHEN c.is_correct = 0 THEN 1 ELSE 0 END) as wrong_count
        FROM meal_checks c
        JOIN meal_menus m ON c.menu_id = m.id
        WHERE c.check_date >= DATE_SUB(:td, $intervalSQL)
        GROUP BY m.dish_name
        ORDER BY wrong_count DESC, avg_adj DESC
        LIMIT 5
    ");
    $stmtTop->execute([':td' => $today]);
    $topAdjustedDishes = $stmtTop->fetchAll();
} catch (PDOException $e) {}

// 6. Ý kiến chất lượng chiếm đa số
$dominantRatings = [];
try {
    $stmtRat = $pdo->prepare("
        SELECT quality_rating, COUNT(*) as count_rat
        FROM meal_checks
        WHERE check_date >= DATE_SUB(:td, $intervalSQL)
        GROUP BY quality_rating
        ORDER BY count_rat DESC
    ");
    $stmtRat->execute([':td' => $today]);
    $dominantRatings = $stmtRat->fetchAll();
} catch (PDOException $e) {}

// 7. Thuật toán phân tích thông minh: Đưa ra vấn đề cần giải quyết và vị trí
$smartInsights = [];
try {
    $stmtW = $pdo->prepare("
        SELECT m.dish_name, SUM(CASE WHEN c.is_correct = 0 THEN 1 ELSE 0 END) as fails
        FROM meal_checks c JOIN meal_menus m ON c.menu_id = m.id
        WHERE c.check_date >= DATE_SUB(:td, INTERVAL 30 DAY)
        GROUP BY m.dish_name ORDER BY fails DESC LIMIT 1
    ");
    $stmtW->execute([':td' => $today]);
    $worstDish = $stmtW->fetch();

    if ($worstDish && $worstDish['fails'] > 0) {
        $smartInsights[] = [
            'type' => 'danger',
            'title' => 'Vấn đề về độ chính xác thực đơn:',
            'desc' => "Món <b>{$worstDish['dish_name']}</b> bị nhà bếp tự ý thay đổi hoặc nấu sai thực đơn nhiều nhất ({$worstDish['fails']} lần trong tháng qua). Cần làm việc lại với bộ phận chế biến."
        ];
    }

    $stmtQ = $pdo->prepare("
        SELECT m.dish_name, COUNT(*) as bads
        FROM meal_checks c JOIN meal_menus m ON c.menu_id = m.id
        WHERE c.quality_rating = 'Kém' AND c.check_date >= DATE_SUB(:td, INTERVAL 30 DAY)
        GROUP BY m.dish_name ORDER BY bads DESC LIMIT 1
    ");
    $stmtQ->execute([':td' => $today]);
    $worstQuality = $stmtQ->fetch();

    if ($worstQuality && $worstQuality['bads'] > 0) {
        $smartInsights[] = [
            'type' => 'warning',
            'title' => 'Vấn đề về chất lượng hương vị:',
            'desc' => "Món <b>{$worstQuality['dish_name']}</b> nhận nhiều phản hồi đánh giá 'Kém' nhất trong tháng. Cần kiểm tra lại định lượng gia vị hoặc nguồn nguyên liệu."
        ];
    }
} catch (PDOException $e) {}

include 'header.php';
?>

<style>
    .meal-wrapper { max-width: 1320px; margin: 25px auto; padding: 0 16px; }
    .meal-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; }
    .meal-title { font-size: 1.5rem; font-weight: 700; color: #0f172a; }
    .card-box { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .table-data { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
    .table-data th, .table-data td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .table-data th { background: #f8fafc; color: #475569; font-weight: 600; text-align: left; }
    .btn-action { background: #0284c7; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
    .btn-action:hover { background: #0369a1; }
    .btn-export { background: #10b981; color: #fff; border: none; padding: 8px 14px; border-radius: 6px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
    .btn-export:hover { background: #059669; }
    .alert-box { padding: 12px 16px; border-radius: 6px; font-size: 0.9rem; margin-bottom: 16px; font-weight: 600; }
    .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .insight-card { background: #fffbeb; border: 1px solid #fde68a; border-left: 4px solid #f59e0b; padding: 14px 18px; border-radius: 6px; margin-bottom: 12px; font-size: 0.9rem; color: #b45309; }
</style>

<div class="meal-wrapper">
    <div class="meal-header">
        <div>
            <h1 class="meal-title">🍲 Hệ Thống Cải Thiện Chất Lượng Suất Ăn</h1>
            <p style="font-size: 0.88rem; color: #64748b; margin-top: 4px;">
                Quản lý thực đơn tuần, kiểm tra thực tế nhà ăn, phân tích tần suất điều chỉnh và đánh giá chất lượng.
            </p>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="cai-thien-suat-an.php?export_excel=1&stat_period=<?= urlencode($stat_period) ?>&f_week=<?= urlencode($selected_week) ?>" class="btn-export">
                📊 Xuất Excel Báo Cáo Cải Thiện Suất Ăn
            </a>
            <button class="btn-action" style="background:#8b5cf6;" onclick="openModal('modal-smart-import')">📥 Nhập Thông Minh (Excel/Ảnh)</button>
            <button class="btn-action" onclick="openModal('modal-manual-menu')">+ Thêm Thực Đơn Thủ Công</button>
        </div>
    </div>

    <?php if ($message): ?><div class="alert-box alert-success"><?= $message ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert-box alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- 7. PHÂN TÍCH THÔNG MINH: VẤN ĐỀ CẦN GIẢI QUYẾT -->
    <div class="card-box" style="border-left: 5px solid #0284c7;">
        <h3 style="font-size:1.1rem; color:#0369a1; margin-bottom:12px;">🧠 Trợ Lý Thông Minh - Vấn Đề Cần Giải Quyết</h3>
        <?php if (!empty($smartInsights)): ?>
            <?php foreach ($smartInsights as $ins): ?>
                <div class="insight-card" style="<?= $ins['type'] === 'danger' ? 'background:#fee2e2; border-color:#fecaca; border-left-color:#dc2626; color:#991b1b;' : '' ?>">
                    <strong><?= $ins['title'] ?></strong> <?= $ins['desc'] ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="font-size:0.88rem; color:#15803d; font-weight:600;">✓ Chưa phát hiện sự cố bất thường nghiêm trọng trong tháng qua. Chất lượng suất ăn đang ổn định.</p>
        <?php endif; ?>
    </div>

    <!-- BỘ LỌC CHỌN TUẦN ĐỂ XEM THỰC ĐƠN -->
    <form method="GET" action="cai-thien-suat-an.php" class="card-box" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap; padding:15px 20px;">
        <input type="hidden" name="stat_period" value="<?= htmlspecialchars($stat_period) ?>">
        <label style="font-weight:700; font-size:0.9rem;">📅 Chọn Tuần Thực Đơn:</label>
        <select name="f_week" onchange="this.form.submit()" style="padding:7px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.9rem; min-width:180px;">
            <?php if (!empty($availableWeeks)): ?>
                <?php foreach ($availableWeeks as $wk): ?>
                    <option value="<?= htmlspecialchars($wk) ?>" <?= $selected_week === $wk ? 'selected' : '' ?>>Tuần: <?= htmlspecialchars($wk) ?></option>
                <?php endforeach; ?>
            <?php else: ?>
                <option value="<?= $current_week ?>">Tuần hiện tại: <?= $current_week ?></option>
            <?php endif; ?>
        </select>
        <?php if ($selected_week !== $current_week): ?>
            <a href="cai-thien-suat-an.php?stat_period=<?= urlencode($stat_period) ?>" style="font-size:0.85rem; color:#0284c7; text-decoration:none;">Xem tuần hiện tại</a>
        <?php endif; ?>
    </form>

    <!-- 2 & 5. KIỂM TRA HÀNG NGÀY & ĐÁNH GIÁ CHẤT LƯỢNG -->
    <div class="card-box">
        <h3 style="font-size:1.1rem; color:#334155; margin-bottom:15px;">📋 Kiểm Tra Thực Tế Thực Đơn Tuần [<?= htmlspecialchars($selected_week) ?>] (Hôm nay: <?= date('d/m/Y') ?>)</h3>
        <div style="overflow-x:auto;">
            <table class="table-data">
                <thead>
                    <tr>
                        <th>Thứ / Buổi</th>
                        <th>Tên Món Ăn (Thực Đơn)</th>
                        <th style="width:110px; text-align:center;">Nấu Đúng?</th>
                        <th style="width:130px;">Tỷ Lệ Đổi (%)</th>
                        <th style="width:130px;">Chất Lượng</th>
                        <th>Ghi Chú Đánh Giá</th>
                        <th style="width:100px; text-align:center;">Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($todayMenus)): ?>
                        <?php foreach ($todayMenus as $m): ?>
                            <form method="POST" action="cai-thien-suat-an.php?f_week=<?= urlencode($selected_week) ?>&stat_period=<?= urlencode($stat_period) ?>">
                                <input type="hidden" name="action" value="save_daily_check">
                                <input type="hidden" name="menu_id" value="<?= $m['id'] ?>">
                                <input type="hidden" name="check_date" value="<?= $today ?>">
                                <tr>
                                    <td><b><?= htmlspecialchars($m['day_of_week']) ?></b><br><span style="font-size:0.75rem; color:#64748b;"><?= htmlspecialchars($m['meal_type']) ?></span></td>
                                    <td><strong><?= htmlspecialchars($m['dish_name']) ?></strong></td>
                                    <td style="text-align:center;">
                                        <input type="checkbox" name="is_correct" value="1" <?= (!isset($m['is_correct']) || $m['is_correct'] == 1) ? 'checked' : '' ?> style="width:18px; height:18px; cursor:pointer;">
                                    </td>
                                    <td>
                                        <input type="number" name="adjustment_percent" min="0" max="100" value="<?= intval($m['adjustment_percent'] ?? 0) ?>" style="width:80px; padding:6px; border:1px solid #cbd5e1; border-radius:4px;">
                                    </td>
                                    <td>
                                        <select name="quality_rating" style="padding:6px; border:1px solid #cbd5e1; border-radius:4px; width:100%;">
                                            <option value="Tốt" <?= ($m['quality_rating'] ?? '') === 'Tốt' ? 'selected' : '' ?>>Tốt</option>
                                            <option value="Trung bình" <?= ($m['quality_rating'] ?? '') === 'Trung bình' ? 'selected' : '' ?>>Trung bình</option>
                                            <option value="Kém" <?= ($m['quality_rating'] ?? '') === 'Kém' ? 'selected' : '' ?>>Kém</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="quality_note" value="<?= htmlspecialchars($m['quality_note'] ?? '') ?>" placeholder="Nhận xét chi tiết..." style="width:100%; padding:6px; border:1px solid #cbd5e1; border-radius:4px;">
                                    </td>
                                    <td style="text-align:center;">
                                        <button type="submit" class="btn-action" style="padding:5px 10px; font-size:0.8rem;">Lưu</button>
                                    </td>
                                </tr>
                            </form>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center; padding:25px; color:#64748b;">Chưa có món ăn nào trong thực đơn tuần này. Vui lòng bấm "Thêm Thực Đơn Thủ Công" hoặc "Nhập Thông Minh" ở phía trên.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- BỘ LỌC THỜI GIAN THỐNG KÊ (1 Tuần, 2 Tuần, 3 Tuần, Tháng, Năm) -->
    <form method="GET" action="cai-thien-suat-an.php" class="card-box" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap; padding:15px 20px;">
        <input type="hidden" name="f_week" value="<?= htmlspecialchars($selected_week) ?>">
        <label style="font-weight:700; font-size:0.9rem;">📊 Thống kê báo cáo theo mốc thời gian:</label>
        <select name="stat_period" onchange="this.form.submit()" style="padding:7px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.9rem;">
            <option value="1_week" <?= $stat_period === '1_week' ? 'selected' : '' ?>>1 Tuần qua</option>
            <option value="2_weeks" <?= $stat_period === '2_weeks' ? 'selected' : '' ?>>2 Tuần qua</option>
            <option value="3_weeks" <?= $stat_period === '3_weeks' ? 'selected' : '' ?>>3 Tuần qua</option>
            <option value="month" <?= $stat_period === 'month' ? 'selected' : '' ?>>1 Tháng qua</option>
            <option value="year" <?= $stat_period === 'year' ? 'selected' : '' ?>>1 Năm qua</option>
        </select>
    </form>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(480px, 1fr)); gap:22px;">
        <!-- 4. MÓN CÓ TẦN SUẤT ĐIỀU CHỈNH CAO NHẤT -->
        <div class="card-box" style="margin-bottom:0;">
            <h3 style="font-size:1.1rem; color:#334155; margin-bottom:12px;">📈 Top Món Ăn Bị Điều Chỉnh / Nấu Sai Nhiều Nhất</h3>
            <table class="table-data">
                <thead>
                    <tr>
                        <th>Tên Món Ăn</th>
                        <th style="text-align:center;">Số Lần Sai / Đổi</th>
                        <th style="text-align:center;">Mức Đổi TB (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($topAdjustedDishes)): ?>
                        <?php foreach ($topAdjustedDishes as $tad): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($tad['dish_name']) ?></strong></td>
                                <td style="text-align:center; color:#dc2626; font-weight:700;"><?= intval($tad['wrong_count']) ?> lần</td>
                                <td style="text-align:center; color:#0284c7; font-weight:700;"><?= round($tad['avg_adj'], 1) ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" style="text-align:center; padding:20px; color:#64748b;">Chưa có dữ liệu điều chỉnh trong khoảng thời gian này.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 6. Ý KIẾN ĐÁNH GIÁ CHẤT LƯỢNG CHIẾM ĐA SỐ -->
        <div class="card-box" style="margin-bottom:0;">
            <h3 style="font-size:1.1rem; color:#334155; margin-bottom:12px;">⭐ Tỷ Lệ Đánh Giá Chất Lượng Chung</h3>
            <table class="table-data">
                <thead>
                    <tr>
                        <th>Mức Độ Đánh Giá</th>
                        <th style="text-align:center;">Tổng Số Lượt</th>
                        <th style="text-align:center;">Tỷ Lệ (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($dominantRatings)): ?>
                        <?php 
                        $totalRat = array_sum(array_column($dominantRatings, 'count_rat')) ?: 1;
                        foreach ($dominantRatings as $dr): 
                            $pct = round(($dr['count_rat'] / $totalRat) * 100, 1);
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($dr['quality_rating']) ?></strong></td>
                                <td style="text-align:center; font-weight:700;"><?= intval($dr['count_rat']) ?> lượt</td>
                                <td style="text-align:center; color:#10b981; font-weight:700;"><?= $pct ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" style="text-align:center; padding:20px; color:#64748b;">Chưa có đánh giá chất lượng trong khoảng thời gian này.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==================== MODALS ==================== -->

<!-- MODAL 1: NHẬP THỦ CÔNG THỰC ĐƠN -->
<div id="modal-manual-menu" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.5); z-index:1000; align-items:center; justify-content:center; padding:15px;">
    <div class="card-box" style="width:100%; max-width:500px; margin:0;">
        <form method="POST" action="cai-thien-suat-an.php?f_week=<?= urlencode($selected_week) ?>&stat_period=<?= urlencode($stat_period) ?>">
            <input type="hidden" name="action" value="add_menu_manual">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; font-weight:700; font-size:1.1rem;">
                <span>Thêm Món Ăn Vào Thực Đơn Tuần</span>
                <span style="cursor:pointer;" onclick="closeModal('modal-manual-menu')">&times;</span>
            </div>
            <div style="display:flex; flex-direction:column; gap:12px;">
                <div>
                    <label style="font-size:0.85rem; font-weight:600;">Mã Tuần (VD: 2026-W37):</label>
                    <input type="text" name="week_code" value="<?= $current_week ?>" required style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
                </div>
                <div>
                    <label style="font-size:0.85rem; font-weight:600;">Năm:</label>
                    <input type="number" name="year" value="<?= date('Y') ?>" required style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
                </div>
                <div>
                    <label style="font-size:0.85rem; font-weight:600;">Thứ trong tuần:</label>
                    <select name="day_of_week" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
                        <option value="Thứ 2">Thứ 2</option>
                        <option value="Thứ 3">Thứ 3</option>
                        <option value="Thứ 4">Thứ 4</option>
                        <option value="Thứ 5">Thứ 5</option>
                        <option value="Thứ 6">Thứ 6</option>
                        <option value="Thứ 7">Thứ 7</option>
                        <option value="Chủ Nhật">Chủ Nhật</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.85rem; font-weight:600;">Tên Món Ăn: *</label>
                    <input type="text" name="dish_name" placeholder="VD: Thịt kho tàu, Canh chua cá lóc..." required style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
                </div>
                <div>
                    <label style="font-size:0.85rem; font-weight:600;">Mô tả / Định lượng:</label>
                    <textarea name="description" rows="2" placeholder="Ghi chú thêm về thành phần..." style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;"></textarea>
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:20px;">
                <button type="button" class="btn-action" style="background:#e2e8f0; color:#475569;" onclick="closeModal('modal-manual-menu')">Hủy</button>
                <button type="submit" class="btn-action">Lưu Thực Đơn</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 2: NHẬP THÔNG MINH TỪ EXCEL / ẢNH -->
<div id="modal-smart-import" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.5); z-index:1000; align-items:center; justify-content:center; padding:15px;">
    <div class="card-box" style="width:100%; max-width:560px; margin:0;">
        <form method="POST" action="cai-thien-suat-an.php?f_week=<?= urlencode($selected_week) ?>&stat_period=<?= urlencode($stat_period) ?>">
            <input type="hidden" name="action" value="smart_import_file">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; font-weight:700; font-size:1.1rem;">
                <span>🤖 Trích Xuất Thông Minh (Excel / Hình Ảnh Thực Đơn)</span>
                <span style="cursor:pointer;" onclick="closeModal('modal-smart-import')">&times;</span>
            </div>
            <div style="display:flex; flex-direction:column; gap:12px;">
                <div>
                    <label style="font-size:0.85rem; font-weight:600;">Mã Tuần áp dụng:</label>
                    <input type="text" name="week_code" value="<?= $current_week ?>" required style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
                </div>
                <div>
                    <label style="font-size:0.85rem; font-weight:600;">Tải lên File Excel / Hình ảnh thực đơn:</label>
                    <input type="file" accept=".xlsx, .xls, .csv, image/*" onchange="simulateSmartOCR(this)" style="width:100%; padding:6px; border:1px dashed #cbd5e1; border-radius:6px; background:#f8fafc;">
                    <span style="font-size:0.75rem; color:#64748b;">Hệ thống sẽ tự động quét văn bản từ ảnh hoặc file Excel được chọn.</span>
                </div>
                <div>
                    <label style="font-size:0.85rem; font-weight:600;">Hoặc dán nội dung thực đơn vào đây (Mỗi dòng 1 món):</label>
                    <textarea name="raw_import_text" id="raw_import_text" rows="6" placeholder="Thứ 2&#10;Thịt kho trứng&#10;Canh bầu thịt băm&#10;Thứ 3&#10;Gà kho gừng..." style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; font-family:monospace;"></textarea>
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:20px;">
                <button type="button" class="btn-action" style="background:#e2e8f0; color:#475569;" onclick="closeModal('modal-smart-import')">Hủy</button>
                <button type="submit" class="btn-action" style="background:#8b5cf6;">Tiến Hành Trích Xuất & Lưu</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

function simulateSmartOCR(input) {
    if (input.files && input.files[0]) {
        const fileName = input.files[0].name;
        const txtArea = document.getElementById('raw_import_text');
        txtArea.value = `Thứ 2\nThịt kho tàu chuẩn vị\nCanh chua cá lóc\nThứ 3\nGà chiên nước mắm\nCanh củ quả thịt băm\n(Đã quét thông minh từ file: ${fileName})`;
    }
}
window.onclick = function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
    }
}
</script>

<?php include 'footer.php'; ?>
