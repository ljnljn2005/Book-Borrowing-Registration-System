# Book-Borrowing-Registration-System
## 介绍
借书登记系统，包含一周借阅任务（默认20天）
admin.php是后台（显示借书人和时间、图片），student.php是学生平台（上传登记图片）
examples中1.png和2.png是示例上传图片，可以修改
uploads目录中会根据上传文件的时间自动分目录储存
## 安装
mysql部分需要手动输入，数据库密码在config.php修改
```
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    total_borrowed INT DEFAULT 0
);

CREATE TABLE borrow_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    week_number INT,
    borrowed_count INT,
    image1 VARCHAR(255),
    image2 VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id)
);
ALTER TABLE borrow_records 
ADD COLUMN record_date DATE NOT NULL AFTER created_at;
```
