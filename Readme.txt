==================================================
  도서 대여 사이트 - Readme.txt
==================================================

[개발 환경]
- Apache 2.4
- PHP 8.5.5
- MySQL 9.6.0
- 접속 주소: http://localhost/library/index.php

[파일 구조]
library/
 ├── config.php        DB 연결 설정 및 공통 보안 함수
 ├── header.php        공통 헤더 (네비게이션, CSS)
 ├── footer.php        공통 푸터
 ├── index.php         메인 페이지
 ├── register.php      회원가입
 ├── login.php         로그인
 ├── logout.php        로그아웃
 ├── book_list.php     도서 목록 (비회원 열람 / 회원 대여)
 ├── book_add.php      도서 등록 (관리자 전용)
 ├── book_edit.php     도서 수정 (관리자 전용)
 ├── book_delete.php   도서 삭제 (관리자 전용)
 ├── my_rentals.php    내 대여 현황 / 반납 (회원 전용)
 └── 학번.sql          DB 백업 파일

[DB 설정 방법]
1. MySQL 접속 후:
   CREATE DATABASE `library` DEFAULT CHARACTER SET utf8mb4;
   USE library;
   source C:/Apache24/htdocs/library/학번.sql

2. config.php 에서 DB_PASS 를 본인 MySQL 비밀번호로 변경

[테스트 계정]
※ 보안상 로그인 화면에는 계정 정보를 표시하지 않습니다.
   아래 계정은 개발·테스트 목적으로만 사용하며,
   실제 배포 시 반드시 비밀번호를 변경하세요.
- 관리자: admin / admin1234
- 일반회원: kim01 / password123

==================================================
  JOIN 사용 위치
==================================================
1. index.php
   SELECT b.book_id, b.title, b.author, b.status, c.category_name
   FROM books b JOIN categories c ON b.category_id = c.category_id
   → 최신 도서 목록 조회 시 카테고리명을 함께 출력

2. book_list.php
   SELECT b.book_id, b.title, ..., c.category_name
   FROM books b JOIN categories c ON b.category_id = c.category_id
   → 도서 목록 전체 조회 시 카테고리명 포함

3. my_rentals.php
   SELECT r.*, b.title, b.author, c.category_name
   FROM rentals r
   JOIN books b ON r.book_id = b.book_id
   JOIN categories c ON b.category_id = c.category_id
   → 대여 기록에 도서명, 카테고리명 함께 출력

==================================================
  Subquery 사용 위치
==================================================
1. index.php
   SELECT COUNT(*) AS cnt FROM books
   WHERE book_id IN (SELECT book_id FROM rentals WHERE status='active')
   → 현재 대여 중인 도서 수를 서브쿼리로 집계

2. my_rentals.php
   SELECT COUNT(*) FROM rentals WHERE user_id=? AND status='active'
   → 로그인 사용자의 활성 대여 수 집계

==================================================
  HTML 입력 폼 종류 (4종 이상)
==================================================
1. text / password   - 로그인, 회원가입 (register.php, login.php)
2. textarea          - 도서 소개 입력 (book_add.php, book_edit.php)
3. checkbox          - 관심 카테고리 선택 (register.php)
4. select/option     - 카테고리 드롭다운 (book_list.php, book_add.php, book_edit.php)
5. radio button      - 대여 상태 선택 (book_edit.php)
6. email / tel       - HTML5 입력 폼 (register.php)

==================================================
  보안 취약점 대처 방안
  (주요정보통신기반시설 기술적 취약점 분석·평가 방법 상세가이드 기준 / 2021년 버전)
==================================================

1. SQL 인젝션 방지 [가이드 항목: SI(상)]
   - 공격: 입력값에 SQL 구문('OR'1'='1)을 삽입하여 DB 무단 조작·열람
   - 방어: 모든 DB 쿼리에 Prepared Statement + bind_param() 사용
           Dynamic SQL 구문 사용 금지, 파라미터에 문자열 검사 필수 적용
   - 적용 위치: config.php, login.php, register.php, book_list.php, book_add.php,
               book_edit.php, book_delete.php, my_rentals.php 전체 파일

