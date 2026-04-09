import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

async function main() {
  // 1. เพิ่ม Microsoft Office 2021
  await prisma.license.create({
    data: {
      name: 'Microsoft Office 2021',
      licenseKey: 'OFF-123-ABC-789',
      totalSeats: 10,
      startDate: new Date('2024-01-01'),
      expiryDate: new Date('2026-01-01'),
      status: 'Active'
    }
  });

  // 2. เพิ่ม AutoCAD 2025 (ไม่ใส่ Key)
  await prisma.license.create({
    data: {
      name: 'AutoCAD 2025',
      licenseKey: null,
      totalSeats: 3,
      startDate: new Date('2024-06-15'),
      expiryDate: new Date('2025-06-15'),
      status: 'Active'
    }
  });

  console.log('Successfully added 2 sample licenses!');
}

main()
  .catch((e) => {
    console.error('Error adding samples:', e);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
