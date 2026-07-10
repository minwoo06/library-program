<?php
// 대여 처리는 HTML 출력 전에 먼저 처리해야 header() 리다이렉트가 작동함
require_once './config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rent_book_id']) && isLoggedIn()) {
    verifyCsrfToken(); // [보안 2] CSRF 검증
    $conn = getDB();
    $book_id     = (int)$_POST['rent_book_id'];
    $period_days = in_array((int)$_POST['period_days'], [7,14,21]) ? (int)$_POST['period_days'] : 7;
    $rent_date   = date('Y-m-d');
    $due_date    = date('Y-m-d', strtotime("+{$period_days} days"));
    $user_id     = $_SESSION['user_id'];

    $chk = $conn->prepare("SELECT status FROM books WHERE book_id=?");
    $chk->bind_param('i', $book_id);
    $chk->execute();
    $chk->bind_result($status);
    $chk->fetch();
    $chk->close();

    if ($status === 'available') {
        $conn->begin_transaction();
        try {
            $s1 = $conn->prepare(
                "INSERT INTO rentals (user_id,book_id,rent_date,due_date,period_days) VALUES (?,?,?,?,?)"
            );
            $s1->bind_param('iissi', $user_id, $book_id, $rent_date, $due_date, $period_days);
            $s1->execute();
            $s1->close();

            $s2 = $conn->prepare("UPDATE books SET status='rented' WHERE book_id=?");
            $s2->bind_param('i', $book_id);
            $s2->execute();
            $s2->close();

            $conn->commit();
            $_SESSION['msg'] = '대여가 완료되었습니다!';
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['msg_error'] = '대여 중 오류가 발생했습니다.';
        }
    } else {
        $_SESSION['msg_error'] = '이미 대여 중인 도서입니다.';
    }
    $conn->close();
    header('Location: ./book_list.php');
    exit;
}

require_once './header.php';
$conn = getDB();

// 카테고리 필터
$cat_filter = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$search     = trim($_GET['search'] ?? '');

// 도서 목록 조회 (JOIN: books + categories)
$where = '1=1';
$params = [];
$types  = '';
if ($cat_filter > 0) {
    $where .= ' AND b.category_id=?';
    $params[] = $cat_filter;
    $types   .= 'i';
}
if ($search !== '') {
    $where .= ' AND (b.title LIKE ? OR b.author LIKE ?)';
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

$sql = "SELECT b.book_id, b.title, b.author, b.publisher, b.status, c.category_name
        FROM books b
        JOIN categories c ON b.category_id = c.category_id
        WHERE {$where}
        ORDER BY b.book_id DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$books = $stmt->get_result();
$stmt->close();

// 카테고리 목록
$cats = $conn->query("SELECT * FROM categories ORDER BY category_id");
?>

<div class="container">
  <div class="page-title">📖 도서 목록</div>

  <?php if (isset($_SESSION['msg'])): ?>
    <div class="alert alert-success"><?= h($_SESSION['msg']) ?></div>
    <?php unset($_SESSION['msg']); ?>
  <?php endif; ?>
  <?php if (isset($_SESSION['msg_error'])): ?>
    <div class="alert alert-error"><?= h($_SESSION['msg_error']) ?></div>
    <?php unset($_SESSION['msg_error']); ?>
  <?php endif; ?>

  <!-- 검색 & 필터 -->
  <div class="card" style="padding:1.2rem;">
    <form method="GET" action="./book_list.php" style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:flex-end;">
      <div class="form-group" style="flex:1;min-width:180px;margin:0;">
        <label>도서 검색</label>
        <!-- text -->
        <input type="text" name="search" placeholder="제목 또는 저자 검색"
               value="<?= h($search) ?>">
      </div>
      <div class="form-group" style="min-width:140px;margin:0;">
        <label>카테고리</label>
        <!-- select/option (dropdown list) -->
        <select name="cat">
          <option value="0">전체</option>
          <?php $cats->data_seek(0); while($c = $cats->fetch_assoc()): ?>
          <option value="<?= $c['category_id'] ?>"
            <?= $cat_filter == $c['category_id'] ? 'selected' : '' ?>>
            <?= h($c['category_name']) ?>
          </option>
          <?php endwhile; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary" style="height:38px;">검색</button>
      <a href="./book_list.php" class="btn btn-secondary" style="height:38px;line-height:1.6;">초기화</a>
    </form>
  </div>

  <?php if (!isLoggedIn()): ?>
  <div class="alert" style="background:#fff8e1;color:#e65100;border:1px solid #ffcc02;">
    ℹ️ 로그인 후 도서를 대여할 수 있습니다.
    <a href="./login.php" style="font-weight:700;color:#e65100;">로그인 →</a>
  </div>
  <?php endif; ?>

  <div class="card">
    <table>
      <thead>
        <tr>
          <th>#</th><th>제목</th><th>저자</th><th>출판사</th><th>카테고리</th><th>상태</th>
          <?php if (isLoggedIn()): ?><th>대여</th><?php endif; ?>
          <?php if (isAdmin()): ?><th>관리</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php $i=1; while ($row = $books->fetch_assoc()): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= h($row['title']) ?></td>
          <td><?= h($row['author']) ?></td>
          <td><?= h($row['publisher']) ?></td>
          <td><?= h($row['category_name']) ?></td>
          <td>
            <span class="badge <?= $row['status']==='available' ? 'badge-ok' : 'badge-rented' ?>">
              <?= $row['status']==='available' ? '대여가능' : '대여불가' ?>
            </span>
          </td>
          <?php if (isLoggedIn()): ?>
          <td>
            <?php if ($row['status']==='available'): ?>
            <form method="POST" action="./book_list.php" style="display:inline;">
              <?= csrfInput() ?>
              <input type="hidden" name="rent_book_id" value="<?= $row['book_id'] ?>">
              <!-- radio button: 대여 기간 선택 -->
              <select name="period_days" style="padding:.25rem;font-size:.8rem;border-radius:4px;border:1px solid #ccc;">
                <option value="7">7일</option>
                <option value="14">14일</option>
                <option value="21">21일</option>
              </select>
              <button type="submit" class="btn btn-success btn-sm">대여</button>
            </form>
            <?php else: ?>
              <span style="color:#aaa;font-size:.82rem;">불가</span>
            <?php endif; ?>
          </td>
          <?php endif; ?>
          <?php if (isAdmin()): ?>
          <td>
            <a href="./book_edit.php?id=<?= $row['book_id'] ?>" class="btn btn-secondary btn-sm">수정</a>
            <form method="POST" action="./book_delete.php" style="display:inline;"
                  onsubmit="return confirm('정말 삭제하시겠습니까?')">
              <?= csrfInput() ?>
              <input type="hidden" name="book_id" value="<?= $row['book_id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">삭제</button>
            </form>
          </td>
          <?php endif; ?>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include './footer.php'; $conn->close(); ?>
