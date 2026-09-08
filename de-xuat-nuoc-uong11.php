<?php
// de-xuat-nuoc-uong.php
// Trang công cộng - KHÔNG YÊU CẦU ĐĂNG NHẬP
require_once 'db_connect.php';

$message = '';
$error = '';

// Tự động kiểm tra và tạo bảng nếu chưa có
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `water_proposals` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `employee_id` INT DEFAULT NULL,
            `receiver_name` VARCHAR(150) NOT NULL,
            `department` VARCHAR(100) NOT NULL,
            `item_id` INT NOT NULL,
            `quantity` INT NOT NULL DEFAULT 1,
            `return_bottle_count` INT NOT NULL DEFAULT 0,
            `delivery_location` TEXT DEFAULT NULL,
            `status` VARCHAR(50) DEFAULT 'Chờ cấp phát',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`item_id`),
            INDEX (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (PDOException $e) {}

// Xử lý gửi đề xuất
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_proposal') {
    $receiver_name = trim($_POST['receiver_name'] ?? '');
    $department    = trim($_POST['department'] ?? '');
    $item_id       = intval($_POST['item_id'] ?? 0);
    $quantity      = intval($_POST['quantity'] ?? 1);
    $return_bottles= intval($_POST['return_bottle_count'] ?? 0);
    $delivery_loc  = trim($_POST['delivery_location'] ?? '');
    $employee_id   = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : null;

    if (empty($receiver_name) || empty($department) || $item_id <= 0 || $quantity <= 0) {
        $error = "Vui lòng điền đầy đủ họ tên, phòng ban và chọn loại nước cần đề xuất!";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO water_proposals (employee_id, receiver_name, department, item_id, quantity, return_bottle_count, delivery_location, status)
                VALUES (:emp_id, :rec_name, :dept, :item_id, :qty, :ret_b, :loc, 'Chờ cấp phát')
            ");
            $stmt->execute([
                ':emp_id'   => $employee_id,
                ':rec_name' => $receiver_name,
                ':dept'     => $department,
                ':item_id'  => $item_id,
                ':qty'      => $quantity,
                ':ret_b'    => max(0, $return_bottles),
                ':loc'      => $delivery_loc
            ]);
            $message = "Đã gửi yêu cầu cấp nước uống thành công! Nhân sự sẽ tiếp nhận và cấp phát sau 9h sáng thứ 2 hàng tuần. Trân trọng!";
        } catch (PDOException $e) {
            $error = "Lỗi gửi yêu cầu: " . $e->getMessage();
        }
    }
}

// Lấy danh sách nhân viên để gợi ý tìm kiếm
$employees = [];
try {
    $employees = $pdo->query("SELECT id, fullname, department FROM employees WHERE status = 'Đang làm việc' ORDER BY fullname ASC")->fetchAll();
} catch (PDOException $e) {}

// Lấy danh sách phòng ban
$departments = [
    'Ban Giám Đốc', 'Phòng Hành Chính - Nhân Sự', 'Phòng Kế Toán - Tài Chính', 
    'Phòng Kỹ Thuật - IT', 'Phòng Kinh Doanh - Marketing', 'Phòng Quản Lý Chất Lượng (QA/QC)', 
    'Bộ Phận Sản Xuất / Kho Vận', 'Bộ Phận Bếp & Dịch Vụ'
];
try {
    $dbDepts = $pdo->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != ''")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($dbDepts)) $departments = array_unique(array_merge($departments, $dbDepts));
} catch (PDOException $e) {}

