import { PrismaClient } from '@prisma/client'

const prisma = new PrismaClient()

async function main() {
  // สร้างผู้ใช้ใหม่
  const newUser = await prisma.user.create({
    data: {
      email: `test_${Date.now()}@example.com`,
      name: 'Test XAMPP User',
    },
  })
  console.log('✅ Created new user:', newUser)

  // ดึงผู้ใช้งานทั้งหมด
  const allUsers = await prisma.user.findMany()
  console.log('📋 All users in database:', allUsers)
}

main()
  .catch((e) => {
    console.error('❌ Error:', e)
    process.exit(1)
  })
  .finally(async () => {
    await prisma.$disconnect()
  })
