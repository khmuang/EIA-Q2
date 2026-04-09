# EIA Dashboard Project Structure & Principles
Updated on: 2026-03-30

ระบบ Dashboard สำหรับติดตามความปลอดภัยและมาตรฐานอุปกรณ์คอมพิวเตอร์ (KACE Integrated) ออกแบบด้วยมาตรฐาน Premium UI V2 และระบบ Caching ระดับ Enterprise

## 1. Directory Map (โครงสร้างไฟล์)

```text
EIA dashboard/
├── 📂 Image/                      (Image assets: bg, logo, illustrations)
├── 🔑 Core Entry (ส่วนการเข้าถึง)
│   ├── login.php                  (Auth Login: AD Multi-domain + Whitelist)
│   ├── adauthen.php               (LDAP Helper: AD Connection & Diagnostic Logs)
│   ├── authorized_users.txt       (Whitelist: รายชื่อผู้มีสิทธิ์เข้าใช้งาน)
│   └── logout.php                 (Session Destroyer)
├── 🏠 Main Dashboard (หน้าหลัก)
│   ├── index.php                  (Executive Summary Dashboard)
│   └── dashboard_cache.json       (Cached data for index)
├── 📊 Analysis Reports (รายงานวิเคราะห์ผล)
│   ├── top_bu_compliance.php      (BU Ranking Dashboard)
│   ├── admin_audit.php            (Standard Admin Rights Report)
│   ├── patch_insight.php          (Missing Patches Detail Report)
│   ├── inactive_assets.php        (Inactive Assets Cleanup Report)
│   └── *_cache.json               (JSON files for each report)
├── 📋 Asset Inventory (ส่วนรายการอุปกรณ์)
│   ├── inventory_list.php         (Live Filterable Asset Table)
│   └── inventory_detail.php       (Single Asset Deep-dive)
├── ⚙️ System Engine (เครื่องจักรเบื้องหลัง)
│   ├── cache_worker.php           (Background Worker: Engine สำหรับรัน SQL)
│   ├── worker_log.txt             (System Logs: บันทึกสถานะการทำงาน)
│   ├── config.php                 (Database Connection)
│   └── query.php                  (Centralized SQL Repository)
└── 📝 Documentation
    └── FILE_STRUCTURE.md          (This document)
```

## 2. Principles of Operation (หลักการทำงาน)

### A. ระบบความปลอดภัย 2 ชั้น (Dual-Factor Authentication)
1. **Identity Check:** ตรวจสอบ Username/Password ผ่าน Active Directory (AD) รองรับ 3 Domain หลัก (Central, CMG, OFM)
2. **Authorization Check:** ตรวจสอบว่า Username นั้นมีชื่ออยู่ใน `authorized_users.txt` หรือไม่ หากไม่มีจะถูกปฏิเสธการเข้าถึงแม้รหัสผ่านจะถูกต้อง

### B. ระบบประมวลผลข้อมูล (Asynchronous Background Caching)
- **Instant Loading:** ข้อมูลแสดงผลจากไฟล์ JSON ทันที ไม่ต้องรอ Query จากฐานข้อมูล
- **Auto-Refresh (60 min):** หากข้อมูลเก่าเกิน 60 นาที ระบบจะกระตุ้น `cache_worker.php` ให้ทำงานเบื้องหลังโดยอัตโนมัติ
- **Concurrency Protection:** ใช้ระบบ Atomic Locking (.lock) เพื่อป้องกันการรัน Query ซ้ำซ้อนกันในเวลาเดียวกัน ช่วยลดภาระ Server 100%

### C. มาตรฐานการแสดงผล (UI/UX Standards)
- **Style:** Premium UI V2 (Glassmorphism, Backdrop Blur 20px)
- **Grading:** ระบบแบ่งเกณฑ์สุขภาพ 4 ระดับ (Emerald >=90%, Blue 70-89%, Amber 50-69%, Rose <50%)
- **Timezone:** Asia/Bangkok (GMT+7) ทุกจุดแสดงเวลาอัปเดตล่าสุด (Last Updated)

## 3. Maintenance (การดูแลรักษา)
- **Logs:** ตรวจสอบสถานะการทำงานได้ที่ `worker_log.txt`
- **Users:** เพิ่ม/ลด ผู้ใช้งานได้ที่ `authorized_users.txt`
- **SQL:** แก้ไขคำสั่ง SQL ศูนย์กลางได้ที่ `query.php`
