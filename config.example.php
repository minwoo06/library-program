<?php
// ============================================================
// config.example.php - 비밀번호 없는 설정 예제 및 공통 보안 함수
// 이 파일을 config.php로 복사하고 DB 설정을 입력하세요. config.php는 Git에서 제외됩니다.
// 직접 URL 접근 차단
// ============================================================
if (in_array(basename($_SERVER['PHP_SELF']), ['config.php', 'config.example.php'], true)) {
    http_response_code(403);
    exit('접근이 거부되었습니다.');
}

// ── DB 설정 ──────────────────────────────────────────────────
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'library');
define('DB_CHARSET', 'utf8mb4');

// ── [보안 7] HTTP 보안 헤더 설정 ─────────────────────────────
// 가이드 항목: DI(디렉터리 인덱싱), XS(크로스사이트 스크립팅), IL(정보 누출)
// 공격: Clickjacking, MIME 스니핑, XSS 등 브라우저 레벨 공격
// 방어: 보안 관련 HTTP 응답 헤더 강제 설정
function setSecurityHeaders() {
    header('X-Frame-Options: DENY');                           // Clickjacking 방지
    header('X-Content-Type-Options: nosniff');                 // MIME 스니핑 방지
    header('X-XSS-Protection: 1; mode=block');                // XSS 필터 강제 활성화
    header('Referrer-Policy: strict-origin-when-cross-origin');// Referrer 정보 최소화
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'unsafe-inline'; img-src 'self' data:;");
}

// ── 세션 시작 (보안 옵션 적용) ────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);   // JS에서 세션 쿠키 접근 차단 (XSS 세션 탈취 방지)
    ini_set('session.use_strict_mode', 1);   // 세션 고정 공격 방지
    ini_set('session.cookie_samesite', 'Strict'); // CSRF 방어 강화 (가이드 CF항목)
    // ── [보안 SC] 세션 타임아웃 설정 ────────────────────────
    // 가이드 항목: SC(18) 불충분한 세션 만료
    // 공격: 만료되지 않은 세션을 탈취하여 불법 접근
    // 방어: 비활성 30분 후 세션 자동 만료 (가이드 권고: 10분, 도서관 특성상 30분 적용)
    ini_set('session.gc_maxlifetime', 1800);
    session_set_cookie_params(['lifetime' => 1800]);
    session_start();
}

// 보안 헤더 세션 시작 후 즉시 설정
setSecurityHeaders();

// ── DB 연결 ───────────────────────────────────────────────────
function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        // 가이드 항목: IL(9) 정보 누출 방지 - DB 오류 상세 정보 숨김
        error_log('[DB ERROR] 연결 실패: ' . $conn->connect_error . ' | IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        die('서비스에 일시적인 문제가 발생했습니다. 잠시 후 다시 시도해주세요.');
    }
    $conn->set_charset(DB_CHARSET);
    return $conn;
}

// ── 로그인/권한 확인 ──────────────────────────────────────────
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ./login.php');
        exit;
    }
}

