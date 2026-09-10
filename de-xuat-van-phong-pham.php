<?php
// de-xuat-van-phong-pham.php
require_once 'db_connect.php';

$message = '';
$error = '';
$today = date('Y-m-d');
$limit = 10; // Số dòng trên mỗi trang lịch sử

// ==================== CÁC PHÒNG BAN MẶC ĐỊNH ====================
if (!isset($DEFAULT_DEPARTMENTS) || !is_array($DEFAULT_DEPARTMENTS)) {
    $DEFAULT_DEPARTMENTS = [
        'Ban Giám Đốc', 'Phòng Hành Chính - Nhân Sự', 'Phòng Kế Toán - Tài Chính',
        'Phòng Kỹ Thuật - IT', 'Phòng Kinh Doanh - Marketing', 'Phòng Quản Lý Chất Lượng (QA/QC)',
        'Bộ Phận Sản Xuất / Kho Vận', 'Bộ Phận Bếp & Dịch Vụ'
    ];
}

// ==================== HÀM TỰ ĐỘNG SINH MÃ ĐỀ XUẤT ====================
function generateUniqueVppProposalCode($pdo, $table, $timeStr) {
    $ts = strtotime($timeStr) ?: time();
    $baseCode = date('dmYHis', $ts);

    $sql = "SELECT request_code FROM `{$table}` WHERE request_code = :b";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':b' => $baseCode]);
    if (!$stmt->fetchColumn()) {
        return $baseCode;
    }
    return $baseCode . '_' . rand(10, 99);
}

// ==================== XỬ LÝ GỬI ĐỀ XUẤT ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_proposal') {
    $receiver_name = trim($_POST['receiver_name'] ?? '');
    $department    = trim($_POST['department'] ?? '');
    $employee_id   = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
    $delivery_loc  = trim($_POST['delivery_location'] ?? '');
    $items_input   = $_POST['items'] ?? []; // Mảng [item_id => quantity]

    $selected_items = [];
    if (is_array($items_input)) {
        foreach ($items_input as $itemId => $qty) {
            $q = intval($qty);
            if ($q > 0) {
                $selected_items[intval($itemId)] = $q;
            }
        }
    }

    if (empty($receiver_name) || empty($department)) {
        $error = "Vui lòng nhập đầy đủ thông tin: Họ tên/Mã số người nhận và Phòng ban!";
    } elseif (empty($selected_items)) {
        $error = "Vui lòng chọn ít nhất 1 mặt hàng văn phòng phẩm với số lượng lớn hơn 0!";
    } else {
        try {
            $pdo->beginTransaction();
            $current_time = date('Y-m-d H:i:s');
            // DÙNG CHUNG 1 MÃ YÊU CẦU DUY NHẤT CHO CẢ ĐỢT GỬI
            $common_code = generateUniqueVppProposalCode($pdo, 'vpp_proposals', $current_time);
            $countSuccess = 0;

            $stmtInsert = $pdo->prepare("
                INSERT INTO vpp_proposals 
                    (request_code, employee_id, receiver_name, department, item_id, quantity, delivery_location, status, created_at)
                VALUES 
                    (:code, :emp_id, :rec, :dept, :it_id, :qty, :loc, 'Chờ duyệt', :created_at)
            ");

            foreach ($selected_items as $itId => $qty) {
                $stmtCheck = $pdo->prepare("SELECT id FROM vpp_items WHERE id = :id");
                $stmtCheck->execute([':id' => $itId]);
                if (!$stmtCheck->fetchColumn()) continue;

                $stmtInsert->execute([
                    ':code'       => $common_code,
                    ':emp_id'     => $employee_id,
                    ':rec'        => $receiver_name,
                    ':dept'       => $department,
                    ':it_id'      => $itId,
                    ':qty'        => $qty,
                    ':loc'        => $delivery_loc,
                    ':created_at' => $current_time
                ]);
                $countSuccess++;
            }

            $pdo->commit();
            $message = "🎉 Gửi đề xuất thành công! Tổng cộng <strong>{$countSuccess}</strong> mặt hàng đã được gộp trong yêu cầu: <strong>{$common_code}</strong>";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Lỗi khi gửi đề xuất: " . $e->getMessage();
        }
    }
}

