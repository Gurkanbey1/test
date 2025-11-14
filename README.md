# WindTask – Wind Medya İş Takip & Ekip Yönetim Sistemi

WindTask; PHP 8 + MySQL 8 üzerinde geliştirilmiş, Bootstrap 5, jQuery, SweetAlert2, FontAwesome ve AJAX destekli modern bir görev ve ekip yönetim panelidir. Bu repo içerisinde çalışma ortamınız için tam dosya yapısı, API uçları, güvenli dosya yüklemeleri, karanlık mod, bildirimler, @mention sistemi, görev içi sohbet, timeline, checklist ve kanban board yer almaktadır.

## Kurulum

1. **Depoyu kopyala**
   ```bash
   git clone <repo-url>
   cd windtask
   ```

2. **Ortam değişkenleri (opsiyonel)**  
   `config.php` varsayılan olarak `DB_HOST=127.0.0.1`, `DB_USER=root`, `DB_PASS=''`, `DB_NAME=windtask` değerlerini kullanır. Farklı değerler için ortam değişkenleri tanımlayabilirsiniz.

3. **Veritabanı**
   ```bash
   mysql -u root -p < database.sql
   ```

4. **Uploads klasörü**
   ```
   chmod -R 775 uploads
   ```

5. **Sunucu**
   ```bash
   php -S 0.0.0.0:8080 -t windtask
   ```
   Ardından tarayıcıdan `http://localhost:8080/login.php` adresine gidin.

## Varsayılan Giriş

- E-posta: `admin@windtask.local`
- Şifre: `Admin123!`  
  (Şifre `database.sql` içinde bcrypt ile hashlenmiştir.)

## Özellikler

- Kullanıcı yönetimi (oluşturma, askıya alma, silme)
- Görev oluşturma, etiketleme, çoklu dosya yükleme, görev sohbet odası
- @mention destekli bildirim sistemi
- Timeline, checklist, kanban board (drag & drop)
- AJAX filtreleme, canlı sohbet (3 sn polling), dosya progress bar
- REST API uçları (`/api/tasks.php`, `/api/create.php`, `/api/comment.php`)
- Karanlık mod (cookie ile hatırlama)
- MIME kontrollü güvenli dosya yükleme + uploads/.htaccess
- Mobil uygulama entegrasyonuna hazır JSON cevaplar

## API Örnekleri

| Method | Endpoint            | Açıklama                  |
|--------|---------------------|---------------------------|
| GET    | `/api/tasks.php`    | Filtreli görev listesi    |
| POST   | `/api/tasks.php`    | `action=update_status` vb |
| POST   | `/api/create.php`   | Görev oluştur             |
| GET    | `/api/comment.php`  | Görev yorumları           |
| POST   | `/api/comment.php`  | Yeni yorum + dosya        |

İsteklerde `csrf_token` gönderilmesi gerekir (sayfanın `<meta name="csrf-token">` alanından okunabilir).

## Paket Alma

Proje tamamlandığında aşağıdaki komutla zip oluşturabilirsiniz:
```bash
(cd /workspace && zip -r windtask.zip windtask)
```
Zip dosyası istemcide paylaşım için hazır olacaktır.