// 가이드 항목: IN(17) 불충분한 인가 - 관리자 페이지 접근 통제
function requireAdmin() {
    if (!isLoggedIn()) {
        // [보안 9] 비인가 접근 시도 로그 기록
        error_log('[SECURITY] 비로그인 관리자 페이지 접근 시도 - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ' | URI: ' . ($_SERVER['REQUEST_URI'] ?? ''));
        header('Location: ./login.php');
        exit;
    }
    if (!isAdmin()) {
        error_log('[SECURITY] 권한 없는 관리자 페이지 접근 시도 - UserID: ' . ($_SESSION['user_id'] ?? 'unknown') . ' | IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ' | URI: ' . ($_SERVER['REQUEST_URI'] ?? ''));
        header('Location: ./index.php');
        exit;
    }
}

// ── [보안 1] 세션 하이재킹 방지 ──────────────────────────────
// 가이드 항목: SE(16) 세션 예측, SF(19) 세션 고정
// 공격: 탈취한 세션 쿠키로 다른 기기에서 접근 시도
// 방어: 로그인 시 저장한 IP와 User-Agent가 다르면 강제 로그아웃
function checkSessionSecurity() {
    if (!isLoggedIn()) return;

    // ── [보안 SC] 세션 타임아웃 체크 ────────────────────────
    // 가이드 항목: SC(18) 불충분한 세션 만료
    if (isset($_SESSION['last_activity'])) {
        if ((time() - $_SESSION['last_activity']) > 1800) {
            error_log('[SESSION] 세션 타임아웃 - UserID: ' . ($_SESSION['user_id'] ?? 'unknown') . ' | IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            session_destroy();
            header('Location: ./login.php?err=timeout');
            exit;
        }
    }
    $_SESSION['last_activity'] = time(); // 마지막 활동 시각 갱신

    $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $current_ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (isset($_SESSION['ip'], $_SESSION['ua'])) {
        if ($_SESSION['ip'] !== $current_ip || $_SESSION['ua'] !== $current_ua) {
            // 가이드 항목: SE(16) 세션 예측 / 세션 하이재킹 의심
            error_log('[SECURITY] 세션 하이재킹 의심 - IP: ' . $current_ip . ' | UA: ' . $current_ua . ' | UserID: ' . ($_SESSION['user_id'] ?? 'unknown'));
            session_destroy();
            header('Location: ./login.php?err=security');
            exit;
        }
    }
}

// 로그인 성공 시 세션에 IP/UA 저장 + 세션 ID 재생성
function setSessionSecurity() {
    $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
    $_SESSION['ua'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $_SESSION['last_activity'] = time();
    // 가이드 항목: SF(19) 세션 고정 공격 방지 - 로그인 시 세션 ID 재생성
    session_regenerate_id(true);
}

// ── [보안 2] CSRF 토큰 ────────────────────────────────────────
// 가이드 항목: CF(15) 크로스사이트 리퀘스트 변조(CSRF)
// 공격: 다른 사이트에서 사용자 모르게 폼 제출 유도
// 방어: 폼마다 서버 발급 난수 토큰 포함, 제출 시 검증
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken() {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        error_log('[SECURITY] CSRF 토큰 불일치 - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ' | URI: ' . ($_SERVER['REQUEST_URI'] ?? ''));
        http_response_code(403);
        die('유효하지 않은 요청입니다. (CSRF 토큰 불일치)');
    }
}

function csrfInput() {
    return '<input type="hidden" name="csrf_token" value="' . h(generateCsrfToken()) . '">';
}

// ── [보안 3] Brute Force(무차별 대입 공격) 방지 ──────────────
// 가이드 항목: BF(12) 약한 문자열 강도 - 일정 횟수 이상 인증 실패 시 잠금
// 공격: 비밀번호 무한 반복 시도로 계정 탈취
// 방어: 5회 실패 시 30분 잠금
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 1800);

function checkLoginAttempts($username) {
    $key_a = 'login_attempts_' . md5($username);
    $key_t = 'login_lockout_'  . md5($username);
    $attempts  = $_SESSION[$key_a] ?? 0;
    $lock_time = $_SESSION[$key_t] ?? 0;
    if ($lock_time && (time() - $lock_time) > LOGIN_LOCKOUT_TIME) {
        $_SESSION[$key_a] = 0;
        $_SESSION[$key_t] = 0;
        return ['locked' => false, 'attempts' => 0];
    }
    if ($attempts >= MAX_LOGIN_ATTEMPTS && $lock_time) {
        $remain = LOGIN_LOCKOUT_TIME - (time() - $lock_time);
        return ['locked' => true, 'remain' => ceil($remain / 60)];
    }
    return ['locked' => false, 'attempts' => $attempts];
}

function recordLoginFailure($username) {
    $key_a = 'login_attempts_' . md5($username);
    $key_t = 'login_lockout_'  . md5($username);
    $_SESSION[$key_a] = ($_SESSION[$key_a] ?? 0) + 1;
    if ($_SESSION[$key_a] >= MAX_LOGIN_ATTEMPTS) {
        $_SESSION[$key_t] = time();
        error_log('[SECURITY] 로그인 잠금 발생 - 계정: ' . $username . ' | IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ' | Time: ' . date('Y-m-d H:i:s'));
    }
}

function resetLoginAttempts($username) {
    $_SESSION['login_attempts_' . md5($username)] = 0;
    $_SESSION['login_lockout_'  . md5($username)] = 0;
}

// ── [보안 4] XSS 방지 출력 함수 ─────────────────────────────
// 가이드 항목: XS(11) 크로스사이트 스크립팅
// 공격: 입력값에 <script> 삽입 후 다른 사용자 브라우저에서 실행
// 방어: 모든 출력에 htmlspecialchars 적용 (ENT_QUOTES로 따옴표 포함 이스케이프)
function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// ── [보안 5] 입력값 길이 제한 ────────────────────────────────
// 가이드 항목: BO(1) 버퍼 오버플로우
// 공격: 비정상적으로 긴 입력값으로 오류 유발 및 정보 노출
// 방어: 최대 길이 제한으로 버퍼 오버플로우 예방
function limitStr($str, $max = 200) {
    return substr(trim($str), 0, $max);
}

// ── [보안 6] 비밀번호 강도 검사 ──────────────────────────────
// 가이드 항목: BF(12) 약한 문자열 강도
// 공격: 단순 비밀번호로 인한 브루트포스·사전 공격 취약
// 방어: 2종류 이상 조합 10자 이상 또는 3종류 이상 조합 8자 이상 (가이드 권고 기준)
function validatePasswordStrength($password) {
    if (strlen($password) < 8) {
        return '비밀번호는 8자 이상이어야 합니다.';
    }
    if (!preg_match('/[A-Za-z]/', $password)) {
        return '비밀번호에 영문자를 포함해야 합니다.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return '비밀번호에 숫자를 포함해야 합니다.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return '비밀번호에 특수문자(!@#$% 등)를 포함해야 합니다.';
    }
    return '';
}

// 모든 페이지 로드 시 세션 보안 검사 자동 실행
checkSessionSecurity();
?>
