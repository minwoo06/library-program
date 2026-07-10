<?php
require_once './config.php';
requireAdmin();

$conn = getDB();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken(); // [보안 2] CSRF 검증
    // [보안 5] 입력값 길이 제한
    $title       = limitStr($_POST['title'] ?? '', 200);
    $author      = limitStr($_POST['author'] ?? '', 100);
    $publisher   = limitStr($_POST['publisher'] ?? '', 100);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $description = limitStr($_POST['description'] ?? '', 1000);

    if (!$title || !$author || !$publisher || !$category_id) {
        $error = '필수 항목을 모두 입력해주세요.';
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO books (title, author, publisher, category_id, description) VALUES (?,?,?,?,?)"
        );
        $stmt->bind_param('sssis', $title, $author, $publisher, $category_id, $description);
        if ($stmt->execute()) {
            $success = '도서가 등록되었습니다.';
        } else {
            $error = '등록 중 오류가 발생했습니다.';
        }
        $stmt->close();
    }
}

$cats = $conn->query("SELECT * FROM categories ORDER BY category_id");

include './header.php';
?>

<div class="container" style="max-width:600px;">
  <div class="page-title">📗 도서 등록</div>
  <div class="card">
    <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

    <form method="POST" action="./book_add.php">
      <?= csrfInput() ?>
      <div class="form-group">
        <label>도서 제목 <span style="color:red">*</span></label>
        <input type="text" name="title" placeholder="도서 제목" required maxlength="200"
               value="<?= h($_POST['title'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>저자 <span style="color:red">*</span></label>
        <input type="text" name="author" placeholder="저자명" required maxlength="100"
               value="<?= h($_POST['author'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>출판사 <span style="color:red">*</span></label>
        <input type="text" name="publisher" placeholder="출판사명" required maxlength="100"
               value="<?= h($_POST['publisher'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>카테고리 <span style="color:red">*</span></label>
        <select name="category_id" required>
          <option value="">선택하세요</option>
          <?php while($c = $cats->fetch_assoc()): ?>
          <option value="<?= $c['category_id'] ?>"
            <?= (($_POST['category_id'] ?? '') == $c['category_id']) ? 'selected' : '' ?>>
            <?= h($c['category_name']) ?>
          </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="form-group">
        <label>도서 소개</label>
        <textarea name="description" placeholder="도서 소개를 입력하세요" maxlength="1000"><?= h($_POST['description'] ?? '') ?></textarea>
      </div>
      <div style="display:flex;gap:.8rem;">
        <button type="submit" class="btn btn-primary">등록하기</button>
        <a href="./book_list.php" class="btn btn-secondary">목록으로</a>
      </div>
    </form>
  </div>
</div>

<?php include './footer.php'; $conn->close(); ?>
