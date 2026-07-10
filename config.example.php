<?php
// ============================================================
// config.example.php
// 이 파일을 복사해서 config.php 로 이름 변경 후 사용하세요
// config.php 는 .gitignore 에 의해 깃허브에 올라가지 않습니다
// ============================================================
if (basename($_SERVER['PHP_SELF']) === 'config.php') {
    http_response_code(403);
    exit('접근이 거부되었습니다.');
}

define('DB_HOST',    '127.0.0.1');
define('DB_USER',    'root');
define('DB_PASS',    '');       // ← 본인 MySQL 비밀번호 입력
define('DB_NAME',    'library');
define('DB_CHARSET', 'utf8mb4');

// 아래 내용은 config.php 와 동일하게 유지하세요
// (나머지 함수들은 config.php 원본 참고)
?>
