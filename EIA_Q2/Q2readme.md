1. คุณคือผู้เชี่ยวชาญด้าน Web programming เพื่อจัดทำ data tracking dashboard อัพเดทผลการดำเนินงานในหัวข้อ topic Q2 ตามตารางด้านใต้			
2. โดยมี topic Q2 ทั้ง 9 และนับจำนวนผลสำเร็จตาม Action status ค่า N และ Blank ถือเป็นค่าเดียวกัน			
Topic Q2	support group	Action status	EIA Phase
1.1 IT Asset Management	Groups	Asset update status Y/N	Q1,Q2 or Q2
1.2 Install GLPI agent	Serviced By	GLPI setup status Y/N	Q1,Q2 or Q2
2. Update OS	Serviced By	OS update status Y/N	Q1,Q2 or Q2
3. Require Restart	Serviced By	Restart update Y/N	Q1,Q2 or Q2
4. Antivirus Installation	Serviced By	AV update Y/N	Q1,Q2 or Q2
5. Built-in Firewall Enablement	Serviced By	Firewall update Y/N	Q1,Q2 or Q2
6. Client join domain	Serviced By	Join domain update Y/N	Q1,Q2 or Q2
7. Privileged User management	Serviced By	Std admin update Y/N	Q1,Q2 or Q2
8. Document Request	Serviced By	Document request update Y/N	Q1,Q2 or Q2
			
3. แนวทางการ publish dashboard			
3.1 Publish บน Github โดยมีทำ gitignore ห้ามนำไฟล์ excel และข้อมูลสำคัญเกี่ยวกับ PDPA ขึ้นระบบ			
3.2 สร้าง Database บน supabase เพื่อเก็บ result ที่จะแสดงใน Dashboard			

4. ส่วนการแสดงผลประกอบด้วย
4.1 Overall Compliance Performance Score
4.2 Total Audited Units
4.3 Data Last Refresh
4.4 Audit Deadline Coundown date (20 May 2026)
4.5 Export CSV Summary
4.6 View by Service Team
4.7 EIA Performance Report Dashboard © 2026
จัดทำขึ้นเพื่อรายงานผลสรุปด้านความปลอดภัย Endpoint|
Visitor Counter
Prepared by Endpoint Management Team
4.8 Go to top
4.9 Floating Action menu by topic
4.10 แยกราย Topic ที่มีข้อมูล Service Team	Y (Success)	N (Pending)	Compliance Rate	Responsibility Share
4.11 สร้างกราฟแท่ง Compliance Rate Comparison by Topic (%) โดยมี target 81 % ทุกหัวข้อจึงจะผ่าน
4.12 สามารถ filter เป็นรายทีมได้
4.13 สามารถ filter EIA Phase ได้ โดย Q1,Q2 ให้แสดงเป็น Q1