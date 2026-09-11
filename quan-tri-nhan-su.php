<?php
// quan-tri-nhan-su.php
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$today = date('Y-m-d');

// Dự phòng trường hợp file db_connect.php chưa khai báo mảng $DEFAULT_DEPARTMENTS
if (!isset($DEFAULT_DEPARTMENTS) || !is_array($DEFAULT_DEPARTMENTS)) {
    $DEFAULT_DEPARTMENTS = [
        'Ban Giám Đốc',
        'Phòng Hành Chính - Nhân Sự',
        'Phòng Kế Toán - Tài Chính',
        'Phòng Kỹ Thuật - IT',
        'Phòng Kinh Doanh - Marketing',
        'Phòng Quản Lý Chất Lượng (QA/QC)',
        'Bộ Phận Sản Xuất / Kho Vận',
        'Bộ Phận Bếp & Dịch Vụ'
    ];
}

// ==================== 1. XỬ LÝ XUẤT EXCEL THEO ĐÚNG BỘ LỌC TÌM KIẾM ====================
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    $date_str = date('d-m-Y_H-i');

    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Pragma: no-cache");
    header("Expires: 0");

    $excel_style = '
    <style>
        .rpt-title { font-family: "Times New Roman", Arial; font-size: 16pt; font-weight: bold; text-align: center; color: #0f172a; height: 35px; vertical-align: middle; }
        .rpt-time { font-family: "Times New Roman", Arial; font-size: 10pt; font-style: italic; text-align: center; color: #475569; }
        .th-cell { font-family: "Times New Roman", Arial; font-size: 11pt; font-weight: bold; background-color: #1e3a8a; color: #ffffff; text-align: center; border: 0.5pt solid #000000; height: 28px; vertical-align: middle; }
        .td-center { font-family: "Times New Roman", Arial; font-size: 10.5pt; text-align: center; border: 0.5pt solid #000000; vertical-align: middle; }
        .td-left { font-family: "Times New Roman", Arial; font-size: 10.5pt; text-align: left; border: 0.5pt solid #000000; vertical-align: middle; }
        .td-bold { font-weight: bold; }
        .tf-total { font-family: "Times New Roman", Arial; font-size: 11pt; font-weight: bold; background-color: #f1f5f9; border: 0.5pt solid #000000; height: 26px; }
    </style>';

    // A. Xuất Nhân viên Trụ sở chính
    if ($export_type === 'employees') {
        header("Content-Disposition: attachment; filename=Bao_Cao_Nhan_Vien_Chinh_{$date_str}.xls");
        echo "\xEF\xBB\xBF";

        $exp_id     = trim($_GET['f1_id'] ?? '');
        $exp_name   = trim($_GET['f1_name'] ?? '');
        $exp_dept   = trim($_GET['f1_dept'] ?? '');
        $exp_pos    = trim($_GET['f1_pos'] ?? '');
        $exp_status = trim($_GET['f1_status'] ?? '');

        $where = ["(branch IS NULL OR branch = '' OR branch = 'Trụ sở chính')"];
        $params = [];
        if ($exp_id !== '')     { $where[] = "id = :id"; $params[':id'] = $exp_id; }
        if ($exp_name !== '')   { $where[] = "fullname LIKE :name"; $params[':name'] = "%$exp_name%"; }
        if ($exp_dept !== '')   { $where[] = "department = :dept"; $params[':dept'] = $exp_dept; }
        if ($exp_pos !== '')    { $where[] = "position LIKE :pos"; $params[':pos'] = "%$exp_pos%"; }
        if ($exp_status !== '') { $where[] = "status = :status"; $params[':status'] = $exp_status; }

        $stmt = $pdo->prepare("SELECT * FROM employees WHERE " . implode(' AND ', $where) . " ORDER BY id ASC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $excel_style . '</head><body>';
        echo '<table border="1">';
        echo '<tr><td colspan="8" class="rpt-title">BÁO CÁO DANH SÁCH NHÂN SỰ TRỤ SỞ CHÍNH</td></tr>';
        echo '<tr><td colspan="8" class="rpt-time">Thời gian kết xuất: ' . date('d/m/Y H:i:s') . '</td></tr>';
        echo '<tr><td colspan="8" style="border:none; height:10px;"></td></tr>';
        echo '<tr>
                <th class="th-cell" width="50">STT</th>
                <th class="th-cell" width="70">Mã ID</th>
                <th class="th-cell" width="200">Họ và Tên</th>
                <th class="th-cell" width="150">Phòng Ban</th>
                <th class="th-cell" width="160">Vị Trí / Chức Vụ</th>
                <th class="th-cell" width="130">Số Điện Thoại</th>
                <th class="th-cell" width="190">Email</th>
                <th class="th-cell" width="130">Trạng Thái</th>
              </tr>';

        $stt = 1;
        foreach ($rows as $r) {
            echo "<tr>
                    <td class='td-center td-bold'>{$stt}</td>
                    <td class='td-center'>{$r['id']}</td>
                    <td class='td-left td-bold'>" . htmlspecialchars($r['fullname']) . "</td>
                    <td class='td-left'>" . htmlspecialchars($r['department']) . "</td>
                    <td class='td-left'>" . htmlspecialchars($r['position']) . "</td>
                    <td class='td-center' style='mso-number-format:\"\@\";'>" . htmlspecialchars($r['phone']) . "</td>
                    <td class='td-left'>" . htmlspecialchars($r['email']) . "</td>
                    <td class='td-center'>" . htmlspecialchars($r['status']) . "</td>
                  </tr>";
            $stt++;
        }
        $totalExported = count($rows);
        echo "<tr>
                <td colspan='2' class='tf-total td-center'>TỔNG CỘNG:</td>
                <td colspan='6' class='tf-total td-left'>{$totalExported} nhân viên</td>
              </tr>";
        echo '</table></body></html>';
        exit;
    }

    // B. Xuất CNV Chi Nhánh Khác
    if ($export_type === 'branch') {
        header("Content-Disposition: attachment; filename=Bao_Cao_CNV_Chi_Nhanh_{$date_str}.xls");
        echo "\xEF\xBB\xBF";

        $exp_id     = trim($_GET['f2_id'] ?? '');
        $exp_name   = trim($_GET['f2_name'] ?? '');
        $exp_dept   = trim($_GET['f2_dept'] ?? '');
        $exp_pos    = trim($_GET['f2_pos'] ?? '');
        $exp_status = trim($_GET['f2_status'] ?? '');

        $where = ["(branch != 'Trụ sở chính' AND branch IS NOT NULL AND branch != '')"];
        $params = [];
        if ($exp_id !== '')     { $where[] = "id = :id"; $params[':id'] = $exp_id; }
        if ($exp_name !== '')   { $where[] = "fullname LIKE :name"; $params[':name'] = "%$exp_name%"; }
        if ($exp_dept !== '')   { $where[] = "department = :dept"; $params[':dept'] = $exp_dept; }
        if ($exp_pos !== '')    { $where[] = "position LIKE :pos"; $params[':pos'] = "%$exp_pos%"; }
        if ($exp_status !== '') { $where[] = "status = :status"; $params[':status'] = $exp_status; }

        $stmt = $pdo->prepare("SELECT * FROM employees WHERE " . implode(' AND ', $where) . " ORDER BY id ASC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $excel_style . '</head><body>';
        echo '<table border="1">';
        echo '<tr><td colspan="9" class="rpt-title">BÁO CÁO DANH SÁCH NHÂN SỰ CHI NHÁNH</td></tr>';
        echo '<tr><td colspan="9" class="rpt-time">Thời gian kết xuất: ' . date('d/m/Y H:i:s') . '</td></tr>';
        echo '<tr><td colspan="9" style="border:none; height:10px;"></td></tr>';
        echo '<tr>
                <th class="th-cell" width="50">STT</th>
                <th class="th-cell" width="70">Mã ID</th>
                <th class="th-cell" width="200">Họ và Tên</th>
                <th class="th-cell" width="160">Chi Nhánh</th>
                <th class="th-cell" width="150">Phòng Ban</th>
                <th class="th-cell" width="160">Vị Trí / Chức Vụ</th>
                <th class="th-cell" width="130">Số Điện Thoại</th>
                <th class="th-cell" width="190">Email</th>
                <th class="th-cell" width="130">Trạng Thái</th>
              </tr>';

        $stt = 1;
        foreach ($rows as $r) {
            echo "<tr>
                    <td class='td-center td-bold'>{$stt}</td>
                    <td class='td-center'>{$r['id']}</td>
                    <td class='td-left td-bold'>" . htmlspecialchars($r['fullname']) . "</td>
                    <td class='td-center td-bold'>" . htmlspecialchars($r['branch']) . "</td>
                    <td class='td-left'>" . htmlspecialchars($r['department']) . "</td>
                    <td class='td-left'>" . htmlspecialchars($r['position']) . "</td>
                    <td class='td-center' style='mso-number-format:\"\@\";'>" . htmlspecialchars($r['phone']) . "</td>
                    <td class='td-left'>" . htmlspecialchars($r['email']) . "</td>
                    <td class='td-center'>" . htmlspecialchars($r['status']) . "</td>
                  </tr>";
            $stt++;
        }
        $totalExported = count($rows);
        echo "<tr>
                <td colspan='2' class='tf-total td-center'>TỔNG CỘNG:</td>
                <td colspan='7' class='tf-total td-left'>{$totalExported} nhân viên</td>
              </tr>";
        echo '</table></body></html>';
        exit;
    }

    // C. Xuất Ứng Viên Tuyển Dụng
    if ($export_type === 'candidates') {
        header("Content-Disposition: attachment; filename=Bao_Cao_Ung_Vien_{$date_str}.xls");
        echo "\xEF\xBB\xBF";

        $exp_id     = trim($_GET['f3_id'] ?? '');
        $exp_name   = trim($_GET['f3_name'] ?? '');
        $exp_pos    = trim($_GET['f3_pos'] ?? '');
        $exp_status = trim($_GET['f3_status'] ?? '');

        $where = ["1=1"];
        $params = [];
        if ($exp_id !== '')     { $where[] = "id = :id"; $params[':id'] = $exp_id; }
        if ($exp_name !== '')   { $where[] = "fullname LIKE :name"; $params[':name'] = "%$exp_name%"; }
        if ($exp_pos !== '')    { $where[] = "applied_position LIKE :pos"; $params[':pos'] = "%$exp_pos%"; }
        if ($exp_status !== '') { $where[] = "interview_status = :status"; $params[':status'] = $exp_status; }

        $stmt = $pdo->prepare("SELECT * FROM candidates WHERE " . implode(' AND ', $where) . " ORDER BY hired ASC, id ASC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $excel_style . '</head><body>';
        echo '<table border="1">';
        echo '<tr><td colspan="9" class="rpt-title">BÁO CÁO DANH SÁCH ỨNG VIÊN TUYỂN DỤNG</td></tr>';
        echo '<tr><td colspan="9" class="rpt-time">Thời gian kết xuất: ' . date('d/m/Y H:i:s') . '</td></tr>';
        echo '<tr><td colspan="9" style="border:none; height:10px;"></td></tr>';
        echo '<tr>
                <th class="th-cell" width="50">STT</th>
                <th class="th-cell" width="70">Mã ID</th>
                <th class="th-cell" width="200">Họ Tên Ứng Viên</th>
                <th class="th-cell" width="170">Vị Trí Ứng Tuyển</th>
                <th class="th-cell" width="130">Điện Thoại</th>
                <th class="th-cell" width="190">Email</th>
                <th class="th-cell" width="130">Ngày PV</th>
                <th class="th-cell" width="140">Kết Quả PV</th>
                <th class="th-cell" width="130">Tình Trạng</th>
              </tr>';

        $stt = 1;
        foreach ($rows as $r) {
            $tinh_trang = $r['hired'] ? 'Đã tuyển dụng' : 'Chưa tuyển';
            $ngay_pv = (!empty($r['interview_date']) && $r['interview_date'] !== '0000-00-00') ? date('d/m/Y', strtotime($r['interview_date'])) : '—';
            echo "<tr>
                    <td class='td-center td-bold'>{$stt}</td>
                    <td class='td-center'>{$r['id']}</td>
                    <td class='td-left td-bold'>" . htmlspecialchars($r['fullname']) . "</td>
                    <td class='td-left'>" . htmlspecialchars($r['applied_position']) . "</td>
                    <td class='td-center' style='mso-number-format:\"\@\";'>" . htmlspecialchars($r['phone']) . "</td>
                    <td class='td-left'>" . htmlspecialchars($r['email']) . "</td>
                    <td class='td-center'>{$ngay_pv}</td>
                    <td class='td-center'>" . htmlspecialchars($r['interview_status']) . "</td>
                    <td class='td-center'>{$tinh_trang}</td>
                  </tr>";
            $stt++;
        }
        $totalExported = count($rows);
        echo "<tr>
                <td colspan='2' class='tf-total td-center'>TỔNG CỘNG:</td>
                <td colspan='7' class='tf-total td-left'>{$totalExported} hồ sơ ứng viên</td>
              </tr>";
        echo '</table></body></html>';
        exit;
    }
}

// ==================== 2. HÀM ĐỌC DỮ LIỆU ĐA NĂNG ====================
function parseExcelOrCsvFile($filePath) {
    $rows = [];

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($filePath) === TRUE) {
            $strings = [];
            $sharedStringsXML = $zip->getFromName('xl/sharedStrings.xml');
            if ($sharedStringsXML) {
                $xml = simplexml_load_string($sharedStringsXML);
                foreach ($xml->si as $val) {
                    $strings[] = (string)($val->t ?? ($val->r ? $val->r->t : ''));
                }
            }

            $sheetXML = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheetXML) {
                $xml = simplexml_load_string($sheetXML);
                foreach ($xml->sheetData->row as $row) {
                    $rowCols = [];
                    foreach ($row->c as $c) {
                        $attrType = (string)$c['t'];
                        $val = (string)$c->v;
                        if ($attrType === 's') {
                            $rowCols[] = isset($strings[$val]) ? $strings[$val] : '';
                        } else {
                            $rowCols[] = $val;
                        }
                    }
                    if (!empty($rowCols)) {
                        $rows[] = $rowCols;
                    }
                }
            }
            $zip->close();
            if (!empty($rows)) return $rows;
        }
    }

    $content = file_get_contents($filePath);
    if (stripos($content, '<table') !== false) {
        preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $content, $trMatches);
        if (!empty($trMatches[1])) {
            foreach ($trMatches[1] as $tr) {
                preg_match_all('/<(td|th)[^>]*>(.*?)<\/(td|th)>/is', $tr, $tdMatches);
                if (!empty($tdMatches[2])) {
                    $rowCols = array_map(function($td) {
                        return trim(html_entity_decode(strip_tags($td), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    }, $tdMatches[2]);
                    $rows[] = $rowCols;
                }
            }
        }
        if (!empty($rows)) return $rows;
    }

    $handle = fopen($filePath, "r");
    if ($handle !== FALSE) {
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

        while (($data = fgetcsv($handle, 2000, $delimiter)) !== FALSE) {
            if (!empty($data)) {
                $rows[] = array_map('trim', $data);
            }
        }
        fclose($handle);
    }

    return $rows;
}

// ==================== 3. XỬ LÝ NHẬP FILE VÀ CRUD ====================
$message = '';
$error = '';
$active_tab = $_POST['active_tab'] ?? ($_GET['tab'] ?? 'employees-tab');

// Nhập File Excel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_excel') {
    $import_type = $_POST['import_type'] ?? '';

    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['excel_file']['tmp_name'];
        $parsedRows = parseExcelOrCsvFile($fileTmp);

        if (!empty($parsedRows)) {
            $importedCount = 0;
            try {
                $pdo->beginTransaction();

                foreach ($parsedRows as $row) {
                    if (empty($row)) continue;
                    $firstCol = trim($row[0] ?? '');

                    if (count($row) < 3 || 
                        stripos($firstCol, 'BÁO CÁO') !== false || 
                        stripos($firstCol, 'Thời gian') !== false || 
                        stripos($firstCol, 'TỔNG CỘNG') !== false || 
                        mb_strtolower($firstCol, 'UTF-8') === 'stt') {
                        continue;
                    }

                    $offset = 0;
                    $custom_id = null;

                    if (is_numeric($firstCol)) {
                        $offset = 1;
                        $secondCol = trim($row[1] ?? '');
                        $cleanId = str_replace('#', '', $secondCol);
                        if (is_numeric($cleanId)) {
                            $custom_id = intval($cleanId);
                            $offset = 2;
                        }
                    }

                    if ($import_type === 'employees') {
                        $fullname   = trim($row[$offset + 0] ?? '');
                        $department = trim($row[$offset + 1] ?? 'Chưa phân bổ');
                        $position   = trim($row[$offset + 2] ?? '');
                        $phone      = trim($row[$offset + 3] ?? '');
                        $email      = trim($row[$offset + 4] ?? '');
                        $status     = trim($row[$offset + 5] ?? 'Đang làm việc');

                        if (!empty($fullname) && !empty($position) && stripos($fullname, 'Họ') === false) {
                            if ($custom_id) {
                                $stmt = $pdo->prepare("INSERT INTO employees (id, fullname, department, branch, position, phone, email, status) VALUES (:id, :fullname, :dept, 'Trụ sở chính', :pos, :phone, :email, :status) ON DUPLICATE KEY UPDATE fullname = VALUES(fullname), department = VALUES(department), position = VALUES(position), phone = VALUES(phone), email = VALUES(email), status = VALUES(status)");
                                $stmt->execute([':id' => $custom_id, ':fullname' => $fullname, ':dept' => $department ?: 'Chưa phân bổ', ':pos' => $position, ':phone' => $phone, ':email' => $email, ':status' => $status ?: 'Đang làm việc']);
                            } else {
                                $stmt = $pdo->prepare("INSERT INTO employees (fullname, department, branch, position, phone, email, status) VALUES (:fullname, :dept, 'Trụ sở chính', :pos, :phone, :email, :status)");
                                $stmt->execute([':fullname' => $fullname, ':dept' => $department ?: 'Chưa phân bổ', ':pos' => $position, ':phone' => $phone, ':email' => $email, ':status' => $status ?: 'Đang làm việc']);
                            }
                            $importedCount++;
                        }
                    }

                    if ($import_type === 'branch') {
                        $fullname   = trim($row[$offset + 0] ?? '');
                        $branch     = trim($row[$offset + 1] ?? 'Chi nhánh');
                        $department = trim($row[$offset + 2] ?? 'Chưa phân bổ');
                        $position   = trim($row[$offset + 3] ?? '');
                        $phone      = trim($row[$offset + 4] ?? '');
                        $email      = trim($row[$offset + 5] ?? '');
                        $status     = trim($row[$offset + 6] ?? 'Đang làm việc');

                        if (!empty($fullname) && !empty($position) && stripos($fullname, 'Họ') === false) {
                            if ($custom_id) {
                                $stmt = $pdo->prepare("INSERT INTO employees (id, fullname, department, branch, position, phone, email, status) VALUES (:id, :fullname, :dept, :branch, :pos, :phone, :email, :status) ON DUPLICATE KEY UPDATE fullname = VALUES(fullname), branch = VALUES(branch), department = VALUES(department), position = VALUES(position), phone = VALUES(phone), email = VALUES(email), status = VALUES(status)");
                                $stmt->execute([':id' => $custom_id, ':fullname' => $fullname, ':dept' => $department ?: 'Chưa phân bổ', ':branch' => $branch ?: 'Chi nhánh', ':pos' => $position, ':phone' => $phone, ':email' => $email, ':status' => $status ?: 'Đang làm việc']);
                            } else {
                                $stmt = $pdo->prepare("INSERT INTO employees (fullname, department, branch, position, phone, email, status) VALUES (:fullname, :dept, :branch, :pos, :phone, :email, :status)");
                                $stmt->execute([':fullname' => $fullname, ':dept' => $department ?: 'Chưa phân bổ', ':branch' => $branch ?: 'Chi nhánh', ':pos' => $position, ':phone' => $phone, ':email' => $email, ':status' => $status ?: 'Đang làm việc']);
                            }
                            $importedCount++;
                        }
                    }

                    if ($import_type === 'candidates') {
                        $fullname         = trim($row[$offset + 0] ?? '');
                        $applied_position = trim($row[$offset + 1] ?? '');
                        $phone            = trim($row[$offset + 2] ?? '');
                        $email            = trim($row[$offset + 3] ?? '');
                        $raw_date         = trim($row[$offset + 4] ?? '');
                        $interview_status = trim($row[$offset + 5] ?? 'Chờ phỏng vấn');

                        $interview_date = null;
                        if (!empty($raw_date) && $raw_date !== '—' && $raw_date !== '0000-00-00') {
                            $timeVal = strtotime(str_replace('/', '-', $raw_date));
                            if ($timeVal !== false) {
                                $interview_date = date('Y-m-d', $timeVal);
                            }
                        }

                        if (!empty($fullname) && !empty($applied_position) && stripos($fullname, 'Họ') === false) {
                            if ($custom_id) {
                                $stmt = $pdo->prepare("INSERT INTO candidates (id, fullname, applied_position, phone, email, interview_date, interview_status, hired) VALUES (:id, :fullname, :pos, :phone, :email, :int_date, :status, 0) ON DUPLICATE KEY UPDATE fullname = VALUES(fullname), applied_position = VALUES(applied_position), phone = VALUES(phone), email = VALUES(email), interview_date = VALUES(interview_date), interview_status = VALUES(interview_status)");
                                $stmt->execute([':id' => $custom_id, ':fullname' => $fullname, ':pos' => $applied_position, ':phone' => $phone, ':email' => $email, ':int_date' => $interview_date, ':status' => $interview_status ?: 'Chờ phỏng vấn']);
                            } else {
                                $stmt = $pdo->prepare("INSERT INTO candidates (fullname, applied_position, phone, email, interview_date, interview_status, hired) VALUES (:fullname, :pos, :phone, :email, :int_date, :status, 0)");
                                $stmt->execute([':fullname' => $fullname, ':pos' => $applied_position, ':phone' => $phone, ':email' => $email, ':int_date' => $interview_date, ':status' => $interview_status ?: 'Chờ phỏng vấn']);
                            }
                            $importedCount++;
                        }
                    }
                }

                $pdo->commit();
                $message = "Đã nhập thành công {$importedCount} dòng dữ liệu khớp chính xác vào hệ thống!";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Lỗi khi nhập: " . $e->getMessage();
            }
        } else {
            $error = "Tệp tải lên không đọc được dữ liệu!";
        }
    } else {
        $error = "Vui lòng chọn tệp Excel hợp lệ!";
    }
}

// Thêm Nhân Viên Mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_employee') {
    $custom_id  = !empty($_POST['custom_id']) ? intval($_POST['custom_id']) : null;
    $fullname   = trim($_POST['fullname'] ?? '');
    $department = trim($_POST['department'] ?? 'Chưa phân bổ');
    $branch     = trim($_POST['branch'] ?? 'Trụ sở chính');
    $position   = trim($_POST['position'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $status     = trim($_POST['status'] ?? 'Đang làm việc');

    if (!empty($fullname) && !empty($position)) {
        try {
            if ($custom_id) {
                $check = $pdo->prepare("SELECT id FROM employees WHERE id = :id");
                $check->execute([':id' => $custom_id]);
                if ($check->fetch()) {
                    $error = "Mã ID #$custom_id đã tồn tại! Vui lòng chọn mã khác.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO employees (id, fullname, department, branch, position, phone, email, status) VALUES (:id, :fullname, :department, :branch, :position, :phone, :email, :status)");
                    $stmt->execute([':id' => $custom_id, ':fullname' => $fullname, ':department' => $department ?: 'Chưa phân bổ', ':branch' => $branch ?: 'Trụ sở chính', ':position' => $position, ':phone' => $phone, ':email' => $email, ':status' => $status]);
                    $message = "Thêm nhân viên với ID #$custom_id thành công!";
                }
            } else {
                $stmt = $pdo->prepare("INSERT INTO employees (fullname, department, branch, position, phone, email, status) VALUES (:fullname, :department, :branch, :position, :phone, :email, :status)");
                $stmt->execute([':fullname' => $fullname, ':department' => $department ?: 'Chưa phân bổ', ':branch' => $branch ?: 'Trụ sở chính', ':position' => $position, ':phone' => $phone, ':email' => $email, ':status' => $status]);
                $message = "Thêm nhân viên mới thành công!";
            }
        } catch (PDOException $e) { $error = "Lỗi: " . $e->getMessage(); }
    }
}

// Cập Nhật Nhân Viên & Đồng Bộ Trạng Thái Chấm Dứt Hợp Đồng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_employee') {
    $old_id     = intval($_POST['old_id']);
    $new_id     = !empty($_POST['custom_id']) ? intval($_POST['custom_id']) : $old_id;
    $fullname   = trim($_POST['fullname'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $branch     = trim($_POST['branch'] ?? 'Trụ sở chính');
    $position   = trim($_POST['position'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $status     = trim($_POST['status'] ?? 'Đang làm việc');

    if (!empty($fullname) && !empty($old_id)) {
        try {
            if ($new_id !== $old_id) {
                $check = $pdo->prepare("SELECT id FROM employees WHERE id = :id");
                $check->execute([':id' => $new_id]);
                if ($check->fetch()) {
                    $error = "Mã ID mới #$new_id đã bị trùng với một nhân sự khác!";
                }
            }

            if (empty($error)) {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("UPDATE employees SET id = :new_id, fullname = :fullname, department = :department, branch = :branch, position = :position, phone = :phone, email = :email, status = :status WHERE id = :old_id");
                $stmt->execute([
                    ':new_id'     => $new_id,
                    ':fullname'   => $fullname,
                    ':department' => $department ?: 'Chưa phân bổ',
                    ':branch'     => $branch ?: 'Trụ sở chính',
                    ':position'   => $position,
                    ':phone'      => $phone,
                    ':email'      => $email,
                    ':status'     => $status,
                    ':old_id'     => $old_id
                ]);

                if ($status === 'Đã nghỉ') {
                    try {
                        $stmtTerm = $pdo->prepare("UPDATE contracts SET status = 'Đã chấm dứt', notes = CONCAT(COALESCE(notes, ''), ' [Tự động chấm dứt do nhân viên nghỉ việc ngày $today]') WHERE employee_id = :eid AND status = 'Hiệu lực'");
                        $stmtTerm->execute([':eid' => $new_id]);
                    } catch (PDOException $e) {}
                }

                $pdo->commit();
                $message = "Cập nhật thông tin nhân viên #$new_id thành công!";
            }
        } catch (PDOException $e) { 
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Lỗi: " . $e->getMessage(); 
        }
    }
}

// Xoá nhân viên
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_employee') {
    $emp_id = intval($_POST['emp_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM employees WHERE id = :id");
        $stmt->execute([':id' => $emp_id]);
        $message = "Đã xoá nhân viên thành công!";
    } catch (PDOException $e) { $error = "Lỗi: " . $e->getMessage(); }
}

// Thao tác Ứng viên
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_candidate') {
    $custom_id        = !empty($_POST['custom_id']) ? intval($_POST['custom_id']) : null;
    $fullname         = trim($_POST['fullname'] ?? '');
    $applied_position = trim($_POST['applied_position'] ?? '');
    $phone            = trim($_POST['phone'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $interview_date   = !empty($_POST['interview_date']) ? $_POST['interview_date'] : null;
    $interview_status = trim($_POST['interview_status'] ?? 'Chờ phỏng vấn');

    if (!empty($fullname) && !empty($applied_position) && !empty($phone)) {
        try {
            if ($custom_id) {
                $check = $pdo->prepare("SELECT id FROM candidates WHERE id = :id");
                $check->execute([':id' => $custom_id]);
                if ($check->fetch()) {
                    $error = "Mã ứng viên #$custom_id đã tồn tại!";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO candidates (id, fullname, applied_position, phone, email, interview_date, interview_status, hired) VALUES (:id, :fullname, :applied_position, :phone, :email, :interview_date, :interview_status, 0)");
                    $stmt->execute([':id' => $custom_id, ':fullname' => $fullname, ':applied_position' => $applied_position, ':phone' => $phone, ':email' => $email, ':interview_date' => $interview_date, ':interview_status' => $interview_status]);
                    $message = "Thêm ứng viên với ID #$custom_id thành công!";
                }
            } else {
                $stmt = $pdo->prepare("INSERT INTO candidates (fullname, applied_position, phone, email, interview_date, interview_status, hired) VALUES (:fullname, :applied_position, :phone, :email, :interview_date, :interview_status, 0)");
                $stmt->execute([':fullname' => $fullname, ':applied_position' => $applied_position, ':phone' => $phone, ':email' => $email, ':interview_date' => $interview_date, ':interview_status' => $interview_status]);
                $message = "Thêm hồ sơ ứng viên thành công!";
            }
        } catch (PDOException $e) { $error = "Lỗi: " . $e->getMessage(); }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_candidate') {
    $old_id             = intval($_POST['old_id']);
    $new_id             = !empty($_POST['custom_id']) ? intval($_POST['custom_id']) : $old_id;
    $fullname           = trim($_POST['fullname'] ?? '');
    $applied_position   = trim($_POST['applied_position'] ?? '');
    $phone              = trim($_POST['phone'] ?? '');
    $email              = trim($_POST['email'] ?? '');
    $interview_date     = !empty($_POST['interview_date']) ? $_POST['interview_date'] : null;
    $interview_status   = trim($_POST['interview_status'] ?? 'Chờ phỏng vấn');

    if (!empty($fullname) && !empty($old_id)) {
        try {
            if ($new_id !== $old_id) {
                $check = $pdo->prepare("SELECT id FROM candidates WHERE id = :id");
                $check->execute([':id' => $new_id]);
                if ($check->fetch()) {
                    $error = "Mã ID mới #$new_id đã bị trùng với ứng viên khác!";
                }
            }

            if (empty($error)) {
                $stmt = $pdo->prepare("UPDATE candidates SET id = :new_id, fullname = :fullname, applied_position = :applied_position, phone = :phone, email = :email, interview_date = :interview_date, interview_status = :interview_status WHERE id = :old_id");
                $stmt->execute([
                    ':new_id'           => $new_id,
                    ':fullname'         => $fullname,
                    ':applied_position' => $applied_position,
                    ':phone'            => $phone,
                    ':email'            => $email,
                    ':interview_date'   => $interview_date,
                    ':interview_status' => $interview_status,
                    ':old_id'           => $old_id
                ]);
                $message = "Cập nhật ứng viên #$new_id thành công!";
            }
        } catch (PDOException $e) { $error = "Lỗi: " . $e->getMessage(); }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_candidate') {
    $cand_id = intval($_POST['cand_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM candidates WHERE id = :id");
        $stmt->execute([':id' => $cand_id]);
        $message = "Đã xoá ứng viên!";
    } catch (PDOException $e) { $error = "Lỗi: " . $e->getMessage(); }
}

// LIÊN KẾT ĐỒNG BỘ: TUYỂN DỤNG ỨNG VIÊN KÈM THÔNG TIN HỢP ĐỒNG TỰ ĐỘNG
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'hire_candidate') {
    $candidate_id   = intval($_POST['candidate_id']);
    $contract_months = intval($_POST['contract_months'] ?? 2);
    $start_date     = !empty($_POST['contract_start_date']) ? $_POST['contract_start_date'] : $today;
    $end_date       = date('Y-m-d', strtotime("+$contract_months months", strtotime($start_date)));
    
    // Phân loại hợp đồng tự động dựa trên số tháng
    $contract_type  = ($contract_months >= 12) ? 'Xác định thời hạn' : 'Thử việc';

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = :id AND hired = 0 LIMIT 1");
        $stmt->execute([':id' => $candidate_id]);
        $cand = $stmt->fetch();

        if ($cand) {
            // 1. Thêm vào danh sách nhân viên
            $insertEmp = $pdo->prepare("INSERT INTO employees (fullname, phone, email, position, department, branch, status) VALUES (:fullname, :phone, :email, :position, 'Chưa phân bổ', 'Trụ sở chính', 'Đang làm việc')");
            $insertEmp->execute([':fullname' => $cand['fullname'], ':phone' => $cand['phone'], ':email' => $cand['email'], ':position' => $cand['applied_position']]);
            $new_emp_id = $pdo->lastInsertId();

            // 2. Tự động sinh số hợp đồng và thêm vào bảng contracts
            $c_num = 'HĐ-' . date('Y') . '/' . sprintf('%03d', $new_emp_id);
            try {
                $stmtContract = $pdo->prepare("
                    INSERT INTO contracts (employee_id, contract_number, contract_type, start_date, end_date, basic_salary, status, notes) 
                    VALUES (:eid, :cnum, :ctype, :s_date, :e_date, 0.00, 'Hiệu lực', 'Tự động tạo khi tuyển ứng viên')
                ");
                $stmtContract->execute([
                    ':eid'   => $new_emp_id,
                    ':cnum'  => $c_num,
                    ':ctype' => $contract_type,
                    ':s_date'=> $start_date,
                    ':e_date'=> $end_date
                ]);
            } catch (PDOException $e) {}

            // 3. Cập nhật hồ sơ ứng viên
            $updateCand = $pdo->prepare("UPDATE candidates SET hired = 1, interview_status = 'Trúng tuyển' WHERE id = :id");
            $updateCand->execute([':id' => $candidate_id]);

            $pdo->commit();
            $message = "Đã tuyển dụng: " . htmlspecialchars($cand['fullname']) . "! Hệ thống đã tự động tạo nhân sự và sinh hợp đồng số [$c_num].";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Lỗi tuyển dụng: " . $e->getMessage();
    }
}

// ==================== 4. BỘ LỌC TÌM KIẾM VÀ PHÂN TRANG (15 DÒNG/TRANG) ====================
$limit = 15;

$empContracts = [];
try {
    $sqlContracts = "
        SELECT c1.employee_id, c1.contract_number, c1.contract_type, c1.end_date, c1.status,
               DATEDIFF(c1.end_date, '$today') AS days_left
        FROM contracts c1
        INNER JOIN (
            SELECT employee_id, MAX(id) as max_id FROM contracts GROUP BY employee_id
        ) c2 ON c1.id = c2.max_id
    ";
    $stmtC = $pdo->query($sqlContracts);
    while ($r = $stmtC->fetch()) {
        $empContracts[intval($r['employee_id'])] = $r;
    }
} catch (PDOException $e) {
    $empContracts = [];
}

// TAB 1: NHÂN VIÊN CHÍNH
$f1_id     = trim($_GET['f1_id'] ?? '');
$f1_name   = trim($_GET['f1_name'] ?? '');
$f1_dept   = trim($_GET['f1_dept'] ?? '');
$f1_pos    = trim($_GET['f1_pos'] ?? '');
$f1_status = trim($_GET['f1_status'] ?? '');
$p1        = max(1, intval($_GET['p1'] ?? 1));

$where1 = ["(branch IS NULL OR branch = '' OR branch = 'Trụ sở chính')"];
$params1 = [];
if ($f1_id !== '')     { $where1[] = "id = :id"; $params1[':id'] = $f1_id; }
if ($f1_name !== '')   { $where1[] = "fullname LIKE :name"; $params1[':name'] = "%$f1_name%"; }
if ($f1_dept !== '')   { $where1[] = "department = :dept"; $params1[':dept'] = $f1_dept; }
if ($f1_pos !== '')    { $where1[] = "position LIKE :pos"; $params1[':pos'] = "%$f1_pos%"; }
if ($f1_status !== '') { $where1[] = "status = :status"; $params1[':status'] = $f1_status; }

$stmt1 = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE " . implode(' AND ', $where1));
$stmt1->execute($params1);
$total1 = $stmt1->fetchColumn();
$pages1 = ceil($total1 / $limit) ?: 1;
$offset1 = ($p1 - 1) * $limit;

$stmt1 = $pdo->prepare("SELECT * FROM employees WHERE " . implode(' AND ', $where1) . " ORDER BY id ASC LIMIT $limit OFFSET $offset1");
$stmt1->execute($params1);
$main_employees = $stmt1->fetchAll();

// TAB 2: CHI NHÁNH KHÁC
$f2_id     = trim($_GET['f2_id'] ?? '');
$f2_name   = trim($_GET['f2_name'] ?? '');
$f2_dept   = trim($_GET['f2_dept'] ?? '');
$f2_pos    = trim($_GET['f2_pos'] ?? '');
$f2_status = trim($_GET['f2_status'] ?? '');
$p2        = max(1, intval($_GET['p2'] ?? 1));

$where2 = ["(branch != 'Trụ sở chính' AND branch IS NOT NULL AND branch != '')"];
$params2 = [];
if ($f2_id !== '')     { $where2[] = "id = :id"; $params2[':id'] = $f2_id; }
if ($f2_name !== '')   { $where2[] = "fullname LIKE :name"; $params2[':name'] = "%$f2_name%"; }
if ($f2_dept !== '')   { $where2[] = "department = :dept"; $params2[':dept'] = $f2_dept; }
if ($f2_pos !== '')    { $where2[] = "position LIKE :pos"; $params2[':pos'] = "%$f2_pos%"; }
if ($f2_status !== '') { $where2[] = "status = :status"; $params2[':status'] = $f2_status; }

$stmt2 = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE " . implode(' AND ', $where2));
$stmt2->execute($params2);
$total2 = $stmt2->fetchColumn();
$pages2 = ceil($total2 / $limit) ?: 1;
$offset2 = ($p2 - 1) * $limit;

$stmt2 = $pdo->prepare("SELECT * FROM employees WHERE " . implode(' AND ', $where2) . " ORDER BY id ASC LIMIT $limit OFFSET $offset2");
$stmt2->execute($params2);
$branch_employees = $stmt2->fetchAll();

// TAB 3: ỨNG VIÊN
$f3_id     = trim($_GET['f3_id'] ?? '');
$f3_name   = trim($_GET['f3_name'] ?? '');
$f3_pos    = trim($_GET['f3_pos'] ?? '');
$f3_status = trim($_GET['f3_status'] ?? '');
$p3        = max(1, intval($_GET['p3'] ?? 1));

$where3 = ["1=1"];
$params3 = [];
if ($f3_id !== '')     { $where3[] = "id = :id"; $params3[':id'] = $f3_id; }
if ($f3_name !== '')   { $where3[] = "fullname LIKE :name"; $params3[':name'] = "%$f3_name%"; }
if ($f3_pos !== '')    { $where3[] = "applied_position LIKE :pos"; $params3[':pos'] = "%$f3_pos%"; }
if ($f3_status !== '') { $where3[] = "interview_status = :status"; $params3[':status'] = $f3_status; }

$stmt3 = $pdo->prepare("SELECT COUNT(*) FROM candidates WHERE " . implode(' AND ', $where3));
$stmt3->execute($params3);
$total3 = $stmt3->fetchColumn();
$pages3 = ceil($total3 / $limit) ?: 1;
$offset3 = ($p3 - 1) * $limit;

$stmt3 = $pdo->prepare("SELECT * FROM candidates WHERE " . implode(' AND ', $where3) . " ORDER BY hired ASC, id ASC LIMIT $limit OFFSET $offset3");
$stmt3->execute($params3);
$candidates = $stmt3->fetchAll();

// Hàm hiển thị huy hiệu hợp đồng liên kết
function renderContractBadge($empId, $contractsMap) {
    if (!isset($contractsMap[$empId])) {
        return '<span class="ct-badge badge-none">Chưa có HĐ</span>';
    }
    $c = $contractsMap[$empId];
    if ($c['status'] !== 'Hiệu lực') {
        return '<span class="ct-badge badge-none">' . htmlspecialchars($c['status']) . '</span>';
    }
    if ($c['contract_type'] === 'Không xác định thời hạn') {
        return '<span class="ct-badge badge-indef">Vô thời hạn</span>';
    }
    
    $days = $c['days_left'];
    if ($days < 0) {
        return '<span class="ct-badge badge-expired" title="Hết hạn: ' . date('d/m/Y', strtotime($c['end_date'])) . '">Quá hạn ' . abs($days) . ' ngày</span>';
    } elseif ($days <= 30) {
        return '<span class="ct-badge badge-warning" title="Hết hạn: ' . date('d/m/Y', strtotime($c['end_date'])) . '">Còn ' . $days . ' ngày</span>';
    } else {
        return '<span class="ct-badge badge-safe" title="Hết hạn: ' . date('d/m/Y', strtotime($c['end_date'])) . '">' . htmlspecialchars($c['contract_type']) . '</span>';
    }
}

// Phân trang
function renderPagination($currentPage, $totalPages, $paramName, $tabName) {
    if ($totalPages <= 1) return;

    $queryParams = $_GET;
    $queryParams['tab'] = $tabName;

    echo '<div class="pagination-wrapper">';
    if ($currentPage > 1) {
        $queryParams[$paramName] = $currentPage - 1;
        echo '<a href="?' . http_build_query($queryParams) . '" class="page-link page-arrow">&laquo;</a>';
    } else {
        echo '<span class="page-link page-arrow disabled">&laquo;</span>';
    }

    $start = max(1, $currentPage - 1);
    $end = min($totalPages, $currentPage + 1);

    if ($currentPage == 1) {
        $end = min($totalPages, $start + 2);
    } elseif ($currentPage == $totalPages) {
        $start = max(1, $end - 2);
    }

    for ($i = $start; $i <= $end; $i++) {
        $queryParams[$paramName] = $i;
        $activeClass = ($i == $currentPage) ? 'active' : '';
        echo '<a href="?' . http_build_query($queryParams) . '" class="page-link ' . $activeClass . '">' . $i . '</a>';
    }

    if ($currentPage < $totalPages) {
        $queryParams[$paramName] = $currentPage + 1;
        echo '<a href="?' . http_build_query($queryParams) . '" class="page-link page-arrow">&raquo;</a>';
    } else {
        echo '<span class="page-link page-arrow disabled">&raquo;</span>';
    }

    echo '</div>';
}

include 'header.php';
?>

<style>
    .hr-wrapper { max-width: 1300px; margin: 25px auto; padding: 0 16px; }
    .hr-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; }
    .hr-title { font-size: 1.45rem; font-weight: 700; color: #0f172a; }
    .hr-tabs { display: flex; gap: 10px; margin-bottom: 18px; border-bottom: 2px solid #e2e8f0; overflow-x: auto; }
    .tab-btn { background: none; border: none; outline: none; padding: 10px 18px; font-size: 0.95rem; font-weight: 600; color: #64748b; cursor: pointer; border-bottom: 3px solid transparent; transition: all 0.2s; white-space: nowrap; }
    .tab-btn.active { color: #0284c7; border-bottom-color: #0284c7; }

    .filter-panel { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; margin-bottom: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
    .filter-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
    .filter-row input, .filter-row select { padding: 8px 11px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem; outline: none; box-sizing: border-box; }
    .filter-row input:focus, .filter-row select:focus { border-color: #0284c7; }
    .btn-filter { background-color: #0284c7; color: #ffffff; border: none; padding: 8px 16px; border-radius: 6px; font-size: 0.88rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; }
    .btn-filter:hover { background-color: #0369a1; }
    .btn-reset { background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 8px 12px; border-radius: 6px; font-size: 0.88rem; text-decoration: none; }
    .btn-reset:hover { background-color: #e2e8f0; }

    .section-toolbar { display: flex; justify-content: flex-end; align-items: center; gap: 10px; margin-bottom: 14px; flex-wrap: wrap; }
    .btn-create { background-color: #0284c7; color: #ffffff; border: none; padding: 9px 15px; border-radius: 6px; font-size: 0.88rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
    .btn-create:hover { background-color: #0369a1; }
    .btn-excel { background-color: #10b981; color: #ffffff; border: none; padding: 9px 15px; border-radius: 6px; font-size: 0.88rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
    .btn-excel:hover { background-color: #059669; }
    .btn-import { background-color: #8b5cf6; color: #ffffff; border: none; padding: 9px 15px; border-radius: 6px; font-size: 0.88rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
    .btn-import:hover { background-color: #7c3aed; }

    .data-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); overflow-x: auto; }
    .hr-table { width: 100%; border-collapse: collapse; text-align: left; min-width: 980px; }
    .hr-table th, .hr-table td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; font-size: 0.88rem; vertical-align: middle; }
    .hr-table th { background-color: #f8fafc; color: #475569; font-weight: 600; }
    .hr-table tfoot td { background-color: #f8fafc; font-weight: 700; color: #0f172a; border-top: 2px solid #cbd5e1; padding: 12px 14px; }

    .action-group { display: flex; gap: 5px; align-items: center; }
    .btn-edit { background-color: #f1f5f9; color: #0284c7; border: 1px solid #cbd5e1; padding: 5px 9px; border-radius: 4px; font-size: 0.8rem; font-weight: 600; cursor: pointer; }
    .btn-edit:hover { background-color: #e2e8f0; }
    .btn-delete { background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; padding: 5px 9px; border-radius: 4px; font-size: 0.8rem; font-weight: 600; cursor: pointer; }
    .btn-delete:hover { background-color: #fca5a5; }
    .btn-hire { background-color: #10b981; color: #ffffff; border: none; padding: 5px 10px; border-radius: 4px; font-size: 0.8rem; font-weight: 600; cursor: pointer; }
    .btn-hire:hover { background-color: #059669; }
    
    .btn-view-contract { background-color: #fff7ed; color: #ea580c; border: 1px solid #fed7aa; padding: 5px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; }
    .btn-view-contract:hover { background-color: #ffedd5; }

    .ct-badge { padding: 3px 7px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; display: inline-block; white-space: nowrap; }
    .badge-none    { background: #f1f5f9; color: #64748b; }
    .badge-indef   { background: #e0f2fe; color: #0284c7; }
    .badge-safe    { background: #dcfce7; color: #15803d; }
    .badge-warning { background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; }
    .badge-expired { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }

    .badge-hired { background-color: #e2e8f0; color: #475569; padding: 4px 7px; border-radius: 4px; font-size: 0.76rem; }
    .badge-status { padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
    .status-pass { background: #dcfce7; color: #166534; }
    .status-pending { background: #fef9c3; color: #854d0e; }
    .status-fail { background: #fee2e2; color: #991b1b; }
    .badge-branch { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; padding: 2px 7px; border-radius: 4px; font-size: 0.78rem; font-weight: 600; }
    .id-tag { background-color: #f1f5f9; color: #475569; font-weight: 700; padding: 2px 6px; border-radius: 4px; border: 1px solid #e2e8f0; font-size: 0.82rem; }

    .pagination-wrapper { display: flex; justify-content: center; align-items: center; gap: 6px; margin-top: 18px; }
    .page-link { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 6px; background: #ffffff; color: #334155; font-size: 0.9rem; font-weight: 600; text-decoration: none; }
    .page-link:hover:not(.disabled):not(.active) { background-color: #f1f5f9; border-color: #94a3b8; }
    .page-link.active { background-color: #0284c7; color: #ffffff; border-color: #0284c7; }
    .page-link.disabled { color: #cbd5e1; border-color: #e2e8f0; cursor: not-allowed; background: #f8fafc; }
    .page-arrow { font-size: 1.1rem; }

    .alert-box { padding: 12px 16px; border-radius: 6px; font-size: 0.9rem; margin-bottom: 18px; }
    .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

    .tab-content { display: none; }
    .tab-content.active { display: block; }

    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); z-index: 1000; align-items: center; justify-content: center; padding: 15px; }
    .modal-box { background: #ffffff; width: 100%; max-width: 500px; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); overflow: hidden; }
    .modal-header { padding: 14px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
    .modal-title { font-size: 1.1rem; font-weight: 700; color: #0f172a; }
    .btn-close-modal { background: none; border: none; font-size: 1.3rem; cursor: pointer; color: #64748b; }
    .modal-body { padding: 20px; display: flex; flex-direction: column; gap: 13px; }
    .form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 5px; color: #334155; }
    .form-group input, .form-group select { width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; box-sizing: border-box; outline: none; }
    .modal-footer { padding: 12px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px; }
    .btn-cancel { background: #e2e8f0; color: #475569; border: none; padding: 8px 14px; border-radius: 4px; cursor: pointer; }
    .btn-save { background: #0284c7; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: 600; }
</style>

<div class="hr-wrapper">
    <div class="hr-header">
        <h1 class="hr-title">Quản Trị Nhân Sự & Tuyển Dụng</h1>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert-box alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert-box alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="hr-tabs">
        <button id="btn-employees-tab" class="tab-btn" onclick="switchTab('employees-tab')">
            👥 Danh Sách Nhân Viên (<?= $total1 ?>)
        </button>
        <button id="btn-branch-tab" class="tab-btn" onclick="switchTab('branch-tab')">
            🏢 CNV Chi Nhánh Khác (<?= $total2 ?>)
        </button>
        <button id="btn-candidates-tab" class="tab-btn" onclick="switchTab('candidates-tab')">
            📝 Ứng Viên Tuyển Dụng (<?= $total3 ?>)
        </button>
    </div>

    <!-- TAB 1: NHÂN VIÊN TRỤ SỞ CHÍNH -->
    <div id="employees-tab" class="tab-content">
        <form method="GET" action="quan-tri-nhan-su.php" class="filter-panel" id="form-filter-1">
            <input type="hidden" name="tab" value="employees-tab">
            <div class="filter-row">
                <input type="number" name="f1_id" value="<?= htmlspecialchars($f1_id) ?>" placeholder="Mã ID..." style="width: 100px;">
                <input type="text" name="f1_name" value="<?= htmlspecialchars($f1_name) ?>" placeholder="Họ và tên..." style="flex: 1; min-width: 140px;">
                
                <select name="f1_dept" style="width: 180px;">
                    <option value="">-- Tất cả phòng ban --</option>
                    <?php foreach ($DEFAULT_DEPARTMENTS as $d): ?>
                        <option value="<?= htmlspecialchars($d) ?>" <?= $f1_dept === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                    <?php endforeach; ?>
                </select>

                <input type="text" name="f1_pos" value="<?= htmlspecialchars($f1_pos) ?>" placeholder="Vị trí / chức vụ..." style="width: 150px;">
                <select name="f1_status" style="width: 150px;">
                    <option value="">-- Tất cả trạng thái --</option>
                    <option value="Đang làm việc" <?= $f1_status === 'Đang làm việc' ? 'selected' : '' ?>>Đang làm việc</option>
                    <option value="Nghỉ phép" <?= $f1_status === 'Nghỉ phép' ? 'selected' : '' ?>>Nghỉ phép</option>
                    <option value="Đã nghỉ" <?= $f1_status === 'Đã nghỉ' ? 'selected' : '' ?>>Đã nghỉ</option>
                </select>
                <button type="submit" class="btn-filter">🔍 Lọc</button>
                <a href="quan-tri-nhan-su.php?tab=employees-tab" class="btn-reset">Đặt lại</a>
            </div>
        </form>

        <div class="section-toolbar">
            <button type="submit" form="form-filter-1" name="export" value="employees" class="btn-excel">📊 Xuất Excel Báo Cáo</button>
            <button class="btn-import" onclick="openImportModal('employees', 'Nhân Viên Trụ Sở Chính', 'employees-tab')">📥 Nhập Excel</button>
            <button class="btn-create" onclick="openAddEmp('Trụ sở chính', 'employees-tab')">+ Thêm Nhân Viên</button>
        </div>

        <div class="data-card">
            <table class="hr-table">
                <thead>
                    <tr>
                        <th style="width: 45px;">STT</th>
                        <th style="width: 65px;">Mã ID</th>
                        <th>Họ và Tên</th>
                        <th>Phòng ban</th>
                        <th>Vị trí</th>
                        <th>Số ĐT</th>
                        <th>Hợp đồng</th>
                        <th>Trạng thái</th>
                        <th style="width: 175px;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($main_employees)): ?>
                        <?php 
                        $stt_main = $offset1 + 1; 
                        foreach ($main_employees as $emp): 
                        ?>
                            <tr>
                                <td><strong><?= $stt_main++ ?></strong></td>
                                <td><span class="id-tag">#<?= $emp['id'] ?></span></td>
                                <td><strong><?= htmlspecialchars($emp['fullname']) ?></strong></td>
                                <td><?= ($emp['department'] === 'Chưa phân bổ') ? '<span style="color:#ea580c; font-style:italic;">Chưa phân bổ</span>' : htmlspecialchars($emp['department']) ?></td>
                                <td><?= htmlspecialchars($emp['position']) ?></td>
                                <td><?= htmlspecialchars($emp['phone'] ?: '—') ?></td>
                                <td><?= renderContractBadge($emp['id'], $empContracts) ?></td>
                                <td><?= htmlspecialchars($emp['status']) ?></td>
                                <td>
                                    <div class="action-group">
                                        <a href="quan-ly-hop-dong.php?f_name=<?= urlencode($emp['fullname']) ?>" class="btn-view-contract" title="Xem hoặc gia hạn hợp đồng">📄 HĐ</a>
                                        <button class="btn-edit" onclick='openEditEmp(<?= json_encode($emp, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, "employees-tab")'>Sửa</button>
                                        <form method="POST" action="quan-tri-nhan-su.php" onsubmit="return confirm('Bạn có chắc muốn xoá nhân viên này?');" style="margin: 0;">
                                            <input type="hidden" name="action" value="delete_employee">
                                            <input type="hidden" name="emp_id" value="<?= $emp['id'] ?>">
                                            <input type="hidden" name="active_tab" value="employees-tab">
                                            <button type="submit" class="btn-delete">Xoá</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9" style="text-align:center; padding: 20px;">Không tìm thấy nhân viên nào phù hợp.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">TỔNG CỘNG:</td>
                        <td colspan="7" style="color: #0284c7;"><?= number_format($total1) ?> nhân viên</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php renderPagination($p1, $pages1, 'p1', 'employees-tab'); ?>
    </div>

    <!-- TAB 2: CNV CHI NHÁNH KHÁC -->
    <div id="branch-tab" class="tab-content">
        <form method="GET" action="quan-tri-nhan-su.php" class="filter-panel" id="form-filter-2">
            <input type="hidden" name="tab" value="branch-tab">
            <div class="filter-row">
                <input type="number" name="f2_id" value="<?= htmlspecialchars($f2_id) ?>" placeholder="Mã ID..." style="width: 100px;">
                <input type="text" name="f2_name" value="<?= htmlspecialchars($f2_name) ?>" placeholder="Họ và tên..." style="flex: 1; min-width: 140px;">
                
                <select name="f2_dept" style="width: 180px;">
                    <option value="">-- Tất cả phòng ban --</option>
                    <?php foreach ($DEFAULT_DEPARTMENTS as $d): ?>
                        <option value="<?= htmlspecialchars($d) ?>" <?= $f2_dept === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                    <?php endforeach; ?>
                </select>

                <input type="text" name="f2_pos" value="<?= htmlspecialchars($f2_pos) ?>" placeholder="Vị trí / chức vụ..." style="width: 150px;">
                <select name="f2_status" style="width: 150px;">
                    <option value="">-- Tất cả trạng thái --</option>
                    <option value="Đang làm việc" <?= $f2_status === 'Đang làm việc' ? 'selected' : '' ?>>Đang làm việc</option>
                    <option value="Nghỉ phép" <?= $f2_status === 'Nghỉ phép' ? 'selected' : '' ?>>Nghỉ phép</option>
                    <option value="Đã nghỉ" <?= $f2_status === 'Đã nghỉ' ? 'selected' : '' ?>>Đã nghỉ</option>
                </select>
                <button type="submit" class="btn-filter">🔍 Lọc</button>
                <a href="quan-tri-nhan-su.php?tab=branch-tab" class="btn-reset">Đặt lại</a>
            </div>
        </form>

        <div class="section-toolbar">
            <button type="submit" form="form-filter-2" name="export" value="branch" class="btn-excel">📊 Xuất Excel Báo Cáo</button>
            <button class="btn-import" onclick="openImportModal('branch', 'CNV Chi Nhánh Khác', 'branch-tab')">📥 Nhập Excel</button>
            <button class="btn-create" onclick="openAddEmp('Chi nhánh khác', 'branch-tab')">+ Thêm CNV Chi Nhánh</button>
        </div>

        <div class="data-card">
            <table class="hr-table">
                <thead>
                    <tr>
                        <th style="width: 45px;">STT</th>
                        <th style="width: 65px;">Mã ID</th>
                        <th>Họ và Tên</th>
                        <th>Chi nhánh</th>
                        <th>Phòng ban</th>
                        <th>Vị trí</th>
                        <th>Hợp đồng</th>
                        <th>Trạng thái</th>
                        <th style="width: 175px;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($branch_employees)): ?>
                        <?php 
                        $stt_branch = $offset2 + 1; 
                        foreach ($branch_employees as $emp): 
                        ?>
                            <tr>
                                <td><strong><?= $stt_branch++ ?></strong></td>
                                <td><span class="id-tag">#<?= $emp['id'] ?></span></td>
                                <td><strong><?= htmlspecialchars($emp['fullname']) ?></strong></td>
                                <td><span class="badge-branch"><?= htmlspecialchars($emp['branch']) ?></span></td>
                                <td><?= htmlspecialchars($emp['department']) ?></td>
                                <td><?= htmlspecialchars($emp['position']) ?></td>
                                <td><?= renderContractBadge($emp['id'], $empContracts) ?></td>
                                <td><?= htmlspecialchars($emp['status']) ?></td>
                                <td>
                                    <div class="action-group">
                                        <a href="quan-ly-hop-dong.php?f_name=<?= urlencode($emp['fullname']) ?>" class="btn-view-contract" title="Xem hoặc gia hạn hợp đồng">📄 HĐ</a>
                                        <button class="btn-edit" onclick='openEditEmp(<?= json_encode($emp, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, "branch-tab")'>Sửa</button>
                                        <form method="POST" action="quan-tri-nhan-su.php" onsubmit="return confirm('Bạn có chắc muốn xoá nhân viên này?');" style="margin: 0;">
                                            <input type="hidden" name="action" value="delete_employee">
                                            <input type="hidden" name="emp_id" value="<?= $emp['id'] ?>">
                                            <input type="hidden" name="active_tab" value="branch-tab">
                                            <button type="submit" class="btn-delete">Xoá</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9" style="text-align:center; padding: 20px;">Không tìm thấy nhân viên chi nhánh phù hợp.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">TỔNG CỘNG:</td>
                        <td colspan="7" style="color: #0284c7;"><?= number_format($total2) ?> nhân viên</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php renderPagination($p2, $pages2, 'p2', 'branch-tab'); ?>
    </div>

    <!-- TAB 3: ỨNG VIÊN TUYỂN DỤNG -->
    <div id="candidates-tab" class="tab-content">
        <form method="GET" action="quan-tri-nhan-su.php" class="filter-panel" id="form-filter-3">
            <input type="hidden" name="tab" value="candidates-tab">
            <div class="filter-row">
                <input type="number" name="f3_id" value="<?= htmlspecialchars($f3_id) ?>" placeholder="Mã ID..." style="width: 100px;">
                <input type="text" name="f3_name" value="<?= htmlspecialchars($f3_name) ?>" placeholder="Họ tên ứng viên..." style="flex: 1; min-width: 140px;">
                <input type="text" name="f3_pos" value="<?= htmlspecialchars($f3_pos) ?>" placeholder="Vị trí ứng tuyển..." style="width: 160px;">
                <select name="f3_status" style="width: 160px;">
                    <option value="">-- Tất cả kết quả --</option>
                    <option value="Chờ phỏng vấn" <?= $f3_status === 'Chờ phỏng vấn' ? 'selected' : '' ?>>Chờ phỏng vấn</option>
                    <option value="Đã phỏng vấn" <?= $f3_status === 'Đã phỏng vấn' ? 'selected' : '' ?>>Đã phỏng vấn</option>
                    <option value="Trúng tuyển" <?= $f3_status === 'Trúng tuyển' ? 'selected' : '' ?>>Trúng tuyển</option>
                    <option value="Không đạt" <?= $f3_status === 'Không đạt' ? 'selected' : '' ?>>Không đạt</option>
                </select>
                <button type="submit" class="btn-filter">🔍 Lọc</button>
                <a href="quan-tri-nhan-su.php?tab=candidates-tab" class="btn-reset">Đặt lại</a>
            </div>
        </form>

        <div class="section-toolbar">
            <button type="submit" form="form-filter-3" name="export" value="candidates" class="btn-excel">📊 Xuất Excel Báo Cáo</button>
            <button class="btn-import" onclick="openImportModal('candidates', 'Hồ Sơ Ứng Viên', 'candidates-tab')">📥 Nhập Excel</button>
            <button class="btn-create" onclick="openAddCand('candidates-tab')">+ Thêm Ứng Viên</button>
        </div>

        <div class="data-card">
            <table class="hr-table">
                <thead>
                    <tr>
                        <th style="width: 45px;">STT</th>
                        <th style="width: 65px;">Mã ID</th>
                        <th>Họ tên ứng viên</th>
                        <th>Vị trí ứng tuyển</th>
                        <th>Điện thoại</th>
                        <th>Email</th>
                        <th>Ngày PV</th>
                        <th>Kết quả</th>
                        <th style="width: 190px;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($candidates)): ?>
                        <?php 
                        $stt_cand = $offset3 + 1; 
                        foreach ($candidates as $cand): 
                        ?>
                            <tr>
                                <td><strong><?= $stt_cand++ ?></strong></td>
                                <td><span class="id-tag">#<?= $cand['id'] ?></span></td>
                                <td><strong><?= htmlspecialchars($cand['fullname']) ?></strong></td>
                                <td><?= htmlspecialchars($cand['applied_position']) ?></td>
                                <td><?= htmlspecialchars($cand['phone']) ?></td>
                                <td><?= htmlspecialchars($cand['email'] ?: '—') ?></td>
                                <td><?= (!empty($cand['interview_date']) && $cand['interview_date'] !== '0000-00-00') ? date('d/m/Y', strtotime($cand['interview_date'])) : '—' ?></td>
                                <td>
                                    <?php
                                        $cls = 'status-pending';
                                        if ($cand['interview_status'] === 'Trúng tuyển') $cls = 'status-pass';
                                        if ($cand['interview_status'] === 'Không đạt') $cls = 'status-fail';
                                    ?>
                                    <span class="badge-status <?= $cls ?>"><?= htmlspecialchars($cand['interview_status']) ?></span>
                                </td>
                                <td>
                                    <div class="action-group">
                                        <button class="btn-edit" onclick='openEditCand(<?= json_encode($cand, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, "candidates-tab")'>Sửa</button>
                                        <?php if ($cand['hired']): ?>
                                            <span class="badge-hired">✓ Đã tuyển</span>
                                        <?php else: ?>
                                            <button type="button" class="btn-hire" onclick='openHireModal(<?= $cand['id'] ?>, "<?= htmlspecialchars($cand['fullname'], ENT_QUOTES) ?>")'>+ Tuyển</button>
                                        <?php endif; ?>
                                        <form method="POST" action="quan-tri-nhan-su.php" onsubmit="return confirm('Bạn có chắc muốn xoá ứng viên này?');" style="margin: 0;">
                                            <input type="hidden" name="action" value="delete_candidate">
                                            <input type="hidden" name="cand_id" value="<?= $cand['id'] ?>">
                                            <input type="hidden" name="active_tab" value="candidates-tab">
                                            <button type="submit" class="btn-delete">Xoá</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9" style="text-align:center; padding: 20px;">Không tìm thấy ứng viên phù hợp.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">TỔNG CỘNG:</td>
                        <td colspan="7" style="color: #0284c7;"><?= number_format($total3) ?> hồ sơ ứng viên</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php renderPagination($p3, $pages3, 'p3', 'candidates-tab'); ?>
    </div>
</div>

<!-- ==================== MODALS ==================== -->

<!-- 1. MODAL NHẬP FILE EXCEL -->
<div id="modal-import" class="modal-overlay">
    <div class="modal-box">
        <form method="POST" action="quan-tri-nhan-su.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="import_excel">
            <input type="hidden" name="import_type" id="import_type_input">
            <input type="hidden" name="active_tab" id="import_active_tab">

            <div class="modal-header">
                <span class="modal-title" id="import_modal_title">Nhập Danh Sách Từ File Excel</span>
                <button type="button" class="btn-close-modal" onclick="closeModal('modal-import')">&times;</button>
            </div>
            <div class="modal-body">
                <p id="import_hint_text" style="font-size: 0.85rem; color: #475569; line-height: 1.5; background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px dashed #cbd5e1;"></p>
                <div class="form-group">
                    <label>Chọn tệp Excel (.xls, .xlsx hoặc .csv) *</label>
                    <input type="file" name="excel_file" accept=".xls,.xlsx,.csv" required style="padding: 6px; border: 1px solid #cbd5e1;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('modal-import')">Hủy</button>
                <button type="submit" class="btn-save" style="background: #8b5cf6;">Bắt Đầu Tải Lên</button>
            </div>
        </form>
    </div>
</div>

<!-- 2. MODAL NHÂN VIÊN -->
<div id="modal-emp" class="modal-overlay">
    <div class="modal-box">
        <form method="POST" action="quan-tri-nhan-su.php">
            <input type="hidden" name="action" id="emp_action_type" value="add_employee">
            <input type="hidden" name="old_id" id="emp_old_id">
            <input type="hidden" name="active_tab" id="emp_active_tab" value="employees-tab">
            
            <div class="modal-header">
                <span class="modal-title" id="emp_modal_title">Thông Tin Nhân Viên</span>
                <button type="button" class="btn-close-modal" onclick="closeModal('modal-emp')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Mã ID Nhân Viên (Nhập số tùy chỉnh hoặc để trống)</label>
                    <input type="number" name="custom_id" id="emp_custom_id" placeholder="VD: 101, 102...">
                </div>
                <div class="form-group">
                    <label>Họ và Tên *</label>
                    <input type="text" name="fullname" id="emp_fullname" required placeholder="Nguyễn Văn A">
                </div>
                <div class="form-group">
                    <label>Thuộc Chi Nhánh *</label>
                    <input type="text" name="branch" id="emp_branch" required placeholder="VD: Trụ sở chính, Chi nhánh...">
                </div>

                <div class="form-group">
                    <label>Phòng ban *</label>
                    <select name="department" id="emp_dept" required>
                        <option value="">-- Chọn phòng ban --</option>
                        <?php foreach ($DEFAULT_DEPARTMENTS as $d): ?>
                            <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Vị trí / Chức danh *</label>
                    <input type="text" name="position" id="emp_pos" required placeholder="VD: Kỹ sư">
                </div>
                <div class="form-group">
                    <label>Số điện thoại</label>
                    <input type="text" name="phone" id="emp_phone" placeholder="09xxxxxxx">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="emp_email" placeholder="example@email.com">
                </div>
                <div class="form-group">
                    <label>Trạng thái</label>
                    <select name="status" id="emp_status">
                        <option value="Đang làm việc">Đang làm việc</option>
                        <option value="Nghỉ phép">Nghỉ phép</option>
                        <option value="Đã nghỉ">Đã nghỉ (Tự động chấm dứt hợp đồng)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('modal-emp')">Hủy</button>
                <button type="submit" class="btn-save">Lưu Thông Tin</button>
            </div>
        </form>
    </div>
</div>

<!-- 3. MODAL ỨNG VIÊN -->
<div id="modal-cand" class="modal-overlay">
    <div class="modal-box">
        <form method="POST" action="quan-tri-nhan-su.php">
            <input type="hidden" name="action" id="cand_action_type" value="add_candidate">
            <input type="hidden" name="old_id" id="cand_old_id">
            <input type="hidden" name="active_tab" id="cand_active_tab" value="candidates-tab">

            <div class="modal-header">
                <span class="modal-title" id="cand_modal_title">Thêm Hồ Sơ Ứng Viên</span>
                <button type="button" class="btn-close-modal" onclick="closeModal('modal-cand')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Mã ID Ứng Viên (Nhập số tùy chỉnh hoặc để trống)</label>
                    <input type="number" name="custom_id" id="cand_custom_id" placeholder="VD: 501, 502...">
                </div>
                <div class="form-group">
                    <label>Họ và Tên *</label>
                    <input type="text" name="fullname" id="cand_fullname" required placeholder="Trần Thị B">
                </div>
                <div class="form-group">
                    <label>Vị trí ứng tuyển *</label>
                    <input type="text" name="applied_position" id="cand_pos" required placeholder="VD: Nhân viên kinh doanh">
                </div>
                <div class="form-group">
                    <label>Số điện thoại *</label>
                    <input type="text" name="phone" id="cand_phone" required placeholder="09xxxxxxx">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="cand_email" placeholder="email@example.com">
                </div>
                <div class="form-group">
                    <label>Ngày phỏng vấn</label>
                    <input type="date" name="interview_date" id="cand_date">
                </div>
                <div class="form-group">
                    <label>Kết quả phỏng vấn</label>
                    <select name="interview_status" id="cand_status">
                        <option value="Chờ phỏng vấn">Chờ phỏng vấn</option>
                        <option value="Đã phỏng vấn">Đã phỏng vấn</option>
                        <option value="Trúng tuyển">Trúng tuyển</option>
                        <option value="Không đạt">Không đạt</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('modal-cand')">Hủy</button>
                <button type="submit" class="btn-save">Lưu Hồ Sơ</button>
            </div>
        </form>
    </div>
</div>

<!-- 4. MODAL TUYỂN DỤNG & KÝ HỢP ĐỒNG -->
<div id="modal-hire" class="modal-overlay">
    <div class="modal-box">
        <form method="POST" action="quan-tri-nhan-su.php">
            <input type="hidden" name="action" value="hire_candidate">
            <input type="hidden" name="candidate_id" id="hire_candidate_id">
            <input type="hidden" name="active_tab" value="candidates-tab">

            <div class="modal-header">
                <span class="modal-title">Xác Nhận Tuyển Dụng & Ký Hợp Đồng</span>
                <button type="button" class="btn-close-modal" onclick="closeModal('modal-hire')">&times;</button>
            </div>
            <div class="modal-body">
                <p id="hire_candidate_label" style="font-size: 0.9rem; font-weight: 600; color: #0f172a; background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0;"></p>
                
                <div class="form-group">
                    <label>Thời hạn hợp đồng *</label>
                    <select name="contract_months" id="hire_months" onchange="updateContractDates()" required>
                        <option value="1">1 Tháng (Thử việc)</option>
                        <option value="2" selected>2 Tháng (Thử việc)</option>
                        <option value="3">3 Tháng (Thử việc)</option>
                        <option value="6">6 Tháng (Chính thức)</option>
                        <option value="12">12 Tháng (1 Năm)</option>
                        <option value="24">24 Tháng (2 Năm)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Từ ngày hiệu lực *</label>
                    <input type="date" name="contract_start_date" id="hire_start_date" value="<?= $today ?>" onchange="updateContractDates()" required>
                </div>
                <div class="form-group">
                    <label>Đến ngày hết hạn *</label>
                    <input type="date" name="contract_end_date" id="hire_end_date" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('modal-hire')">Hủy</button>
                <button type="submit" class="btn-save" style="background: #10b981;">Xác Nhận Tuyển Dụng</button>
            </div>
        </form>
    </div>
</div>

<script>
function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    
    let targetTab = document.getElementById(tabId);
    let targetBtn = document.getElementById('btn-' + tabId);
    
    if (targetTab && targetBtn) {
        targetTab.classList.add('active');
        targetBtn.classList.add('active');
        localStorage.setItem('hr_current_tab', tabId);
    }
}

window.addEventListener('DOMContentLoaded', () => {
    let urlParams = new URLSearchParams(window.location.search);
    let urlTab = urlParams.get('tab');
    let serverActiveTab = "<?= htmlspecialchars($active_tab) ?>";
    let savedTab = urlTab || serverActiveTab || localStorage.getItem('hr_current_tab') || 'employees-tab';
    switchTab(savedTab);
});

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function openImportModal(type, title, tabName) {
    document.getElementById('import_type_input').value = type;
    document.getElementById('import_active_tab').value = tabName;
    document.getElementById('import_modal_title').innerText = 'Nhập ' + title + ' Từ File Excel';
    document.getElementById('import_hint_text').innerHTML = '📌 File Excel có thể chứa cả cột STT và cột Mã ID (hoặc chỉ cần Mã ID). Hệ thống sẽ tự động cập nhật đúng mã ID bạn đã gán.';
    document.getElementById('modal-import').style.display = 'flex';
}

function openAddEmp(defaultBranch = 'Trụ sở chính', tabName = 'employees-tab') {
    document.getElementById('emp_action_type').value = 'add_employee';
    document.getElementById('emp_active_tab').value = tabName;
    document.getElementById('emp_modal_title').innerText = (defaultBranch === 'Trụ sở chính') ? 'Thêm Nhân Viên Trụ Sở Chính' : 'Thêm Nhân Viên Chi Nhánh';
    document.getElementById('emp_old_id').value = '';
    document.getElementById('emp_custom_id').value = '';
    document.getElementById('emp_fullname').value = '';
    document.getElementById('emp_branch').value = (defaultBranch === 'Trụ sở chính') ? 'Trụ sở chính' : '';
    document.getElementById('emp_dept').value = '';
    document.getElementById('emp_pos').value = '';
    document.getElementById('emp_phone').value = '';
    document.getElementById('emp_email').value = '';
    document.getElementById('emp_status').value = 'Đang làm việc';
    document.getElementById('modal-emp').style.display = 'flex';
}

function openEditEmp(emp, tabName = 'employees-tab') {
    document.getElementById('emp_action_type').value = 'update_employee';
    document.getElementById('emp_active_tab').value = tabName;
    document.getElementById('emp_modal_title').innerText = 'Cập Nhật Thông Tin: ' + emp.fullname;
    document.getElementById('emp_old_id').value = emp.id;
    document.getElementById('emp_custom_id').value = emp.id;
    document.getElementById('emp_fullname').value = emp.fullname || '';
    document.getElementById('emp_branch').value = emp.branch || 'Trụ sở chính';
    
    let deptSelect = document.getElementById('emp_dept');
    let found = false;
    for (let i = 0; i < deptSelect.options.length; i++) {
        if (deptSelect.options[i].value === emp.department) {
            deptSelect.selectedIndex = i;
            found = true;
            break;
        }
    }
    if (!found && emp.department && emp.department !== 'Chưa phân bổ') {
        let opt = new Option(emp.department, emp.department, true, true);
        deptSelect.add(opt);
    } else if (!found) {
        deptSelect.value = '';
    }

    document.getElementById('emp_pos').value = emp.position || '';
    document.getElementById('emp_phone').value = emp.phone || '';
    document.getElementById('emp_email').value = emp.email || '';
    document.getElementById('emp_status').value = emp.status || 'Đang làm việc';
    document.getElementById('modal-emp').style.display = 'flex';
}

function openAddCand(tabName = 'candidates-tab') {
    document.getElementById('cand_action_type').value = 'add_candidate';
    document.getElementById('cand_active_tab').value = tabName;
    document.getElementById('cand_modal_title').innerText = 'Thêm Hồ Sơ Ứng Viên';
    document.getElementById('cand_old_id').value = '';
    document.getElementById('cand_custom_id').value = '';
    document.getElementById('cand_fullname').value = '';
    document.getElementById('cand_pos').value = '';
    document.getElementById('cand_phone').value = '';
    document.getElementById('cand_email').value = '';
    document.getElementById('cand_date').value = '';
    document.getElementById('cand_status').value = 'Chờ phỏng vấn';
    document.getElementById('modal-cand').style.display = 'flex';
}

function openEditCand(cand, tabName = 'candidates-tab') {
    document.getElementById('cand_action_type').value = 'update_candidate';
    document.getElementById('cand_active_tab').value = tabName;
    document.getElementById('cand_modal_title').innerText = 'Cập Nhật Ứng Viên: ' + cand.fullname;
    document.getElementById('cand_old_id').value = cand.id;
    document.getElementById('cand_custom_id').value = cand.id;
    document.getElementById('cand_fullname').value = cand.fullname || '';
    document.getElementById('cand_pos').value = cand.applied_position || '';
    document.getElementById('cand_phone').value = cand.phone || '';
    document.getElementById('cand_email').value = cand.email || '';
    document.getElementById('cand_date').value = cand.interview_date || '';
    document.getElementById('cand_status').value = cand.interview_status || 'Chờ phỏng vấn';
    document.getElementById('modal-cand').style.display = 'flex';
}

function openHireModal(candId, candName) {
    document.getElementById('hire_candidate_id').value = candId;
    document.getElementById('hire_candidate_label').innerText = 'Ứng viên: ' + candName;
    updateContractDates();
    document.getElementById('modal-hire').style.display = 'flex';
}

function updateContractDates() {
    let startDateStr = document.getElementById('hire_start_date').value;
    let months = parseInt(document.getElementById('hire_months').value) || 2;
    if (startDateStr) {
        let startDate = new Date(startDateStr);
        startDate.setMonth(startDate.getMonth() + months);
        let endDateStr = startDate.toISOString().split('T')[0];
        document.getElementById('hire_end_date').value = endDateStr;
    }
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php include 'footer.php'; ?>
