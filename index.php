<?php
require_once './header.php';
$conn = getDB();

// 최신 도서 6권 (JOIN 사용 - categories 테이블과 조인) ← JOIN 사용 위치
// Readme.txt 명시: index.php 8번째 줄 (실제 줄 수는 파일 기준)
$sql = "SELECT b.book_id, b.title, b.author, b.status, c.category_name
        FROM books b
        JOIN categories c ON b.category_id = c.category_id
        ORDER BY b.created_at DESC
        LIMIT 6";
$result = $conn->query($sql);

// 대여 중인 책 수 (Subquery 사용) ← Subquery 사용 위치
// Readme.txt 명시: index.php 16번째 줄
$sub = $conn->query(
    "SELECT COUNT(*) AS cnt FROM books
     WHERE book_id IN (SELECT book_id FROM rentals WHERE status='active')"
);
$rented_count = $sub->fetch_assoc()['cnt'];

$total = $conn->query("SELECT COUNT(*) AS cnt FROM books")->fetch_assoc()['cnt'];
$member_count = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE role='member'")->fetch_assoc()['cnt'];
?>

<div class="container">
  <!-- 히어로 배너 -->
  <div style="background:linear-gradient(135deg,#1a237e,#283593);color:#fff;border-radius:16px;padding:3rem 2.5rem;margin-bottom:2rem;position:relative;overflow:hidden;">
    <div style="font-size:3rem;margin-bottom:.5rem;">📚</div>
    <h1 style="font-size:2rem;font-weight:700;margin-bottom:.5rem;">BookRent 도서 대여 사이트</h1>
    <p style="color:#c5cae9;font-size:1rem;margin-bottom:1.5rem;">원하는 책을 쉽고 빠르게 대여하세요.</p>
    <?php if (!isLoggedIn()): ?>
      <a href="./register.php" class="btn btn-primary" style="background:#ffd54f;color:#1a237e;margin-right:.5rem;">회원가입</a>
      <a href="./login.php" class="btn" style="background:rgba(255,255,255,.15);color:#fff;">로그인</a>
    <?php else: ?>
      <a href="./book_list.php" class="btn" style="background:#ffd54f;color:#1a237e;">도서 목록 보기</a>
    <?php endif; ?>
  </div>

  <!-- 통계 카드 -->
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:2rem;">
    <?php foreach([
      ['📖','전체 도서',$total.'권'],
      ['🔖','대여 중',$rented_count.'권'],
      ['👥','회원 수',$member_count.'명'],
    ] as [$icon,$label,$val]): ?>
    <div class="card" style="text-align:center;padding:1.5rem;">
      <div style="font-size:2rem;"><?= $icon ?></div>
      <div style="font-size:.85rem;color:#777;margin:.3rem 0;"><?= $label ?></div>
      <div style="font-size:1.6rem;font-weight:700;color:#1a237e;"><?= $val ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- 최신 도서 -->
  <div class="card">
    <h2>🆕 최신 등록 도서</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem;">
      <?php while($row = $result->fetch_assoc()): ?>
      <div style="border:1px solid #eee;border-radius:10px;padding:1rem;text-align:center;background:#fafafa;">
        <div style="font-size:2.5rem;margin-bottom:.5rem;">📗</div>
        <div style="font-weight:700;font-size:.9rem;margin-bottom:.3rem;line-height:1.3;"><?= h($row['title']) ?></div>
        <div style="font-size:.8rem;color:#777;margin-bottom:.5rem;"><?= h($row['author']) ?></div>
        <span class="badge <?= $row['status']==='available' ? 'badge-ok' : 'badge-rented' ?>">
          <?= $row['status']==='available' ? '대여가능' : '대여중' ?>
        </span>
      </div>
      <?php endwhile; ?>
    </div>
    <div style="text-align:center;margin-top:1.2rem;">
      <a href="./book_list.php" class="btn btn-primary">전체 도서 보기</a>
    </div>
  </div>
</div>

<?php include './footer.php'; $conn->close(); ?>