// ==================== TRUY VẤN TẤT CẢ VPP CHO MODAL ====================
$vppItems = [];
try {
    $vppItems = $pdo->query("SELECT id, item_code, item_name, specification, unit, stock_qty FROM vpp_items ORDER BY item_name ASC")->fetchAll();
} catch (PDOException $e) {}

// ==================== BỘ LỌC & TRUY VẤN LỊCH SỬ ĐỀ XUẤT ====================
$f_search = trim($_GET['f_search'] ?? '');
$f_dept   = trim($_GET['f_dept'] ?? '');
$f_status = trim($_GET['f_status'] ?? '');
$page     = max(1, intval($_GET['page'] ?? 1));

$where = ["1=1"];
$params = [];

if (!empty($f_search)) {
    $where[] = "(p.request_code LIKE :s1 OR p.receiver_name LIKE :s2 OR i.item_name LIKE :s3)";
    $params[':s1'] = "%$f_search%";
    $params[':s2'] = "%$f_search%";
    $params[':s3'] = "%$f_search%";
}
if (!empty($f_dept)) {
    $where[] = "p.department = :dept";
    $params[':dept'] = $f_dept;
}
if (!empty($f_status)) {
    $where[] = "p.status = :st";
    $params[':st'] = $f_status;
}

$countStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT p.request_code) 
    FROM vpp_proposals p 
    JOIN vpp_items i ON p.item_id = i.id 
    WHERE " . implode(' AND ', $where)
);
$countStmt->execute($params);
$total_rows = intval($countStmt->fetchColumn());
$total_pages = ceil($total_rows / $limit) ?: 1;
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $limit;

// Gom nhóm các mặt hàng theo cùng 1 request_code
$sqlList = "
    SELECT 
        p.request_code,
        p.receiver_name,
        p.department,
        p.employee_id,
        p.status,
        p.created_at,
        p.reject_reason,
        COUNT(p.id) AS total_items,
        GROUP_CONCAT(CONCAT(i.item_name, ' (SL: ', p.quantity, ' ', i.unit, ')') SEPARATOR '<br>') AS items_summary
    FROM vpp_proposals p 
    JOIN vpp_items i ON p.item_id = i.id 
    WHERE " . implode(' AND ', $where) . " 
    GROUP BY p.request_code, p.receiver_name, p.department, p.employee_id, p.status, p.created_at, p.reject_reason
    ORDER BY MAX(p.id) DESC 
    LIMIT " . intval($limit) . " OFFSET " . intval($offset) . "
";
$stmtList = $pdo->prepare($sqlList);
$stmtList->execute($params);
$proposalsList = $stmtList->fetchAll();

include 'header.php';
?>

