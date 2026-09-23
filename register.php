<?php
require_once './config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // [보안 2] CSRF 검증
    verifyCsrfToken();

    $conn = getDB();
    // [보안 5] 입력값 길이 제한
    $username  = limitStr($_POST['username'] ?? '', 20);
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $name      = limitStr($_POST['name'] ?? '', 50);
    $email     = limitStr($_POST['email'] ?? '', 100);
    $phone     = limitStr($_POST['phone'] ?? '', 20);

    if (!$username || !$password || !$name || !$email) {
        $error = '필수 항목을 모두 입력해주세요.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $username)) {
        $error = '아이디는 4~20자 영문/숫자/밑줄만 사용 가능합니다.';
    } else {
        // [보안 6] 비밀번호 강도 검사 (OWASP A07 Authentication Failures)
        // 공격: 단순 비밀번호로 브루트포스·사전공격 취약
        // 방어: 8자 이상 + 영문 + 숫자 + 특수문자 조합 강제
        $pw_error = validatePasswordStrength($password);
        if ($pw_error) {
            $error = $pw_error;
        } elseif ($password !== $password2) {
            $error = '비밀번호가 일치하지 않습니다.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = '이메일 형식이 올바르지 않습니다.';
        } else {
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $error = '이미 사용 중인 아이디입니다.';
            } else {
                $stmt->close();
                // bcrypt 해시 (password_hash 사용)
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare(
                    "INSERT INTO users (username, password, name, email, phone) VALUES (?,?,?,?,?)"
                );
                $stmt->bind_param('sssss', $username, $hashed, $name, $email, $phone);
                if ($stmt->execute()) {
                    $success = '회원가입이 완료되었습니다! 로그인 해주세요.';
                    // [보안 9] 회원가입 이벤트 로그
                    error_log('[AUTH] 회원가입 완료 - 계정: ' . $username . ' | IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ' | Time: ' . date('Y-m-d H:i:s'));
                } else {
                    $error = '회원가입 중 오류가 발생했습니다.';
                }
            }
            $stmt->close();
        }
    }
    $conn->close();
}

include './header.php';
?>

<div class="container" style="max-width:520px;">
  <div class="card">
    <h2>📝 회원가입</h2>

    <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><?= h($success) ?></div>
      <a href="./login.php" class="btn btn-primary">로그인 하러 가기</a>
    <?php else: ?>

    <form method="POST" action="./register.php">
      <?= csrfInput() ?>
      <div class="form-group">
        <label>아이디 <span style="color:red">*</span></label>
        <input type="text" name="username" placeholder="4~20자 영문/숫자" maxlength="20"
               value="<?= h($_POST['username'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>비밀번호 <span style="color:red">*</span></label>
        <input type="password" name="password" placeholder="8자 이상, 영문+숫자+특수문자 포함" required maxlength="100">
        <small style="color:#888;font-size:.78rem;">영문, 숫자, 특수문자(!@#$% 등)를 포함하여 8자 이상 입력하세요.</small>
      </div>
      <div class="form-group">
        <label>비밀번호 확인 <span style="color:red">*</span></label>
        <input type="password" name="password2" placeholder="비밀번호 재입력" required maxlength="100">
      </div>
      <div class="form-group">
        <label>이름 <span style="color:red">*</span></label>
        <input type="text" name="name" placeholder="실명 입력" maxlength="50"
               value="<?= h($_POST['name'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>이메일 <span style="color:red">*</span></label>
        <input type="email" name="email" placeholder="example@email.com"
               value="<?= h($_POST['email'] ?? '') ?>" required maxlength="100">
      </div>
      <div class="form-group">
        <label>전화번호</label>
        <input type="tel" name="phone" placeholder="010-0000-0000"
               value="<?= h($_POST['phone'] ?? '') ?>" maxlength="20">
      </div>
      <div class="form-group">
        <label>관심 카테고리 (선택)</label>
        <div style="display:flex;flex-wrap:wrap;gap:.6rem;margin-top:.3rem;">
          <?php foreach(['소설','과학','역사','자기계발','컴퓨터/IT'] as $cat): ?>
          <label style="display:flex;align-items:center;gap:.3rem;font-size:.9rem;font-weight:400;">
            <input type="checkbox" name="interests[]" value="<?= h($cat) ?>"> <?= h($cat) ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:.5rem;">
        가입하기
      </button>
    </form>
    <p style="text-align:center;margin-top:1rem;font-size:.88rem;color:#777;">
      이미 계정이 있으신가요? <a href="./login.php" style="color:#1a237e;font-weight:700;">로그인</a>
    </p>
    <?php endif; ?>
  </div>
</div>

<?php include './footer.php'; ?>
