<?php
require 'config.php';

$stmt = $pdo->query("
    SELECT 
        s.id AS student_id,  -- 使用students表的id作为学号
        s.name, 
        s.total_borrowed, 
        MAX(br.created_at) AS last_borrow,
        GROUP_CONCAT(CONCAT(br.image1, ',', br.image2) SEPARATOR ';') as images 
    FROM students s
    LEFT JOIN borrow_records br ON s.id = br.student_id  -- 正确关联学生ID
    GROUP BY s.id
");
$students = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>管理后台</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-chart-line"></i> 借阅统计管理</h1>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th class="student-id">学号</th>  <!-- 新增学号列 -->
                    <th>姓名</th>
                    <th>总借阅量</th>
                    <th>最后借阅时间</th>  <!-- 修正列名 -->
                    <th>图片记录</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                <tr>
                    <td class="student-id"><?= htmlspecialchars($student['student_id']) ?></td>
                    <td><?= htmlspecialchars($student['name']) ?></td>
                    <td><?= $student['total_borrowed'] ?? 0 ?></td>
                    <td>
                        <?php if ($student['last_borrow']): ?>
                            <?= date('m-d H:i', strtotime($student['last_borrow'])) ?>
                        <?php else: ?>
                            无记录
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="image-gallery">
                            <?php if ($student['images']): ?>
                                <?php foreach (explode(';', $student['images']) as $imagePair): ?>
                                    <?php 
                                    list($img1, $img2) = explode(',', $imagePair);
                                    ?>
                                    <div class="image-preview">
                                        <a href="<?= $img1 ?>" target="_blank" title="查看大图">
                                            <img src="<?= $img1 ?>" alt="借阅证明1">
                                        </a>
                                        <a href="<?= $img2 ?>" target="_blank" title="查看大图">
                                            <img src="<?= $img2 ?>" alt="借阅证明2">
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span>暂无图片</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>