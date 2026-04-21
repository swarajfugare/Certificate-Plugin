# 🚀 SmartCertify – WordPress Certificate Plugin

> 🎓 Generate, manage & verify certificates with ease — all from one powerful WordPress plugin.

---

## 👨‍💻 Developed By

**Swaraj Fugare**
🌐 https://portfolio.matoshreecollection.in
🏢 https://matoshreecollection.in

---

## 🌟 About SmartCertify

**SmartCertify** is a modern WordPress plugin designed to handle **complete certificate lifecycle management** — from creation to delivery, verification, and analytics.

✔ No external dependency
✔ Fully secure system
✔ QR-based verification
✔ Batch + class management
✔ Built for performance & scalability

---

## ✨ Key Highlights

### 🎯 Certificate System

* Single master template for all certificates
* Dynamic class & batch-based generation
* QR code verification (local & secure)

---

### ⚡ Automation & Workflow

* Bulk certificate generation
* Batch-based issuing system
* Auto email + WhatsApp delivery links

---

### 🔐 Security Features

* Token-based secure downloads
* Login-protected certificate access
* Download limits & tracking
* Full audit logs

---

### 📊 Admin Dashboard

* Analytics & usage tracking
* Certificate lifecycle (revoke, reissue, renew)
* Student history management

---

## 📸 Preview (Add Your Screenshots Here)

```
/assets/screenshot1.png
/assets/screenshot2.png
/assets/dashboard.png
```

---

## 🛠️ Installation Guide

### Step 1: Upload Plugin

Upload plugin folder to:

```
/wp-content/plugins/smartcertify/
```

---

### Step 2: Activate Plugin

* Go to WordPress Admin → Plugins
* Activate **SmartCertify**

---

### Step 3: Open Dashboard

Go to:

```
SmartCertify → Dashboard
```

---

## 🚀 Quick Setup

### 1️⃣ Setup Certificate Template

* Upload template (PNG / JPG / PDF)
* Position:

  * Name
  * QR Code
  * Signature

---

### 2️⃣ Create Batches

* Add class
* Add batch
* Assign teachers

---

### 3️⃣ Add Codes

* Manual entry OR CSV import
* Link codes with students

---

### 4️⃣ Display Form

Use shortcode:

```
[smartcertify_form]
```

---

## 🎯 How It Works (User Side)

1. Select class
2. Login (if required)
3. Enter name + code
4. Generate certificate
5. Download / View

---

## 🔗 Shortcode

```
[smartcertify_form]
```

Optional:

```
[smartcertify_form class="Course101"]
```

---

## ⚙️ Advanced Features

* REST API support
* Webhooks integration
* Custom hooks & filters
* Backup & restore system
* Health check tools

---

## 📂 Project Structure

```
Certificate-Plugin/
│
├── assets/            # Images & UI
├── includes/          # Core logic
├── smartcertify.php   # Main plugin file
├── README.md
├── CHANGELOG.md
```

---

## ⚠️ Requirements

* WordPress 5.0+
* PHP 7.2+
* MySQL 5.6+

---

## 🔐 Security

✔ CSRF Protection
✔ SQL Injection Safe
✔ Input Sanitization
✔ Secure File Handling

---

## 📈 Performance

* Lightweight (~50KB)
* Optimized queries
* Cache-friendly
* Mobile responsive

---

## 🧩 Customization

### Custom CSS

```css
.smartcertify-container {
  /* Your styles */
}
```

---

### Change Button Text

```php
add_filter('smartcertify_button_text', function() {
  return 'Download My Certificate';
});
```

---

## 📊 Version

**Current Version:** 1.7.3

---

## 📜 License

MIT License

---

## ❤️ Support This Project

If you like this project:

⭐ Star this repository
🔗 Share with others
🌐 Visit: https://matoshreecollection.in

---

## 🚀 Future Plans

* AI certificate auto-fill
* Advanced analytics dashboard
* Multi-language support
* SaaS version

---

> ⚡ Built with passion by Swaraj Fugare
