<?php
require_once './config.php';
requireLogin();

// 반납 처리는 HTML 출력 전에 먼저 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_rental_id'])) {
    verifyCsrfToken(); // [보안 2] CSRF 검증
    $conn = getDB();
    $rental_id = (int)$_POST['return_rental_id'];
    $user_id   = $_SESSION['user_id'];

    $chk = $conn->prepare("SELECT book_id FROM rentals WHERE rental_id=? AND user_id=? AND status='active'");
    $chk->bind_param('ii', $rental_id, $user_id);
    $chk->execute();
    $chk->bind_result($book_id);
    if ($chk->fetch()) {
        $chk->close();
        $conn->begin_transaction();
        try {
            $today = date('Y-m-d');
            $u1 = $conn->prepare("UPDATE rentals SET status='returned', return_date=? WHERE rental_id=?");
            $u1->bind_param('si', $today, $rental_id);
            $u1->execute(); $u1->close();

            $u2 = $conn->prepare("UPDATE books SET status='available' WHERE book_id=?");
            $u2->bind_param('i', $book_id);
            $u2->execute(); $u2->close();

            $conn->commit();
            $_SESSION['msg'] = '반납이 완료되었습니다.';
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['msg_error'] = '반납 처리 중 오류가 발생했습니다.';
        }
    } else {
        $chk->close();
        $_SESSION['msg_error'] = '잘못된 요청입니다.';
    }
    $conn->close();
    header('Location: ./my_rentals.php');
    exit;
}

require_once './header.php';
$conn = getDB();

$user_id = $_SESSION['user_id'];

// 내 대여 목록 (JOIN: rentals + books + categories) ← 추가 JOIN
$sql = "SELECT r.rental_id, b.title, b.author, c.category_name,
               r.rent_date, r.due_date, r.return_date, r.period_days, r.status,
               DATEDIFF(r.due_date, CURDATE()) AS days_left
        FROM rentals r
        JOIN books b ON r.book_id = b.book_id
        JOIN categories c ON b.category_id = c.category_id
        WHERE r.user_id = ?
        ORDER BY r.rent_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$rentals = $stmt->get_result();
$stmt->close();

// 현재 대여 중인 권수 (Subquery)
$sub = $conn->prepare(
    "SELECT COUNT(*) FROM rentals WHERE user_id=? AND status='active'"
);
$sub->bind_param('i', $user_id);
$sub->execute();
$sub->bind_result($active_count);
$sub->fetch();
$sub->close();
?>

<div class="container">
  <div class="page-title">📋 내 대여 현황</div>

  <?php if (isset($_SESSION['msg'])): ?>
    <div class="alert alert-success"><?= h($_SESSION['msg']) ?></div>
    <?php unset($_SESSION['msg']); ?>
  <?php endif; ?>
  <?php if (isset($_SESSION['msg_error'])): ?>
    <div class="alert alert-error"><?= h($_SESSION['msg_error']) ?></div>
    <?php unset($_SESSION['msg_error']); ?>
  <?php endif; ?>

  <div class="card" style="padding:1rem 1.5rem;margin-bottom:1rem;background:#e8eaf6;">
    <span style="font-size:.95rem;">
      현재 대여 중인 도서:
      <strong style="color:#1a237e;font-size:1.2rem;"><?= (int)$active_count ?></strong>권
    </span>
  </div>

  <div class="card">
    <?php if ($rentals->num_rows === 0): ?>
      <p style="text-align:center;color:#888;padding:2rem 0;">대여 기록이 없습니다.</p>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>#</th><th>도서명</th><th>카테고리</th><th>대여일</th>
          <th>반납기한</th><th>대여기간</th><th>상태</th><th>반납</th>
        </tr>
      </thead>
      <tbody>
        <?php $i=1; while($row = $rentals->fetch_assoc()): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= h($row['title']) ?></td>
          <td><?= h($row['category_name']) ?></td>
          <td><?= h($row['rent_date']) ?></td>
          <td>
            <?= h($row['due_date']) ?>
            <?php if ($row['status']==='active'): ?>
              <?php if ($row['days_left'] < 0): ?>
                <span style="color:red;font-size:.78rem;font-weight:700;"> (<?= abs((int)$row['days_left']) ?>일 연체)</span>
              <?php elseif ($row['days_left'] <= 3): ?>
                <span style="color:orange;font-size:.78rem;"> (D-<?= (int)$row['days_left'] ?>)</span>
              <?php endif; ?>
            <?php endif; ?>
          </td>
          <td><?= h($row['period_days']) ?>일</td>
          <td>
            <span class="badge <?= $row['status']==='active' ? 'badge-active' : 'badge-returned' ?>">
              <?= $row['status']==='active' ? '대여중' : '반납완료' ?>
            </span>
          </td>
          <td>
            <?php if ($row['status']==='active'): ?>
            <form method="POST" action="./my_rentals.php" style="display:inline;">
              <?= csrfInput() ?>
              <input type="hidden" name="return_rental_id" value="<?= $row['rental_id'] ?>">
              <button type="submit" class="btn btn-secondary btn-sm"
                      onclick="return confirm('반납하시겠습니까?')">반납</button>
            </form>
            <?php else: ?>
              <span style="color:#aaa;font-size:.82rem;"><?= h($row['return_date'] ?? '-') ?></span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php include './footer.php'; $conn->close(); ?>
