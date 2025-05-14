<?php
require 'config.php';

$error = '';
$success = '';
$remaining = 20;
$canSubmit = true;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->beginTransaction();

        // 获取表单数据
        $name = trim($_POST['name']);
        $count = (int)$_POST['count'];
        $today = date('Y-m-d');
        $currentWeek = date('W');

        // 验证输入
        if (empty($name)) throw new Exception("姓名不能为空");
        if ($count < 1 || $count > 20) throw new Exception("借阅数量需在1-20本之间");

        // 处理学生信息
        $stmt = $pdo->prepare("SELECT id FROM students WHERE name = ? FOR UPDATE");
        $stmt->execute([$name]);
        $student = $stmt->fetch();

        if (!$student) {
            $stmt = $pdo->prepare("INSERT INTO students (name) VALUES (?)");
            $stmt->execute([$name]);
            $student_id = $pdo->lastInsertId();
        } else {
            $student_id = $student['id'];
        }

        // 检查当日提交
        $stmt = $pdo->prepare("SELECT id FROM borrow_records 
                             WHERE student_id = ? AND record_date = ?
                             LIMIT 1");
        $stmt->execute([$student_id, $today]);
        if ($stmt->fetch()) {
            throw new Exception("今日已提交过，请明天再来");
        }

        // 处理图片上传
        $uploadDir = 'uploads/' . date('Y/m/d/');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $imagePaths = [];
        foreach (['image1', 'image2'] as $fileKey) {
            if ($_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("请上传两张图片");
            }

            $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
            $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExts)) {
                throw new Exception("只支持JPG/PNG/GIF格式");
            }

            $newFilename = hash('sha256', uniqid().$_FILES[$fileKey]['tmp_name']) . ".$ext";
            $dest = $uploadDir . $newFilename;

            if (!move_uploaded_file($_FILES[$fileKey]['tmp_name'], $dest)) {
                throw new Exception("文件上传失败");
            }
            $imagePaths[] = $dest;
        }

        // 插入记录
        $stmt = $pdo->prepare("INSERT INTO borrow_records 
            (student_id, week_number, borrowed_count, image1, image2, record_date)
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $student_id,
            $currentWeek,
            $count,
            $imagePaths[0],
            $imagePaths[1],
            $today
        ]);

        // 更新总借阅量
        $stmt = $pdo->prepare("UPDATE students SET total_borrowed = total_borrowed + ? 
                              WHERE id = ?");
        $stmt->execute([$count, $student_id]);

        $pdo->commit();
        $success = "提交成功！本次借阅{$count}本";
    }
} catch (Exception $e) {
    $pdo->rollBack();
    $error = $e->getMessage();
}

// 获取本周进度
if (isset($student_id)) {
    $stmt = $pdo->prepare("SELECT SUM(borrowed_count) as total 
        FROM borrow_records 
        WHERE student_id = ? AND week_number = ?");
    $stmt->execute([$student_id, date('W')]);
    $total = (int)$stmt->fetch()['total'];
    $remaining = max(0, 20 - $total);
}

// 检查当日提交状态
if (isset($student_id)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM borrow_records 
                         WHERE student_id = ? AND record_date = ?");
    $stmt->execute([$student_id, date('Y-m-d')]);
    $canSubmit = $stmt->fetch()['cnt'] == 0;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>图书借阅系统</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 20px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        .progress-bar { height: 20px; background: #e9ecef; border-radius: 10px; overflow: hidden; }
        .progress { height: 100%; background: #38d9a9; transition: width 0.3s; }
        .upload-box { border: 2px dashed #ced4da; padding: 30px; text-align: center; border-radius: 8px; cursor: pointer; transition: 0.3s; }
        .upload-box:hover { border-color: #4dabf7; background: #f8f9fa; }
        .preview-img { max-width: 150px; margin: 10px; border-radius: 6px; }
        .btn { padding: 12px 24px; border: none; border-radius: 6px; background: #4dabf7; color: white; cursor: pointer; }
        .btn:disabled { background: #adb5bd; }
        .example-images img { width: 200px; border-radius: 8px; }
        .example-section { margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-book"></i> 图书借阅登记</h1>

        <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php elseif ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="progress-container">
            <h3>本周进度（目标：20本）</h3>
            <div class="progress-bar">
                <div class="progress" style="width: <?= ((20 - $remaining)/20)*100 ?>%"></div>
            </div>
            <p>剩余需借阅：<?= $remaining ?> 本</p>
        </div>

        <div class="example-section">
            <h4><i class="fas fa-image"></i> 示例图片：</h4>
            <div class="example-images">
                <img src="examples/1.png" alt="示例1">
                <img src="examples/2.png" alt="示例2">
            </div>
            <p style="color:#666; margin-top:10px;">（请参考示例图片格式上传）</p>
        </div>

        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label>姓名：</label>
                <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>本次借阅数量：</label>
                <input type="number" name="count" class="form-control" min="1" max="20" required value="<?= htmlspecialchars($_POST['count'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>上传证明：</label>
                <div class="upload-container">
                    <div class="upload-box" onclick="document.getElementById('image1').click()">
                        <div id="preview1"></div>
                        <span>点击上传第一张证明</span>
                        <input type="file" name="image1" id="image1" hidden required>
                    </div>
                    <div class="upload-box" onclick="document.getElementById('image2').click()">
                        <div id="preview2"></div>
                        <span>点击上传第二张证明</span>
                        <input type="file" name="image2" id="image2" hidden required>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn" <?= !$canSubmit ? 'disabled' : '' ?>>
                <i class="fas fa-check"></i> 提交
            </button>

            <?php if (!$canSubmit): ?>
            <p style="color:#ff6b6b; margin-top:15px;">
                <i class="fas fa-info-circle"></i> 今日已提交，请明天再来
            </p>
            <?php endif; ?>
        </form>
    </div>

    <script>
        // 图片预览功能
        function handleFilePreview(input, previewId) {
            const file = input.files[0];
            const preview = document.getElementById(previewId);
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" class="preview-img">`;
                }
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '';
            }
        }

        document.getElementById('image1').addEventListener('change', function() {
            handleFilePreview(this, 'preview1');
        });

        document.getElementById('image2').addEventListener('change', function() {
            handleFilePreview(this, 'preview2');
        });
    </script>
</body>
</html>