<?php
// [보안] 직접 URL 접근 차단 (http://localhost/library/header.php 직접 접근 금지)
if (basename($_SERVER['PHP_SELF']) === 'header.php') {
    http_response_code(403);
    exit('접근이 거부되었습니다.');
}
// header.php - 모든 페이지 상단에 include
require_once './config.php';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>📚 도서 대여 사이트</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Noto Sans KR', sans-serif;
      background: #f4f6fb;
      color: #222;
      min-height: 100vh;
    }
    a { text-decoration: none; color: inherit; }

    /* ── NAV ── */
    nav {
      background: #1a237e;
      color: #fff;
      padding: 0 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 60px;
      box-shadow: 0 2px 8px rgba(0,0,0,.3);
    }
    .nav-logo { font-size: 1.3rem; font-weight: 700; letter-spacing: -0.5px; }
    .nav-logo span { color: #ffd54f; }
    .nav-links { display: flex; gap: 1.2rem; align-items: center; font-size: .9rem; }
    .nav-links a { color: #cfd8dc; transition: color .2s; }
    .nav-links a:hover { color: #fff; }
    .nav-links .btn-nav {
      background: #ffd54f; color: #1a237e;
      padding: .35rem .9rem; border-radius: 20px;
      font-weight: 700; font-size: .85rem;
      transition: background .2s;
    }
    .nav-links .btn-nav:hover { background: #ffe082; }

    /* ── CONTAINER ── */
    .container {
      max-width: 1100px;
      margin: 2rem auto;
      padding: 0 1rem;
    }

    /* ── CARD ── */
    .card {
      background: #fff;
      border-radius: 12px;
      padding: 2rem;
      box-shadow: 0 2px 12px rgba(0,0,0,.08);
      margin-bottom: 1.5rem;
    }
    .card h2 { font-size: 1.3rem; margin-bottom: 1.2rem; color: #1a237e; }

    /* ── FORM ── */
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-size: .85rem; font-weight: 500; margin-bottom: .35rem; color: #555; }
    .form-group input[type=text],
    .form-group input[type=password],
    .form-group input[type=email],
    .form-group input[type=tel],
    .form-group select,
    .form-group textarea {
      width: 100%; padding: .6rem .9rem;
      border: 1.5px solid #ddd; border-radius: 8px;
      font-family: inherit; font-size: .95rem;
      transition: border-color .2s;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none; border-color: #1a237e;
    }
    .form-group textarea { resize: vertical; min-height: 90px; }

    /* ── BUTTONS ── */
    .btn {
      display: inline-block; padding: .55rem 1.3rem;
      border: none; border-radius: 8px;
      font-family: inherit; font-size: .9rem; font-weight: 500;
      cursor: pointer; transition: opacity .2s, transform .1s;
    }
    .btn:hover { opacity: .88; transform: translateY(-1px); }
    .btn-primary   { background: #1a237e; color: #fff; }
    .btn-success   { background: #2e7d32; color: #fff; }
    .btn-danger    { background: #c62828; color: #fff; }
    .btn-secondary { background: #607d8b; color: #fff; }
    .btn-sm { padding: .35rem .8rem; font-size: .82rem; }

    /* ── TABLE ── */
    table { width: 100%; border-collapse: collapse; font-size: .9rem; }
    th { background: #1a237e; color: #fff; padding: .7rem 1rem; text-align: left; }
    td { padding: .65rem 1rem; border-bottom: 1px solid #eee; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #f0f4ff; }

    /* ── BADGE ── */
    .badge {
      display: inline-block; padding: .2rem .65rem;
      border-radius: 20px; font-size: .78rem; font-weight: 700;
    }
    .badge-ok      { background: #e8f5e9; color: #2e7d32; }
    .badge-rented  { background: #fce4ec; color: #c62828; }
    .badge-active  { background: #fff3e0; color: #e65100; }
    .badge-returned{ background: #e8f5e9; color: #2e7d32; }

    /* ── ALERT ── */
    .alert { padding: .75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: .9rem; }
    .alert-error   { background: #fce4ec; color: #c62828; border: 1px solid #f48fb1; }
    .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }

    /* ── PAGE TITLE ── */
    .page-title { font-size: 1.6rem; font-weight: 700; margin-bottom: 1.5rem; color: #1a237e; }
  </style>
</head>
<body>
<nav>
  <div class="nav-logo">📚 <span>BookRent</span></div>
  <div class="nav-links">
    <a href="./index.php">홈</a>
    <a href="./book_list.php">도서 목록</a>
    <?php if (isLoggedIn()): ?>
      <a href="./my_rentals.php">내 대여</a>
      <?php if (isAdmin()): ?>
        <a href="./book_add.php">도서 등록</a>
      <?php endif; ?>
      <span style="color:#cfd8dc;font-size:.85rem">
        <?= h($_SESSION['name']) ?>님
      </span>
      <a href="./logout.php" class="btn-nav">로그아웃</a>
    <?php else: ?>
      <a href="./login.php">로그인</a>
      <a href="./register.php" class="btn-nav">회원가입</a>
    <?php endif; ?>
  </div>
</nav>
