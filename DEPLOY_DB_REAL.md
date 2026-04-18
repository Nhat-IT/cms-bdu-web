# Chuyen Tu DB Ao Sang DB That (Production)

## 1. Cap nhat bien moi truong

Sao chep `.env.example` thanh `.env` va sua lai:

```env
DB_HOST=<ip-hoac-domain-mysql>
DB_PORT=3306
DB_USER=<db_user_that>
DB_PASSWORD=<mat_khau_manh>
DB_NAME=cms_bdu
DB_CONNECTION_LIMIT=1
DB_TIMEZONE=Z
DB_SSL=false
DB_CONNECT_TIMEOUT=10000

PORT=3000
NODE_ENV=production
SESSION_SECRET=<chuoi-bi-mat-dai>
DB_STARTUP_STRICT=false
```

Neu dung MySQL cloud bat buoc SSL, dat:

```env
DB_SSL=true
```

## 2. Tao database that

Dang nhap MySQL bang tai khoan quan tri:

```sql
CREATE DATABASE cms_bdu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'app_user'@'%' IDENTIFIED BY 'change_me_now';
GRANT ALL PRIVILEGES ON cms_bdu.* TO 'app_user'@'%';
FLUSH PRIVILEGES;
```

## 3. Import schema

Chay lenh:

```bash
mysql -h <DB_HOST> -P <DB_PORT> -u <DB_USER> -p cms_bdu < db.sql
```

## 4. Khoi dong he thong

```bash
npm start
```

Server se chi startup khi ping DB thanh cong.
Neu DB sai, app se dung ngay de tranh van hanh voi cau hinh loi.

## 5. Luu y bao mat

- Khong dua `.env` len git.
- Doi mat khau DB va `SESSION_SECRET` truoc khi mo production.
- Neu thong tin DB tung bi lo, hay rotate mat khau va tao user moi.