// Lấy danh mục sản phẩm nước uống
$waterItems = [];
try {
    $waterItems = $pdo->query("SELECT id, item_code, item_name, specification, unit, stock_qty FROM water_items ORDER BY item_code ASC, item_name ASC")->fetchAll();
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phiếu Đề Xuất Cấp Nước Uống</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        body { background-color: #f1f5f9; color: #1e293b; padding: 15px; }
        .proposal-card { max-width: 580px; margin: 20px auto; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); border: 1px solid #cbd5e1; overflow: hidden; }
        .proposal-header { background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #ffffff; padding: 22px 20px; text-align: center; }
        .proposal-header h1 { font-size: 1.45rem; font-weight: 800; margin-bottom: 6px; }
        .proposal-header p { font-size: 0.88rem; opacity: 0.9; }
        .proposal-body { padding: 22px; display: flex; flex-direction: column; gap: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; position: relative; }
        .form-group label { font-size: 0.9rem; font-weight: 700; color: #334155; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; outline: none; background: #fff; transition: border-color 0.2s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #0284c7; box-shadow: 0 0 0 3px rgba(2,132,199,0.15); }
        .suggest-list { position: absolute; top: 100%; left: 0; right: 0; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; max-height: 180px; overflow-y: auto; z-index: 50; display: none; box-shadow: 0 10px 15px rgba(0,0,0,0.1); margin-top: 4px; }
        .suggest-item { padding: 10px 14px; cursor: pointer; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
        .suggest-item:hover { background-color: #f0f9ff; color: #0284c7; }
        .bottle-return-box { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; padding: 14px; display: none; }
        .btn-submit { background: #0284c7; color: #ffffff; border: none; padding: 14px; border-radius: 8px; font-size: 1.05rem; font-weight: 800; cursor: pointer; width: 100%; transition: background 0.2s; margin-top: 6px; }
        .btn-submit:hover { background: #0369a1; }
        .alert { padding: 14px; border-radius: 8px; font-size: 0.92rem; margin-bottom: 15px; font-weight: 600; text-align: center; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .hint-text { font-size: 0.78rem; color: #64748b; margin-top: 2px; }

        /* Modal xác nhận */
        .modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); display: none; justify-content: center; align-items: center; z-index: 1000; padding: 16px; backdrop-filter: blur(2px); }
        .modal-box { background: #ffffff; border-radius: 12px; width: 100%; max-width: 440px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); overflow: hidden; animation: modalFadeIn 0.2s ease-out; }
        @keyframes modalFadeIn { from { opacity: 0; transform: scale(0.96); } to { opacity: 1; transform: scale(1); } }
        .modal-header { padding: 16px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 800; font-size: 1.1rem; color: #0f172a; }
        .modal-content { padding: 20px; font-size: 0.95rem; color: #475569; line-height: 1.6; }
        .modal-content strong { color: #0f172a; }
        .modal-actions { display: flex; gap: 10px; padding: 16px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; }
        .btn-modal { flex: 1; padding: 11px 16px; border-radius: 8px; font-size: 0.95rem; font-weight: 700; border: none; cursor: pointer; transition: 0.2s; }
        .btn-cancel { background: #e2e8f0; color: #334155; }
        .btn-cancel:hover { background: #cbd5e1; }
        .btn-confirm { background: #0284c7; color: #ffffff; }
        .btn-confirm:hover { background: #0369a1; }
    </style>
</head>
<body>

<div class="proposal-card">
    <div class="proposal-header">
        <h1>💧 PHIẾU ĐỀ XUẤT CẤP NƯỚC UỐNG</h1>
        <p>Hệ thống tiếp nhận yêu cầu cấp nước nội bộ công ty</p>
    </div>

    <div class="proposal-body">
        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="de-xuat-nuoc-uong.php" id="formProposal">
            <input type="hidden" name="action" value="submit_proposal">
            <input type="hidden" name="employee_id" id="hidden_emp_id">

            <!-- 1. Người nhận / Mã số -->
            <div class="form-group">
                <label>Người đại diện nhận / Họ và tên: *</label>
                <input type="text" name="receiver_name" id="inp_receiver" placeholder="Nhập tên hoặc gõ mã ID" autocomplete="off" required>
                <div class="suggest-list" id="suggest_box"></div>
                <span class="hint-text">💡 Có thể nhập tự do hoặc gõ mã ID/Tên để hệ thống tự điền thông tin</span>
            </div>

            <!-- 2. Phòng ban / Bộ phận -->
            <div class="form-group">
                <label>Phòng ban / Bộ phận: *</label>
                <select name="department" id="sel_dept" required>
                    <option value="">-- Chọn phòng ban --</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 3. Chọn loại nước -->
            <div class="form-group">
                <label>Chọn loại nước uống: *</label>
                <select name="item_id" id="sel_water_item" onchange="checkWaterSpec(this)" required>
                    <option value="">-- Chọn sản phẩm nước --</option>
                    <?php foreach ($waterItems as $it): ?>
                        <option value="<?= $it['id'] ?>" 
                                data-spec="<?= htmlspecialchars($it['specification']) ?>" 
                                data-unit="<?= htmlspecialchars($it['unit']) ?>">
                            <?= htmlspecialchars($it['item_name']) ?> - <?= htmlspecialchars($it['specification'] ?: 'Bình chuẩn') ?> (Tồn kho: <?= $it['stock_qty'] . ' ' . $it['unit'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 4. Số lượng -->
            <div class="form-group">
                <label>Số lượng cần cấp: *</label>
                <input type="number" name="quantity" min="1" max="100" value="1" required>
            </div>

            <!-- 5. Trả vỏ (tự hiện khi chọn loại 19L hoặc bình đổi) -->
            <div class="bottle-return-box" id="box_bottle_return">
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="color: #c2410c;">↩️ Số lượng vỏ bình gửi trả đợt này (Loại 19L):</label>
                    <input type="number" name="return_bottle_count" min="0" value="1" placeholder="Nhập số vỏ gửi trả lại...">
                    <span class="hint-text" style="color: #ea580c;">Vui lòng ghi chính xác số lượng vỏ bình rỗng gửi trả lại kho</span>
                </div>
            </div>

            <!-- 6. Ghi chú / Vị trí giao -->
            <div class="form-group">
                <label>Ghi chú / Vị trí giao nhận: *</label>
                <textarea name="delivery_location" rows="3" placeholder="Nhập chi tiết vị trí giao hoặc ghi chú thêm" required></textarea>
            </div>

            <button type="submit" class="btn-submit">🚀 GỬI ĐỀ XUẤT NƯỚC UỐNG</button>
        </form>
    </div>
</div>

<!-- Modal xác nhận gửi đề xuất -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal-box">
        <div class="modal-header">Xác nhận gửi đề xuất</div>
        <div class="modal-content">
            Bạn có chắc chắn đồng ý gửi phiếu đề xuất cấp nước uống này không?<br>
            <span style="font-size: 0.85rem; color: #64748b;">(Vui lòng kiểm tra kỹ người nhận và số lượng trước khi xác nhận).</span>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-modal btn-cancel" id="btnCancelSubmit">Hủy</button>
            <button type="button" class="btn-modal btn-confirm" id="btnConfirmSubmit">Đồng ý</button>
        </div>
    </div>
</div>

<script>
// Dữ liệu danh sách nhân sự từ server
const employees = <?= json_encode($employees) ?>;
const inpReceiver = document.getElementById('inp_receiver');
const suggestBox  = document.getElementById('suggest_box');
const selDept     = document.getElementById('sel_dept');
const hiddenEmpId = document.getElementById('hidden_emp_id');

inpReceiver.addEventListener('input', function() {
    const val = this.value.trim().toLowerCase();
    suggestBox.innerHTML = '';
    if (!val) {
        suggestBox.style.display = 'none';
        return;
    }

    const matched = employees.filter(e => 
        e.fullname.toLowerCase().includes(val) || 
        e.id.toString() === val || 
        ('id:' + e.id).includes(val)
    );

    if (matched.length > 0) {
        matched.slice(0, 8).forEach(emp => {
            const div = document.createElement('div');
            div.className = 'suggest-item';
            div.innerHTML = `<strong>#${emp.id} - ${emp.fullname}</strong> (${emp.department || 'Chưa phân bổ'})`;
            div.onclick = function() {
                inpReceiver.value = emp.fullname;
                hiddenEmpId.value = emp.id;
                if (emp.department) {
                    selDept.value = emp.department;
                }
                suggestBox.style.display = 'none';
            };
            suggestBox.appendChild(div);
        });
        suggestBox.style.display = 'block';
    } else {
        suggestBox.style.display = 'none';
        hiddenEmpId.value = '';
    }
});

document.addEventListener('click', function(e) {
    if (!e.target.closest('#inp_receiver') && !e.target.closest('#suggest_box')) {
        suggestBox.style.display = 'none';
    }
});

// Tự động phát hiện loại bình 19L để hiển thị ô trả vỏ
function checkWaterSpec(selectElem) {
    const selectedOpt = selectElem.options[selectElem.selectedIndex];
    const spec = (selectedOpt.getAttribute('data-spec') || '').toLowerCase();
    const name = (selectedOpt.text || '').toLowerCase();
    const boxBottle = document.getElementById('box_bottle_return');

    if (spec.includes('19') || spec.includes('20') || name.includes('19l') || name.includes('19 lít') || name.includes('bình')) {
        boxBottle.style.display = 'block';
    } else {
        boxBottle.style.display = 'none';
    }
}

// Xử lý xác nhận trước khi gửi Form
const formProposal     = document.getElementById('formProposal');
const confirmModal     = document.getElementById('confirmModal');
const btnCancelSubmit  = document.getElementById('btnCancelSubmit');
const btnConfirmSubmit = document.getElementById('btnConfirmSubmit');
let isConfirmed        = false;

formProposal.addEventListener('submit', function(e) {
    if (!isConfirmed) {
        e.preventDefault(); // Ngăn gửi form lần đầu
        if (formProposal.checkValidity()) {
            confirmModal.style.display = 'flex'; // Hiển thị popup xác nhận
        } else {
            formProposal.reportValidity();
        }
    }
});

btnCancelSubmit.addEventListener('click', function() {
    confirmModal.style.display = 'none';
});

btnConfirmSubmit.addEventListener('click', function() {
    isConfirmed = true;
    confirmModal.style.display = 'none';
    formProposal.submit(); // Thực hiện submit form chính thức
});
</script>

</body>
</html>
