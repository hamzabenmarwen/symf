# 🚀 Quick Start Guide - New Features

## What's New (December 3, 2025)

### 1️⃣ **Overdue Book Protection** 
- Users can't borrow if they have overdue books
- System automatically marks books as "overdue" when due date passes
- Status shown in borrowing history (red = overdue)

**Test it:**
1. Borrow a book
2. Wait until due date passes
3. Try to borrow another book → You'll get an error message
4. Return the overdue book
5. Now you can borrow again ✓

---

### 2️⃣ **Email Reminders** 
- Automatic emails 3 days before return date
- Automatic emails when book is overdue
- Requires setup (see below)

**To enable:**
1. Open `.env.local` in project root
2. Add your email service:
```env
# Gmail (easiest for testing)
MAILER_DSN=smtp://your_email%40gmail.com:your_app_password@smtp.gmail.com:587

# Or SendGrid
MAILER_DSN=sendgrid://your_api_key@default

# Or Mailgun, etc.
```

3. Test it:
```bash
cd c:\Users\Win10\Desktop\symfony\Easy\projetEA
php bin/console app:send-notifications
```

4. Set up daily cron (optional):
```
Windows Task Scheduler: Run daily at 9 AM
Command: php C:\path\to\bin\console app:send-notifications
```

---

### 3️⃣ **User Profile Statistics**
- Visit "Mon Compte" to see your stats
- Shows: total borrowed, active loans, overdue, wishlist, reviews, ratings

**What you'll see:**
- 📚 Total Borrowings
- ⏰ Currently Borrowed
- ⚠️ Overdue Books (red if any)
- ❤️ Wishlist Items
- ⭐ Reviews Written
- 📊 Average Rating

---

### 4️⃣ **Admin Dashboard**
- Enhanced statistics dashboard at `/admin`
- Shows: books, users, borrowing trends, top users, return rates

**What admins see:**
- 📊 Key metrics cards
- 📈 Borrowing trends (last 7 days)
- 🏆 Top 5 books & users
- 📋 Recent borrowings list
- 📊 Return rate analysis

---

## 📁 Files Changed

### **New Services:**
- `src/Service/OverdueService.php` - Overdue detection
- `src/Service/NotificationService.php` - Email sending
- `src/Service/AdminStatisticsService.php` - Statistics

### **New Command:**
- `src/Command/SendNotificationsCommand.php` - Daily task runner

### **Modified Entities:**
- `src/Entity/Emprunt.php` - Added status field
- `src/Entity/Utilisateur.php` - Added statistics methods

### **Modified Controllers:**
- `src/Controller/CatalogController.php` - Overdue check
- `src/Controller/UserAccountController.php` - Status update
- `src/Controller/Admin/DashboardController.php` - New dashboard

### **New Templates:**
- `templates/emails/base.html.twig`
- `templates/emails/return_reminder.html.twig`
- `templates/emails/overdue_notice.html.twig`
- `templates/emails/book_available.html.twig`
- `templates/emails/welcome.html.twig`
- `templates/emails/verification.html.twig`

### **Modified Templates:**
- `templates/user_account/index.html.twig` - New stats dashboard
- `templates/admin/dashboard.html.twig` - New admin dashboard

### **Database:**
- `migrations/Version20251203192217.php` - New migration (status field)

---

## 🧪 Testing Checklist

- [ ] User can't borrow if overdue
- [ ] User can borrow after returning overdue
- [ ] Profile page shows 6 statistics cards
- [ ] Admin dashboard loads with metrics
- [ ] Email command runs without errors
- [ ] Overdue status shows in borrowing list
- [ ] Member badge displays correctly

---

## ⚡ Common Issues & Fixes

**Issue: "Cannot send email"**
→ Solution: Set `MAILER_DSN` in `.env.local`

**Issue: "Service not found"**
→ Solution: Run `php bin/console cache:clear`

**Issue: "Migration failed"**
→ Solution: Run `php bin/console doctrine:migrations:migrate`

**Issue: "Page shows blank dashboard"**
→ Solution: Clear browser cache and refresh

---

## 📞 Useful Commands

```bash
# Clear cache
php bin/console cache:clear

# Test notifications
php bin/console app:send-notifications

# Check migrations
php bin/console doctrine:migrations:status

# Migrate database
php bin/console doctrine:migrations:migrate

# View app info
php bin/console about

# Start dev server
php -S 127.0.0.1:8000 -t public/
```

---

## 🎯 Next Features to Consider

1. **Book Reservations** - Reserve out-of-stock books
2. **Advanced Search** - Better filtering & search
3. **Recommendations** - "Similar books" feature
4. **Multiple Wishlists** - Organize books by category
5. **SMS Alerts** - Text message reminders

---

## 📚 Documentation

See `IMPLEMENTATION_COMPLETED.md` for full technical details.
See `APP_REVIEW_CURRENT_STATE.md` for feature overview.

---

**All set! Enjoy your enhanced library management system! 📚**
