<?php
// [버그수정] require_once './header.php' 보다 먼저 권한 체크
// 기존: header.php(HTML 출력) → requireAdmin() → header() 리다이렉트 불가
// 수정: config.php만 먼저 로드 → 권한/book 검증 → header.php(HTML 출력)
require_once './config.php';
requireAdmin();

$conn = getDB();
$error = '';
$success = '';

$book_id = (int)($_GET['id'] ?? 0);
if (!$book_id) { header('Location: ./book_list.php'); exit; }

// 도서 정보 조회
$stmt = $conn->prepare("SELECT * FROM books WHERE book_id=?");
$stmt->bind_param('i', $book_id);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$book) { header('Location: ./book_list.php'); exit; }

// 수정 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken(); // [보안 2] CSRF 검증
    $title       = limitStr($_POST['title'] ?? '', 200);
    $author      = limitStr($_POST['author'] ?? '', 100);
    $publisher   = limitStr($_POST['publisher'] ?? '', 100);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $description = limitStr(trim($_POST['description'] ?? ''), 1000);
    $status      = in_array($_POST['status'], ['available','rented']) ? $_POST['status'] : 'available';

    if (!$title || !$author || !$publisher || !$category_id) {
        $error = '필수 항목을 모두 입력해주세요.';
    } else {
        $stmt = $conn->prepare(
            "UPDATE books SET title=?, author=?, publisher=?, category_id=?, description=?, status=? WHERE book_id=?"
        );
        $stmt->bind_param('sssissi', $title, $author, $publisher, $category_id, $description, $status, $book_id);
        if ($stmt->execute()) {
            $success = '도서 정보가 수정되었습니다.';
            $book = array_merge($book, compact('title','author','publisher','category_id','description','status'));
        } else {
            $error = '수정 중 오류가 발생했습니다.';
        }
        $stmt->close();
    }
}

$cats = $conn->query("SELECT * FROM categories ORDER BY category_id");

// 모든 검증 완료 후 HTML 출력 시작
include './header.php';
?>

<div class="container" style="max-width:600px;">
  <div class="page-title">✏️ 도서 수정</div>
  <div class="card">
    <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

    <form method="POST" action="./book_edit.php?id=<?= $book_id ?>">
      <?= csrfInput() ?>
      <div class="form-group">
        <label>도서 제목 <span style="color:red">*</span></label>
        <input type="text" name="title" required value="<?= h($book['title']) ?>">
      </div>
      <div class="form-group">
        <label>저자 <span style="color:red">*</span></label>
        <input type="text" name="author" required value="<?= h($book['author']) ?>">
      </div>
      <div class="form-group">
        <label>출판사 <span style="color:red">*</span></label>
        <input type="text" name="publisher" required value="<?= h($book['publisher']) ?>">
      </div>
      <!-- select/option -->
      <div class="form-group">
        <label>카테고리 <span style="color:red">*</span></label>
        <select name="category_id" required>
          <?php $cats->data_seek(0); while($c = $cats->fetch_assoc()): ?>
          <option value="<?= $c['category_id'] ?>"
            <?= $book['category_id'] == $c['category_id'] ? 'selected' : '' ?>>
            <?= h($c['category_name']) ?>
          </option>
          <?php endwhile; ?>
        </select>
      </div>
      <!-- textarea -->
      <div class="form-group">
        <label>도서 소개</label>
        <textarea name="description"><?= h($book['description']) ?></textarea>
      </div>
      <!-- radio button: 상태 변경 -->
      <div class="form-group">
        <label>대여 상태</label>
        <div style="display:flex;gap:1.5rem;margin-top:.3rem;">
          <label style="font-weight:400;display:flex;align-items:center;gap:.4rem;">
            <input type="radio" name="status" value="available"
                   <?= $book['status']==='available' ? 'checked' : '' ?>> 대여가능
          </label>
          <label style="font-weight:400;display:flex;align-items:center;gap:.4rem;">
            <input type="radio" name="status" value="rented"
                   <?= $book['status']==='rented' ? 'checked' : '' ?>> 대여불가
          </label>
        </div>
      </div>

      <div style="display:flex;gap:.8rem;">
        <button type="submit" class="btn btn-primary">저장</button>
        <a href="./book_list.php" class="btn btn-secondary">목록으로</a>
      </div>
    </form>
  </div>
</div>

<?php include './footer.php'; $conn->close(); ?>
