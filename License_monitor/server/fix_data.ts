import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

async function main() {
  console.log('>>> Cleaning old data...');
  await prisma.usageLog.deleteMany();
  await prisma.license.deleteMany();

  console.log('>>> Inserting 2 new sample licenses...');
  
  const l1 = await prisma.license.create({
    data: {
      name: 'Microsoft Office 2021 Professional',
      licenseKey: 'OFF-111-AAA-222',
      totalSeats: 10,
      startDate: new Date('2024-01-01'),
      expiryDate: new Date('2026-01-01'),
    }
  });

  const l2 = await prisma.license.create({
    data: {
      name: 'AutoCAD 2025 (Annual Subscription)',
      licenseKey: null,
      totalSeats: 3,
      startDate: new Date('2024-05-15'),
      expiryDate: new Date('2025-05-15'),
    }
  });

  console.log('>>> CREATED SUCCESS:');
  console.log('1.', l1.name);
  console.log('2.', l2.name);
  
  const all = await prisma.license.findMany();
  console.log('>>> CURRENT DATABASE COUNT:', all.length);
}

main()
  .catch((e) => {
    console.error('FAILED:', e.message);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
