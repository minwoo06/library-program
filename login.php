<?php
require_once './config.php';

if (isLoggedIn()) {
    header('Location: ./index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 가이드 항목: CF(15) CSRF 토큰 검증
    verifyCsrfToken();

    $username = limitStr($_POST['username'] ?? '', 50);
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = '아이디와 비밀번호를 입력해주세요.';
    } else {
        // 가이드 항목: BF(12) 약한 문자열 강도 - 로그인 실패 횟수 제한
        $attempt = checkLoginAttempts($username);
        if ($attempt['locked']) {
            $error = "로그인 시도가 너무 많습니다. {$attempt['remain']}분 후 다시 시도해주세요.";
        } else {
            $conn = getDB();
            // 가이드 항목: SI(5) SQL 인젝션 방지 - Prepared Statement 사용
            $stmt = $conn->prepare("SELECT user_id, name, role, password FROM users WHERE username=?");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row) {
                $user_id   = $row['user_id'];
                $name      = $row['name'];
                $role      = $row['role'];
                $hashed_pw = $row['password'];

                // 가이드 항목: BF(12) bcrypt 검증 (기존 SHA-256도 호환)
                $pw_ok = password_verify($password, $hashed_pw)
                      || hash('sha256', $password) === $hashed_pw;

                if ($pw_ok) {
                    // SHA-256이면 bcrypt로 자동 업그레이드
                    if (!password_verify($password, $hashed_pw)) {
                        $new_hash = password_hash($password, PASSWORD_BCRYPT);
                        $upd = $conn->prepare("UPDATE users SET password=? WHERE username=?");
                        $upd->bind_param('ss', $new_hash, $username);
                        $upd->execute();
                        $upd->close();
                    }

                    $_SESSION['user_id']  = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['name']     = $name;
                    $_SESSION['role']     = $role;

                    // 가이드 항목: SF(19) 세션 고정 방지 + SE(16) 세션 예측 방지
                    setSessionSecurity();
                    resetLoginAttempts($username);
                    // 가이드 항목: 로그인 성공 이벤트 기록
                    error_log('[AUTH] 로그인 성공 - 계정: ' . $username . ' | IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ' | Time: ' . date('Y-m-d H:i:s'));
                    $conn->close();
                    header('Location: ./index.php');
                    exit;
                } else {
                    recordLoginFailure($username);
                    error_log('[AUTH] 로그인 실패 - 계정: ' . $username . ' | IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ' | Time: ' . date('Y-m-d H:i:s'));
                    $attempt2 = checkLoginAttempts($username);
                    $remain_attempts = MAX_LOGIN_ATTEMPTS - ($attempt2['attempts'] ?? 0);
                    if ($remain_attempts > 0) {
                        $error = "아이디 또는 비밀번호가 올바르지 않습니다. (남은 시도: {$remain_attempts}회)";
                    } else {
                        $error = '로그인 시도 횟수를 초과했습니다. 30분 후 다시 시도해주세요.';
                    }
                }
            } else {
                recordLoginFailure($username);
                error_log('[AUTH] 존재하지 않는 계정 시도 - 계정: ' . $username . ' | IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                // 가이드 항목: IL(9) 정보 누출 방지 - 계정 존재 여부 노출 금지
                $error = '아이디 또는 비밀번호가 올바르지 않습니다.';
            }
            $conn->close();
        }
    }
}

// 세션 타임아웃/보안 이상 메시지 처리
if (isset($_GET['err'])) {
    if ($_GET['err'] === 'security') {
        $error = '보안상의 이유로 로그아웃되었습니다. 다시 로그인해주세요.';
    } elseif ($_GET['err'] === 'timeout') {
        $error = '장시간 활동이 없어 자동 로그아웃되었습니다.';
    }
}

include './header.php';
?>

<div class="container" style="max-width:440px;">
  <div class="card">
    <h2>🔐 로그인</h2>
    <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>

    <form method="POST" action="./login.php">
      <?= csrfInput() ?>
      <div class="form-group">
        <label>아이디</label>
        <input type="text" name="username" placeholder="아이디 입력"
               value="<?= h($_POST['username'] ?? '') ?>" required autofocus maxlength="50">
      </div>
      <div class="form-group">
        <label>비밀번호</label>
        <input type="password" name="password" placeholder="비밀번호 입력"
               required maxlength="100">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:.5rem;">
        로그인
      </button>
    </form>

    <p style="text-align:center;margin-top:1rem;font-size:.88rem;color:#777;">
      계정이 없으신가요? <a href="./register.php" style="color:#1a237e;font-weight:700;">회원가입</a>
    </p>
    <?php /* [보안 조치] 로그인 화면 테스트 계정 안내 삭제 - 가이드 IL(9) 정보 누출 방지 */ ?>
  </div>
</div>

<?php include './footer.php'; ?>
