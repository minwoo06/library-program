# Library — 도서 대여 사이트

PHP·MySQL로 구현한 도서 대여 웹 프로젝트입니다. 회원가입과 로그인, 도서 검색, 관리자 도서 관리, 대여·반납 및 연체 표시를 제공합니다.

## 실행 환경

- Apache 2.4
- PHP 8.5 및 mysqli 확장
- MySQL 9.6

## 처음 설치하기

1. 프로젝트를 Apache의 웹 루트 아래 `library` 폴더에 둡니다.
2. `config.example.php`를 `config.php`로 복사합니다. 예제에는 실행에 필요한 공통 함수도 포함되어 있습니다.
3. `config.php`의 `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`을 본인의 환경에 맞게 수정합니다.
4. **새로 설치하는 빈 데이터베이스에서만** 아래 명령을 실행합니다. 기존 사이트의 DB를 다시 초기화할 필요는 없습니다.

   ```sql
   CREATE DATABASE library DEFAULT CHARACTER SET utf8mb4;
   USE library;
   SOURCE C:/Apache24/htdocs/library/database/schema.sql;
   ```

5. `http://localhost/library/index.php`에 접속하여 회원가입합니다. 기본 관리자나 공용 비밀번호는 제공하지 않습니다.
6. 관리자가 필요하면 DB 관리자가 다음 SQL의 아이디를 실제 가입한 본인 아이디로 바꾸어 실행합니다.

   ```sql
   UPDATE users SET role = 'admin' WHERE username = '본인이_가입한_아이디';
   ```

스키마에는 테이블 구조와 기본 카테고리만 포함됩니다. 도서와 사용자 데이터는 사이트에서 직접 등록합니다.

## 공개 파일과 로컬 파일

| 파일 | 용도 | Git에 포함 |
| --- | --- | --- |
| `config.example.php` | 비밀번호 없는 설정 예제 및 공통 함수 | 예 |
| `config.php` | 실제 DB 접속 설정 및 공통 함수 | 아니요 |
| `database/schema.sql` | 사용자 데이터가 없는 설치용 스키마 | 예 |
| 그 외 `*.sql` | 로컬 DB 백업 | 아니요 |
| `.env`, 로그, 압축 백업 | 로컬 설정 및 작업 파일 | 아니요 |

`.gitignore`는 파일명 맨 앞의 점까지 포함해야 합니다. 이미 커밋한 파일은 제외 규칙만 추가해도 사라지지 않습니다. GitHub 웹 업로드를 사용할 때도 실제 설정과 백업 파일을 직접 선택하지 마세요.

공통 보안 함수를 변경할 때는 로컬 `config.php`와 공개 `config.example.php`를 함께 반영하되, 예제의 `DB_PASS`는 항상 비워 두세요.

## 적용한 주요 보안 처리

- 사용자 입력을 처리하는 SQL에 Prepared Statement 사용
- HTML 출력 이스케이프와 CSRF 토큰 검증
- 신규 비밀번호 해시 저장과 관리자 권한 확인
- 세션 ID 재생성, 세션 만료 처리
- 대여·반납 시 트랜잭션 사용

기존 과제의 구현 설명은 `Readme.txt`에 있습니다.
