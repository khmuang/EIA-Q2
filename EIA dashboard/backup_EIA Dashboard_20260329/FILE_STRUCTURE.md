# EIA Dashboard Project Structure
Created on: 2026-03-28

This document outlines the file structure and relationships of the EIA Dashboard system.

## Directory Map

```text
EIA dashboard/
├── 📂 Image/                      (Image assets)
│   ├── bg-computer.jpg            (Main hero background)
│   ├── bg-computer-2.jpg-9.jpg    (Alternative tech/server backgrounds)
│   ├── illustration-1.jpg-3.jpg   (Modern flat illustrations)
│   └── Logo RIS.png               (Organization logo)
│
├── 🏠 index.php                   (Main Dashboard: Executive Summary & KPIs)
├── 📋 inventory_list.php          (Inventory Table: Integrated UI with 11 columns)
├── 🔍 inventory_detail.php        (Asset Details: Single machine deep-dive)
│
├── 📊 top_bu_compliance.php       (BU Ranking: Score summary by Business Unit)
│
├── 📂 Insight Reports (Strategic deep-dives)
│   ├── 🔑 admin_audit.php         (Standard Admin Rights Check)
│   ├── 📈 patch_insight.php        (Missing Critical Patches Analysis)
│   └── 💤 inactive_assets.php      (Asset Lifecycle - Inactive 30+ Days)
│
├── ⚙️ System Files (Core Logic)
│   ├── config.php                 (MySQL Database Connection Settings)
│   ├── query.php                  (Master SQL CTE Queries)
│   ├── login.php / logout.php     (Auth System)
│   ├── validate.php / adauthen.php (Validation logic)
│   ├── dashboard_cache.json       (Cache for index page - 60 min)
│   └── bu_ranking_cache.json      (Cache for BU ranking - 60 min)
│
└── 🐍 read_excel_summary.py       (Python helper for data processing)
```

## System Standards
- **UI Framework:** Tailwind CSS
- **Design Style:** Premium UI V2 (Glassmorphism, 12px rounded corners)
- Features: Dark Mode, Smooth Back-to-Top, Automatic Caching (60 min)
- Timezone: Asia/Bangkok

---

# ระบบการจัดการข้อมูลสำรอง (Data Caching System)
ระบบนี้ถูกออกแบบมาเพื่อเพิ่มความเร็วในการใช้งานสูงสุดและลดภาระของ Web Server โดยใช้สถาปัตยกรรมแบบ **Asynchronous Background Caching** (Enterprise Standard)

## 1. หลักการทำงานพื้นฐาน
*   **Zero Wait-time:** ผู้ใช้งานไม่ต้องรอคอยการโหลดข้อมูลจากฐานข้อมูล (Database) โดยตรง ทำให้หน้าเว็บโหลดเร็วทันที (Instant Load)
*   **Background Worker:** ใช้ไฟล์ `cache_worker.php` เป็น "เครื่องจักรกลาง" ทำหน้าที่ประมวลผลคำสั่ง SQL ชุดใหญ่เบื้องหลังเพียงลำพัง
*   **Lock Mechanism:** มีระบบ **Atomic Lock (.lock)** ป้องกันการรันคำสั่ง SQL ซ้อนกัน (Cache Stampede) ช่วยให้ Database ไม่เกิดอาการ Spike แม้มีผู้เข้าใช้งานพร้อมกันจำนวนมาก (เช่น 50+ sessions)

## 2. กระบวนการอัปเดตข้อมูล (Workflow)
1.  **ตรวจสอบ (Check):** เมื่อหน้าเว็บถูกเรียก ระบบจะตรวจเช็คไฟล์ Cache (.json) ทันที
2.  **เงื่อนไขเวลา (60 นาที):**
    *   หากข้อมูลยังใหม่ (< 60 นาที) -> **ดึงข้อมูลมาแสดงผลทันที**
    *   หากข้อมูลหมดอายุ (> 60 นาที) -> ระบบจะสั่งงาน `cache_worker.php` ให้แอบรันเบื้องหลัง และ **ส่งข้อมูลเดิมให้ผู้ใช้ดูก่อนทันที** โดยผู้ใช้ไม่รู้สึกว่าช้า
3.  **ความปลอดภัย (Protection):** หากมีคนสั่งรันงานไปแล้ว (มีไฟล์ .lock) ระบบจะข้ามการสั่งรันซ้ำ เพื่อป้องกันภาระส่วนเกินบน Server

## 3. รายละเอียดไฟล์และการตั้งค่า
| หน้าเว็บ | ชื่อไฟล์ Cache (.json) | ระบบป้องกัน (.lock) | ระยะเวลาอัปเดต |
| :--- | :--- | :--- | :--- |
| **หน้า Dashboard หลัก** | `dashboard_cache.json` | `dashboard.lock` | ทุก 60 นาที |
| **Standard Admin Rights** | `admin_audit_cache.json` | `admin_audit.lock` | ทุก 60 นาที |
| **Patch Management** | `patch_insight_cache.json` | `patch_insight.lock` | ทุก 60 นาที |
| **Inactive Assets** | `inactive_assets_cache.json` | `inactive_assets.lock` | ทุก 60 นาที |
| **BU Ranking** | `bu_ranking_cache.json` | `bu_ranking.lock` | ทุก 60 นาที |

## 4. ข้อกำหนดทางเทคนิค
*   **PHP CLI:** กำหนด Path ของตัวรันไว้ที่ `C:\xampp\php\php.exe` สำหรับรันงานเบื้องหลังบน Windows
*   **Data Integrity:** Master SQL ทั้งหมดถูกรวมศูนย์ไว้ที่ `query.php` เพื่อให้ข้อมูลในทุกไฟล์ Cache มีความสอดคล้องกัน 100%