<style>
    .prop-form-wrapper { max-width: 1260px; margin: 25px auto; padding: 0 16px; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    
    .history-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 22px 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 30px; }
    .history-title { font-size: 1.22rem; font-weight: 800; color: #1e293b; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }

    .form-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 25px 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 30px; }
    .form-header-title { font-size: 1.35rem; font-weight: 800; color: #0f172a; margin-bottom: 6px; display: flex; align-items: center; gap: 8px; }
    .form-subtitle { font-size: 0.88rem; color: #64748b; margin-bottom: 22px; }

    .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
    @media (max-width: 768px) {
        .grid-3 { grid-template-columns: 1fr; gap: 12px; }
        .form-card, .history-card { padding: 18px; }
    }

    .form-group { margin-bottom: 14px; }
    .form-group label { font-size: 0.85rem; font-weight: 700; color: #334155; margin-bottom: 6px; display: block; }
    .form-group input, .form-group select, .form-group textarea {
        width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; outline: none; background: #fff; box-sizing: border-box; transition: all 0.2s ease;
    }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #8b5cf6; box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15); }

    .btn-open-picker { background: #f5f3ff; color: #7c3aed; border: 1.5px dashed #c4b5fd; padding: 10px 18px; border-radius: 8px; font-weight: 700; font-size: 0.92rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
    .btn-open-picker:hover { background: #ede9fe; border-color: #8b5cf6; }

    .selected-items-table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 0.88rem; }
    .selected-items-table th, .selected-items-table td { padding: 9px 12px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
    .selected-items-table th { background: #f8fafc; color: #475569; font-weight: 700; text-align: left; }

    .btn-submit-prop {
        background: #8b5cf6; color: #ffffff; border: none; padding: 11px 26px; border-radius: 8px; font-weight: 700; font-size: 0.95rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s ease;
    }
    .btn-submit-prop:hover { background: #7c3aed; box-shadow: 0 4px 12px rgba(124, 58, 237, 0.25); }

    .alert-box { padding: 12px 16px; border-radius: 8px; font-size: 0.9rem; margin-bottom: 18px; line-height: 1.5; }
    .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

    .filter-bar { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }
    .filter-bar input, .filter-bar select { padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.86rem; outline: none; background: #fff; }

    .table-prop-history { width: 100%; border-collapse: collapse; font-size: 0.88rem; min-width: 850px; }
    .table-prop-history th, .table-prop-history td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; text-align: left; }
    .table-prop-history th { background: #f8fafc; color: #475569; font-weight: 700; }

    .badge-status { padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: 700; display: inline-block; }
    .badge-wait { background: #fef3c7; color: #d97706; border: 1px solid #fde68a; }
    .badge-appr { background: #dbeafe; color: #1d4ed8; border: 1px solid #bfdbfe; }
    .badge-given { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .badge-rej { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

    .pagination-wrapper { display: flex; justify-content: center; align-items: center; gap: 6px; margin-top: 16px; }
    .page-link { display: inline-flex; align-items: center; justify-content: center; min-width: 32px; height: 32px; padding: 0 8px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; color: #334155; font-size: 0.85rem; font-weight: 600; text-decoration: none; cursor: pointer; }
    .page-link.active { background-color: #8b5cf6; color: #fff; border-color: #8b5cf6; }
    .page-link.disabled { color: #cbd5e1; border-color: #e2e8f0; cursor: not-allowed; }

    /* Modal Chọn VPP (Chống nhảy màn hình) */
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 9999; align-items: center; justify-content: center; padding: 15px; }
    .modal-box { background: #fff; width: 100%; max-width: 860px; height: 80vh; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); display: flex; flex-direction: column; overflow: hidden; }
    .modal-header { padding: 14px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 800; font-size: 1.05rem; display: flex; justify-content: space-between; align-items: center; color: #1e293b; }
    .modal-body { padding: 14px 20px; overflow-y: auto; flex: 1; }
    .modal-footer { padding: 12px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }

    .modal-table { width: 100%; border-collapse: collapse; font-size: 0.87rem; }
    .modal-table th, .modal-table td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .modal-table th { background: #f8fafc; color: #334155; position: sticky; top: 0; z-index: 10; font-weight: 700; border-bottom: 2px solid #e2e8f0; }
    .modal-table tr:hover { background: #f8fafc; }
</style>

<div class="prop-form-wrapper">
    <!-- THÔNG BÁO CHUNG -->
    <?php if ($message): ?><div class="alert-box alert-success"><?= $message ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert-box alert-danger"><?= $error ?></div><?php endif; ?>

    <!-- KHỐI 1: BẢNG THEO DÕI TIẾN ĐỘ ĐỀ XUẤT -->
    <div class="history-card">
        <div class="history-title">
            <span>📋 Tiến Độ Các Đợt Đề Xuất Đã Gửi (Tổng: <?= $total_rows ?> yêu cầu)</span>
            <span style="font-size: 0.82rem; font-weight: normal; color: #64748b;">Dữ liệu tự động cập nhật khi bộ phận kho xét duyệt</span>
        </div>

        <form method="GET" action="de-xuat-van-phong-pham.php" class="filter-bar">
            <input type="text" name="f_search" value="<?= htmlspecialchars($f_search) ?>" placeholder="🔍 Tìm mã yêu cầu, họ tên, mặt hàng..." style="width: 240px;">

            <select name="f_dept">
                <option value="">-- Tất cả phòng ban --</option>
                <?php foreach ($DEFAULT_DEPARTMENTS as $d): ?>
                    <option value="<?= htmlspecialchars($d) ?>" <?= $f_dept === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="f_status">
                <option value="">-- Tất cả trạng thái --</option>
                <option value="Chờ duyệt" <?= $f_status === 'Chờ duyệt' ? 'selected' : '' ?>>Chờ duyệt</option>
                <option value="Đã duyệt" <?= $f_status === 'Đã duyệt' ? 'selected' : '' ?>>Đã duyệt</option>
                <option value="Đã cấp phát" <?= $f_status === 'Đã cấp phát' ? 'selected' : '' ?>>Đã cấp phát</option>
                <option value="Từ chối" <?= $f_status === 'Từ chối' ? 'selected' : '' ?>>Từ chối</option>
            </select>

            <button type="submit" style="background:#8b5cf6; color:#fff; border:none; padding:7px 14px; border-radius:6px; font-weight:600; cursor:pointer;">Lọc</button>
            <?php if ($f_search || $f_dept || $f_status): ?>
                <a href="de-xuat-van-phong-pham.php" style="font-size:0.85rem; color:#64748b; text-decoration:none;">Xóa lọc</a>
            <?php endif; ?>
        </form>

        <div style="overflow-x: auto;">
            <table class="table-prop-history">
                <thead>
                    <tr>
                        <th style="width: 45px; text-align: center;">STT</th>
                        <th style="width: 135px;">Mã Yêu Cầu</th>
                        <th style="width: 140px;">Thời Gian Gửi</th>
                        <th>Người Nhận / Mã NV</th>
                        <th>Phòng Ban</th>
                        <th>Danh Sách Mặt Hàng Đề Xuất</th>
                        <th style="width: 120px; text-align: center;">Trạng Thái</th>
                        <th>Lý Do / Ghi Chú</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($proposalsList)): ?>
                        <?php 
                        $stt = $offset + 1;
                        foreach ($proposalsList as $p): 
                            $badgeClass = 'badge-wait';
                            if ($p['status'] === 'Đã duyệt') $badgeClass = 'badge-appr';
                            elseif ($p['status'] === 'Đã cấp phát') $badgeClass = 'badge-given';
                            elseif ($p['status'] === 'Từ chối') $badgeClass = 'badge-rej';
                        ?>
                            <tr>
                                <td style="text-align: center;"><b><?= $stt++ ?></b></td>
                                <td>
                                    <span style="background:#f1f5f9; color:#475569; padding:3px 6px; border-radius:4px; font-weight:700; font-size:0.8rem;">
                                        <?= htmlspecialchars($p['request_code']) ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($p['receiver_name']) ?></strong>
                                    <?= $p['employee_id'] ? ("<span style='font-size:0.75rem; color:#64748b;'><br>Mã NV: #{$p['employee_id']}</span>") : '' ?>
                                </td>
                                <td><?= htmlspecialchars($p['department']) ?></td>
                                <td style="line-height: 1.6;">
                                    <?= $p['items_summary'] ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge-status <?= $badgeClass ?>"><?= htmlspecialchars($p['status']) ?></span>
                                </td>
                                <td style="font-size: 0.83rem;">
                                    <?php if ($p['status'] === 'Từ chối' && !empty($p['reject_reason'])): ?>
                                        <span style="color:#dc2626;">✗ <?= htmlspecialchars($p['reject_reason']) ?></span>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align: center; padding: 25px; color: #64748b;">Không tìm thấy đề xuất văn phòng phẩm nào.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination-wrapper">
                <?php 
                $queryParams = $_GET;
                if ($page > 1) {
                    $queryParams['page'] = $page - 1;
                    echo '<a href="?' . http_build_query($queryParams) . '" class="page-link">&laquo;</a>';
                }
                for ($i = 1; $i <= $total_pages; $i++) {
                    $queryParams['page'] = $i;
                    $active = ($i == $page) ? 'active' : '';
                    echo '<a href="?' . http_build_query($queryParams) . '" class="page-link ' . $active . '">' . $i . '</a>';
                }
                if ($page < $total_pages) {
                    $queryParams['page'] = $page + 1;
                    echo '<a href="?' . http_build_query($queryParams) . '" class="page-link">&raquo;</a>';
                }
                ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- KHỐI 2: FORM ĐỀ XUẤT CẤP VĂN PHÒNG PHẨM -->
    <div class="form-card">
        <div class="form-header-title">
            📝 Gửi Yêu Cầu Cấp Phát Văn Phòng Phẩm Mới
        </div>
        <p class="form-subtitle">
            Họ tên người nhận có thể nhập họ tên hoặc mã số nhân viên. Bấm nút chọn mặt hàng để tick chọn cùng lúc nhiều món VPP.
        </p>

        <form method="POST" action="de-xuat-van-phong-pham.php" id="formProposal" onsubmit="return validateBeforeSubmit();">
            <input type="hidden" name="action" value="submit_proposal">

            <div class="grid-3">
                <div class="form-group">
                    <label>Họ tên hoặc Mã số nhân viên nhận: *</label>
                    <input type="text" name="receiver_name" placeholder="VD: Nguyễn Văn A hoặc NV-1024" required>
                </div>
                <div class="form-group">
                    <label>Phòng ban / Bộ phận: *</label>
                    <select name="department" required>
                        <option value="">-- Chọn phòng ban --</option>
                        <?php foreach ($DEFAULT_DEPARTMENTS as $d): ?>
                            <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Mã nhân viên (phụ, nếu có):</label>
                    <input type="number" name="employee_id" placeholder="VD: 1024">
                </div>
            </div>

            <!-- KHU VỰC CHỌN MẶT HÀNG TỪ MODAL -->
            <div style="margin: 18px 0; background: #faf5ff; border: 1px solid #e9d5ff; border-radius: 8px; padding: 16px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div>
                        <strong style="color:#6b21a8; font-size:0.95rem;">📦 Danh sách mặt hàng VPP đề xuất:</strong>
                        <p style="margin:2px 0 0; font-size:0.8rem; color:#7e22ce;">Tick chọn các mặt hàng và nhập số lượng, tất cả sẽ được gửi chung trong 1 đợt yêu cầu.</p>
                    </div>
                    <button type="button" class="btn-open-picker" onclick="openPickerModal()">
                        🔍 Tìm & Chọn Mặt Hàng VPP
                    </button>
                </div>

                <div id="selected_table_wrapper" style="margin-top: 14px; overflow-x: auto; display: none;">
                    <table class="selected-items-table">
                        <thead>
                            <tr>
                                <th style="width: 50px; text-align: center;">STT</th>
                                <th style="width: 120px;">Mã VPP</th>
                                <th>Tên Mặt Hàng</th>
                                <th>Quy Cách</th>
                                <th style="width: 80px; text-align: center;">ĐVT</th>
                                <th style="width: 130px; text-align: center;">Số Lượng Đề Xuất</th>
                                <th style="width: 70px; text-align: center;">Xóa</th>
                            </tr>
                        </thead>
                        <tbody id="selected_items_body">
                            <!-- JS render các món đã chọn vào đây -->
                        </tbody>
                    </table>
                </div>
                <div id="empty_items_notice" style="text-align: center; padding: 18px; color: #9333ea; font-size: 0.88rem; font-style: italic;">
                    Chưa có mặt hàng nào được chọn. Vui lòng bấm nút <b>"Tìm & Chọn Mặt Hàng VPP"</b> ở trên.
                </div>
            </div>

            <div class="form-group">
                <label>Vị trí nhận / Ghi chú bàn làm việc cụ thể:</label>
                <input type="text" name="delivery_location" placeholder="VD: Bàn số 05 dãy IT, Lầu 2 toà nhà chính...">
            </div>

            <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="submit" class="btn-submit-prop">
                    🚀 Gửi Yêu Cầu Đề Xuất
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL TÌM KIẾM, PHÂN TRANG (20/TRANG) & TICK CHỌN VPP ==================== -->
<div id="modal-picker" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <span>📦 Chọn Danh Mục Văn Phòng Phẩm & Số Lượng</span>
            <span style="cursor: pointer; font-size: 1.3rem;" onclick="closePickerModal()">&times;</span>
        </div>
        <div style="padding: 12px 20px; background: #fff; border-bottom: 1px solid #e2e8f0;">
            <input type="text" id="modal_search_input" oninput="onSearchModalChange()" placeholder="🔍 Gõ tên hoặc mã VPP để tìm nhanh..." style="width: 100%; padding: 9px 12px; border: 1.5px solid #8b5cf6; border-radius: 6px; outline: none; font-size: 0.9rem; box-sizing: border-box;">
        </div>
        
        <div class="modal-body" id="modal_table_scroll_container">
            <table class="modal-table">
                <thead>
                    <tr>
                        <th style="width: 45px; text-align: center;">STT</th>
                        <th style="width: 45px; text-align: center;">Chọn</th>
                        <th style="width: 110px;">Mã VPP</th>
                        <th>Tên Mặt Hàng</th>
                        <th>Quy Cách</th>
                        <th style="width: 70px; text-align: center;">ĐVT</th>
                        <th style="width: 90px; text-align: center;">Tồn Kho</th>
                        <th style="width: 120px; text-align: center;">Số Lượng</th>
                    </tr>
                </thead>
                <tbody id="modal_items_tbody">
                    <!-- JavaScript render động theo phân trang 20 dòng/trang -->
                </tbody>
            </table>
        </div>

        <div class="modal-footer">
            <div id="modal_pagination_controls" class="pagination-wrapper" style="margin: 0;"></div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <span style="font-size: 0.86rem; color: #64748b; margin-right: 8px;">Đã chọn: <strong id="modal_checked_count" style="color: #7c3aed;">0</strong></span>
                <button type="button" onclick="closePickerModal()" style="background:#e2e8f0; color:#475569; border:none; padding:8px 16px; border-radius:6px; font-weight:600; cursor:pointer;">Hủy</button>
                <button type="button" onclick="applySelectedItems()" style="background:#8b5cf6; color:#fff; border:none; padding:8px 22px; border-radius:6px; font-weight:bold; cursor:pointer;">✓ Xác Nhận Chọn (OK)</button>
            </div>
        </div>
    </div>
</div>

<script>
// Nạp toàn bộ danh mục VPP vào biến JS
const ALL_VPP_ITEMS = <?= json_encode($vppItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

// Bộ nhớ đệm tạm thời cho các mặt hàng đã chọn { itemId: { id, code, name, spec, unit, qty } }
let selectedItems = {};
let filteredItems = [...ALL_VPP_ITEMS];
let modalCurrentPage = 1;
const MODAL_PAGE_SIZE = 20;

function openPickerModal() {
    document.getElementById('modal-picker').style.display = 'flex';
    document.getElementById('modal_search_input').value = '';
    filteredItems = [...ALL_VPP_ITEMS];
    modalCurrentPage = 1;
    renderModalTable();
}

function closePickerModal() {
    document.getElementById('modal-picker').style.display = 'none';
}

function onSearchModalChange() {
    const kw = document.getElementById('modal_search_input').value.toLowerCase().trim();
    filteredItems = ALL_VPP_ITEMS.filter(it => {
        const name = (it.item_name || '').toLowerCase();
        const code = (it.item_code || '').toLowerCase();
        const spec = (it.specification || '').toLowerCase();
        return name.includes(kw) || code.includes(kw) || spec.includes(kw);
    });
    modalCurrentPage = 1;
    renderModalTable();
}

function renderModalTable() {
    const tbody = document.getElementById('modal_items_tbody');
    tbody.innerHTML = '';

    const totalRows = filteredItems.length;
    const totalPages = Math.ceil(totalRows / MODAL_PAGE_SIZE) || 1;
    if (modalCurrentPage > totalPages) modalCurrentPage = totalPages;

    const startIdx = (modalCurrentPage - 1) * MODAL_PAGE_SIZE;
    const endIdx = Math.min(startIdx + MODAL_PAGE_SIZE, totalRows);
    const pageItems = filteredItems.slice(startIdx, endIdx);

    if (pageItems.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:25px; color:#64748b;">Không tìm thấy mặt hàng nào phù hợp.</td></tr>';
    } else {
        pageItems.forEach((it, idx) => {
            const stt = startIdx + idx + 1;
            const isChecked = !!selectedItems[it.id];
            const currentQty = isChecked ? selectedItems[it.id].qty : 1;
            const stockQty = parseInt(it.stock_qty) || 0;
            const stockColor = stockQty > 0 ? '#15803d' : '#dc2626';

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="text-align: center; color: #64748b; font-weight: bold;">${stt}</td>
                <td style="text-align: center;">
                    <input type="checkbox" class="chk-item-picker" data-id="${it.id}" 
                           ${isChecked ? 'checked' : ''} 
                           onchange="onPickerCheckboxChange(this, ${it.id})" 
                           style="width: 17px; height: 17px; cursor: pointer; margin: 0;">
                </td>
                <td><span style="background:#f1f5f9; padding:2px 5px; border-radius:4px; font-weight:600; font-size:0.8rem;">${it.item_code || '—'}</span></td>
                <td><b>${it.item_name}</b></td>
                <td style="color:#64748b; font-size:0.82rem;">${it.specification || '—'}</td>
                <td style="text-align: center;">${it.unit || 'Cái'}</td>
                <td style="text-align: center; font-weight: bold; color: ${stockColor};">${stockQty}</td>
                <td style="text-align: center;">
                    <input type="number" id="qty_modal_${it.id}" min="1" value="${currentQty}" 
                           ${isChecked ? '' : 'disabled'}
                           onchange="onPickerQtyChange(${it.id}, this.value)"
                           style="width: 75px; padding: 4px 6px; border: 1px solid #cbd5e1; border-radius: 4px; text-align: center; font-weight: bold; outline: none;">
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    renderModalPagination(totalPages);
    updateCheckedCount();
}

function renderModalPagination(totalPages) {
    const wrapper = document.getElementById('modal_pagination_controls');
    wrapper.innerHTML = '';
    if (totalPages <= 1) return;

    if (modalCurrentPage > 1) {
        wrapper.innerHTML += `<button type="button" class="page-link" onclick="goToModalPage(${modalCurrentPage - 1})">&laquo;</button>`;
    } else {
        wrapper.innerHTML += `<span class="page-link disabled">&laquo;</span>`;
    }

    const start = Math.max(1, modalCurrentPage - 1);
    const end = Math.min(totalPages, modalCurrentPage + 1);

    for (let i = start; i <= end; i++) {
        const active = (i === modalCurrentPage) ? 'active' : '';
        wrapper.innerHTML += `<button type="button" class="page-link ${active}" onclick="goToModalPage(${i})">${i}</button>`;
    }

    if (modalCurrentPage < totalPages) {
        wrapper.innerHTML += `<button type="button" class="page-link" onclick="goToModalPage(${modalCurrentPage + 1})">&raquo;</button>`;
    } else {
        wrapper.innerHTML += `<span class="page-link disabled">&raquo;</span>`;
    }
}

function goToModalPage(p) {
    modalCurrentPage = p;
    renderModalTable();
}

// Tick chọn không làm nhảy màn hình
function onPickerCheckboxChange(chk, itemId) {
    const qtyInp = document.getElementById('qty_modal_' + itemId);
    const itemData = ALL_VPP_ITEMS.find(x => parseInt(x.id) === parseInt(itemId));

    if (chk.checked) {
        if (qtyInp) qtyInp.disabled = false;
        const q = qtyInp ? (parseInt(qtyInp.value) || 1) : 1;
        selectedItems[itemId] = {
            id: itemData.id,
            code: itemData.item_code,
            name: itemData.item_name,
            spec: itemData.specification,
            unit: itemData.unit,
            qty: q
        };
    } else {
        if (qtyInp) qtyInp.disabled = true;
        delete selectedItems[itemId];
    }
    updateCheckedCount();
}

function onPickerQtyChange(itemId, val) {
    const q = parseInt(val) || 1;
    if (selectedItems[itemId]) {
        selectedItems[itemId].qty = q;
    }
}

function updateCheckedCount() {
    document.getElementById('modal_checked_count').innerText = Object.keys(selectedItems).length;
}

// Bấm Xác Nhận (OK)
function applySelectedItems() {
    renderSelectedTable();
    closePickerModal();
}

function renderSelectedTable() {
    const tbody = document.getElementById('selected_items_body');
    const tableWrap = document.getElementById('selected_table_wrapper');
    const emptyNotice = document.getElementById('empty_items_notice');
    tbody.innerHTML = '';

    const keys = Object.keys(selectedItems);
    if (keys.length === 0) {
        tableWrap.style.display = 'none';
        emptyNotice.style.display = 'block';
        return;
    }

    tableWrap.style.display = 'block';
    emptyNotice.style.display = 'none';

    let stt = 1;
    keys.forEach(id => {
        const item = selectedItems[id];
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td style="text-align:center; color:#64748b; font-weight:bold;">${stt++}</td>
            <td><span style="background:#f1f5f9; padding:2px 5px; border-radius:4px; font-weight:600; font-size:0.8rem;">${item.code || '—'}</span></td>
            <td><b>${item.name}</b></td>
            <td style="color:#64748b; font-size:0.82rem;">${item.spec || '—'}</td>
            <td style="text-align:center;">${item.unit}</td>
            <td style="text-align:center;">
                <input type="number" name="items[${item.id}]" value="${item.qty}" min="1" required 
                       onchange="updateFormQty(${item.id}, this.value)"
                       style="width: 80px; padding: 4px 6px; border: 1px solid #cbd5e1; border-radius: 4px; text-align: center; font-weight: bold; color: #0284c7;">
            </td>
            <td style="text-align:center;">
                <button type="button" onclick="removeItem(${item.id})" style="background:#fee2e2; color:#dc2626; border:1px solid #fecaca; padding:3px 8px; border-radius:4px; cursor:pointer; font-weight:bold;">✕</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function updateFormQty(id, val) {
    const q = parseInt(val) || 1;
    if (selectedItems[id]) selectedItems[id].qty = q;
}

function removeItem(id) {
    delete selectedItems[id];
    renderSelectedTable();
}

function validateBeforeSubmit() {
    if (Object.keys(selectedItems).length === 0) {
        alert("Vui lòng bấm 'Tìm & Chọn Mặt Hàng VPP' để chọn ít nhất 1 mặt hàng trước khi gửi!");
        return false;
    }
    return true;
}

window.onclick = function(e) {
    if (e.target.id === 'modal-picker') {
        closePickerModal();
    }
}
</script>

<?php include 'footer.php'; ?>
