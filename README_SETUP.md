# 🍊 Orange Project Setup Guide

Hướng dẫn dựng toàn bộ hệ thống Orange bao gồm Backend Laravel, Database MySQL, và Flutter App.

## 📋 Yêu cầu hệ thống

### Prerequisites
- Docker và Docker Compose
- Flutter SDK (cho mobile app)
- Git

### Cài đặt Docker (nếu chưa có)
```bash
# macOS
brew install docker docker-compose

# Ubuntu/Debian
sudo apt-get update
sudo apt-get install docker.io docker-compose
```

### Cài đặt Flutter (nếu chưa có)
```bash
# macOS
brew install flutter

# Ubuntu/Debian
sudo snap install flutter --classic
```

## 🚀 Setup nhanh

### Bước 1: Clone và setup Backend + Database
```bash
# Chạy script setup tự động
chmod +x setup.sh
./setup.sh
```

### Bước 2: Setup Flutter App
```bash
# Chạy script setup Flutter
chmod +x setup_flutter.sh
./setup_flutter.sh
```

## 📝 Setup thủ công

### 1. Backend Laravel + Database

#### Bước 1: Tạo file .env
```bash
# Tạo file .env trong thư mục orange_backend
cp orange_backend/.env.example orange_backend/.env
```

#### Bước 2: Cấu hình database trong .env
```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=orange_db
DB_USERNAME=orange_user
DB_PASSWORD=orange_pass
```

#### Bước 3: Khởi động Docker services
```bash
docker-compose up -d
```

#### Bước 4: Setup Laravel
```bash
# Chạy các lệnh Laravel
docker-compose exec backend php artisan key:generate
docker-compose exec backend php artisan config:clear
docker-compose exec backend php artisan config:cache
docker-compose exec backend php artisan migrate --force
```

### 2. Flutter App

#### Bước 1: Vào thư mục Flutter
```bash
cd Orange
```

#### Bước 2: Cài đặt dependencies
```bash
flutter pub get
```

#### Bước 3: Kiểm tra setup
```bash
flutter doctor
```

#### Bước 4: Chạy app
```bash
# Chạy trên device/emulator
flutter run

# Chạy trên web
flutter run -d chrome

# Build APK
flutter build apk

# Build iOS
flutter build ios
```

## 🌐 Truy cập các services

- **Backend API**: http://localhost:8000
- **Database**: localhost:3306
- **Redis**: localhost:6379

## 📁 Cấu trúc dự án

```
orange/
├── docker-compose.yml          # Docker services
├── setup.sh                    # Script setup backend
├── setup_flutter.sh           # Script setup Flutter
├── database_orange.sql        # Database schema
├── orange_backend/            # Laravel Backend
│   ├── Dockerfile
│   ├── .env
│   └── ...
└── Orange/                    # Flutter App
    ├── pubspec.yaml
    ├── lib/
    └── ...
```

## 🔧 Troubleshooting

### Backend Issues

#### Database connection failed
```bash
# Kiểm tra container status
docker-compose ps

# Restart services
docker-compose restart

# Check logs
docker-compose logs mysql
docker-compose logs backend
```

#### Laravel permissions
```bash
# Set permissions
chmod -R 775 orange_backend/storage
chmod -R 775 orange_backend/bootstrap/cache
```

### Flutter Issues

#### Dependencies not found
```bash
cd Orange
flutter clean
flutter pub get
```

#### Build errors
```bash
flutter doctor -v
flutter analyze
```

## 🛠️ Development Commands

### Backend
```bash
# View logs
docker-compose logs -f backend

# Access container
docker-compose exec backend bash

# Run artisan commands
docker-compose exec backend php artisan migrate
docker-compose exec backend php artisan make:controller TestController
```

### Database
```bash
# Access MySQL
docker-compose exec mysql mysql -u orange_user -p orange_db

# Backup database
docker-compose exec mysql mysqldump -u orange_user -p orange_db > backup.sql
```

### Flutter
```bash
# Hot reload
flutter run --hot

# Build release
flutter build apk --release
flutter build ios --release

# Run tests
flutter test
```

## 📱 API Endpoints

Backend API sẽ có sẵn tại `http://localhost:8000/api/`

Các endpoint chính:
- `POST /api/login` - Đăng nhập
- `POST /api/register` - Đăng ký
- `GET /api/user` - Thông tin user
- `POST /api/logout` - Đăng xuất

## 🔒 Security Notes

- Đổi mật khẩu database trong production
- Cấu hình HTTPS cho production
- Set APP_ENV=production trong .env
- Disable APP_DEBUG trong production

## 📞 Support

Nếu gặp vấn đề, hãy kiểm tra:
1. Docker services đang chạy
2. Ports không bị conflict
3. File permissions đúng
4. Flutter doctor không có lỗi 