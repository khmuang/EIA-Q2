import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

async function main() {
  await prisma.license.deleteMany(); // เคลียร์ข้อมูลเก่าถ้ามี
  
  await prisma.license.create({
    data: {
      name: 'Adobe Creative Cloud',
      licenseKey: 'ADBE-1234-5678-9012',
      totalSeats: 5,
      status: 'Active',
      usageLogs: {
        create: [
          { userName: 'Somsak.S', machineName: 'DESIGN-01' }
        ]
      }
    }
  });

  await prisma.license.create({
    data: {
      name: 'SolidWorks 2024',
      licenseKey: 'SW-9988-7766-5544',
      totalSeats: 2,
      status: 'Active'
    }
  });

  console.log('Seed data created successfully!');
}

main()
  .catch((e) => console.error(e))
  .finally(async () => await prisma.$disconnect());