2. 크로스사이트 스크립팅(XSS) 방지 [가이드 항목: XS(상)]
   - 공격: 입력값에 <script> 삽입 후 다른 사용자 브라우저에서 스크립트 실행,
           세션 쿠키 탈취 및 악성코드 유포 가능
   - 방어: 모든 출력에 htmlspecialchars()를 래핑한 h() 함수 사용 (ENT_QUOTES 옵션)
           session.cookie_httponly=1 설정으로 JS에서 세션 쿠키 접근 차단
           X-XSS-Protection 헤더 설정으로 브라우저 XSS 필터 강제 활성화
           Content-Security-Policy 헤더로 허용된 출처의 리소스만 로드
   - 적용 위치: config.php의 h() 함수 및 setSecurityHeaders() 함수, 모든 PHP 출력부

3. 크로스사이트 리퀘스트 변조(CSRF) 방지 [가이드 항목: CF(상)]
   - 공격: 악성 사이트에서 로그인된 사용자의 권한으로 몰래 데이터 수정·등록·삭제 요청
   - 방어: 서버에서 난수 토큰(bin2hex(random_bytes(32))) 발급 후 폼에 hidden input으로
           포함, 제출 시 hash_equals()로 검증 (가이드: Hidden Form에 암호화된 토큰 추가)
           session.cookie_samesite=Strict 설정으로 크로스사이트 쿠키 전송 차단
   - 적용 위치: config.php (generateCsrfToken, verifyCsrfToken, csrfInput 함수)
               login.php, register.php, book_add.php, book_edit.php,
               book_list.php, book_delete.php, my_rentals.php

4. 무차별 대입 공격(Brute Force) 방지 [가이드 항목: BF(상)]
   - 공격: 비밀번호를 자동화 도구로 무한 반복 시도하여 계정 탈취
   - 방어: 로그인 5회 실패 시 30분 잠금 처리 (가이드 권고: 3~5회 초과 시 잠금)
           서버 측 Server Side Script로 인증 실패 횟수 관리 (Client Side 우회 방지)
           실패 시 남은 시도 횟수를 사용자에게 표시
   - 적용 위치: config.php (checkLoginAttempts, recordLoginFailure, resetLoginAttempts)
               login.php (로그인 처리 시 호출)

5. 약한 비밀번호 강도 방지 [가이드 항목: BF(상)]
   - 공격: 단순 비밀번호로 인한 사전 공격(Dictionary Attack) 및 추측 공격 취약
   - 방어: 가이드 권고 기준 - 2종류 이상 조합 10자 이상, 또는 3종류 이상 조합 8자 이상
           본 시스템: 8자 이상 + 영문 + 숫자 + 특수문자 조합 필수
           bcrypt (password_hash) 해시로 저장 (평문·SHA-256 저장 금지)
           기존 SHA-256 저장 계정은 로그인 시 bcrypt로 자동 업그레이드
   - 적용 위치: config.php (validatePasswordStrength 함수), register.php, login.php

6. 세션 하이재킹·세션 고정·세션 예측 방지 [가이드 항목: SE(상), SF(상), SC(상)]
   - 공격: 탈취한 세션 쿠키로 다른 기기에서 접근 (하이재킹)
           로그인 전 발급된 세션 ID를 로그인 후에도 재사용 (세션 고정)
           단순 패턴의 세션 ID를 추측하여 불법 접근 (세션 예측)
           세션 만료 미설정으로 만료되지 않은 세션 재활용 (불충분한 세션 만료)
   - 방어: 로그인 시 IP 주소·User-Agent를 세션에 저장, 매 요청마다 비교하여
           다를 경우 강제 로그아웃 (세션 하이재킹 방지)
           로그인 성공 시 session_regenerate_id(true)로 세션 ID 재생성 (세션 고정 방지)
           PHP 기본 세션 관리(추측 불가능한 난수 세션 ID) 사용 (세션 예측 방지)
           비활성 30분 후 세션 자동 만료, 타임아웃 시 자동 로그아웃 처리 (세션 만료)
           session.cookie_httponly=1, session.use_strict_mode=1 설정 적용
   - 적용 위치: config.php (checkSessionSecurity, setSessionSecurity 함수)

7. 정보 누출 방지 [가이드 항목: IL(상)]
   - 공격: 에러 메시지에서 DB 구조·버전·파일 경로 등 민감 정보 수집,
           로그인 실패 시 특정 ID 존재 여부 식별
   - 방어: DB 연결 오류 등 내부 상세 정보는 error_log()로 서버 로그에만 기록
           사용자에게는 일반적인 오류 메시지만 표시
           로그인 실패 시 "아이디 또는 비밀번호가 올바르지 않습니다" 메시지로 통일
           (계정 존재 여부를 구분할 수 없도록 동일 메시지 사용)
           로그인 화면에서 테스트 계정 정보 표시 삭제
   - 적용 위치: config.php (getDB 함수), login.php

