<?php
require_once './config.php';
requireAdmin();

// [버그수정] GET 방식 삭제는 CSRF 취약 → POST + CSRF 토큰 방식으로 변경
// 기존: <a href="book_delete.php?id=N"> 클릭만으로 삭제 가능 (CSRF 가능)
// 수정: POST 요청 + CSRF 토큰 검증 후 삭제 처리
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // GET 직접 접근 차단
    header('Location: ./book_list.php');
    exit;
}

verifyCsrfToken(); // [보안 2] CSRF 검증

$conn = getDB();
$book_id = (int)($_POST['book_id'] ?? 0);

if ($book_id) {
    // 대여 중인 도서는 삭제 불가
    $chk = $conn->prepare("SELECT status FROM books WHERE book_id=?");
    $chk->bind_param('i', $book_id);
    $chk->execute();
    $chk->bind_result($status);
    $chk->fetch();
    $chk->close();

    if ($status === 'rented') {
        $_SESSION['msg_error'] = '대여 중인 도서는 삭제할 수 없습니다.';
    } else {
        // 관련 대여 기록도 삭제 후 도서 삭제 (DELETE 사용)
        $d1 = $conn->prepare("DELETE FROM rentals WHERE book_id=?");
        $d1->bind_param('i', $book_id);
        $d1->execute();
        $d1->close();

        $d2 = $conn->prepare("DELETE FROM books WHERE book_id=?");
        $d2->bind_param('i', $book_id);
        $d2->execute();
        $d2->close();

        $_SESSION['msg'] = '도서가 삭제되었습니다.';
    }
}
$conn->close();
header('Location: ./book_list.php');
exit;
?>