8. 디렉터리 인덱싱 방지 [가이드 항목: DI(상)]
   - 공격: 디렉터리 URL 접근 시 파일 목록이 노출되어 서버 구조·소스파일 노출
   - 방어: Apache httpd.conf에서 Options -Indexes 설정으로 디렉터리 리스팅 비활성화
           .htaccess 파일에 Options -Indexes 추가 적용
   - 적용 위치: C:\Apache24\conf\httpd.conf (Options -Indexes FollowSymLinks)
               C:\Apache24\htdocs\library\.htaccess

9. 불충분한 인가 방지 [가이드 항목: IN(상)]
   - 공격: 비인가자가 URL 파라미터 변경 등으로 관리자 페이지에 직접 접근
   - 방어: requireLogin() - 비로그인 접근 시 login.php 리다이렉트
           requireAdmin() - 일반 회원의 관리자 페이지 접근 차단 및 로그 기록
           대여·반납 시 서버에서 본인 기록인지 재확인 (클라이언트 신뢰 금지)
           config.php, header.php 직접 URL 접근 시 403 응답
   - 적용 위치: config.php (requireLogin, requireAdmin 함수)
               book_add.php, book_edit.php, book_delete.php (관리자 전용)
               my_rentals.php (로그인 필수)

10. 버퍼 오버플로우 방지 [가이드 항목: BO(상)]
    - 공격: 비정상적으로 긴 입력값으로 오류 유발, 의도치 않은 정보 노출 및 비인가 접근
    - 방어: limitStr() 함수로 입력값 최대 길이 제한 (서버 측 검증)
            HTML maxlength 속성으로 클라이언트 측 1차 제한
            정수값은 (int) 캐스팅으로 형변환, 허용값 범위 체크 (대여 기간 7/14/21일)
            이메일은 filter_var(FILTER_VALIDATE_EMAIL) 검증
            아이디는 preg_match로 영문·숫자·밑줄만 허용 (화이트리스트 방식)
    - 적용 위치: config.php (limitStr 함수), register.php, login.php,
                book_add.php, book_edit.php, book_list.php

11. HTTP 보안 헤더 설정 [가이드 항목: DI(상), XS(상), IL(상) 연계]
    - 공격: Clickjacking (iframe 삽입), MIME 스니핑, 외부 리소스를 통한 스크립트 실행
    - 방어: X-Frame-Options: DENY - Clickjacking 방지 (iframe 삽입 불가)
            X-Content-Type-Options: nosniff - MIME 타입 스니핑 공격 방지
            X-XSS-Protection: 1; mode=block - 구형 브라우저 XSS 필터 강제 활성화
            Referrer-Policy - 외부 사이트로 Referrer 정보 최소화
            Content-Security-Policy - 허용된 출처의 리소스만 로드
    - 적용 위치: config.php (setSecurityHeaders 함수, 모든 페이지 로드 시 자동 실행)

12. 보안 이벤트 로그 기록
    - 목적: 침해 사고 발생 시 추적 및 원인 분석을 위한 로그 보존
    - 기록 항목: 로그인 성공·실패 (계정명, IP, 시각)
                 Brute Force 잠금 발생 이벤트
                 CSRF 토큰 불일치 시도
                 세션 하이재킹 의심 접근
                 세션 타임아웃 만료
                 비인가 관리자 페이지 접근 시도
                 회원가입 이벤트
    - 적용 위치: config.php, login.php, register.php (error_log()로 서버 로그에 기록)

13. GET 방식 삭제 → POST + CSRF 토큰 방식으로 변경
    - 공격: <a href="book_delete.php?id=N"> 링크를 통한 CSRF 공격으로 데이터 삭제
    - 방어: 삭제 요청을 POST 폼 + CSRF 토큰 검증 방식으로 변경
            GET 직접 접근 시 book_list.php로 리다이렉트
    - 적용 위치: book_delete.php (POST 방식으로 변경), book_list.php (삭제 버튼 폼 변경)

14. 상대경로 사용
    - 모든 파일 include/require는 ./파일명.php 형태 사용 (절대경로 사용 없음)

15. 트랜잭션 처리
    - 대여·반납 시 books 테이블과 rentals 테이블을
      begin_transaction / commit / rollback으로 원자적 처리
    - 중간 오류 시 데이터 불일치 방지
