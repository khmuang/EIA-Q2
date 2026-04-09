import express from 'express';
import cors from 'cors';
import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();
const app = express();
const PORT = 3001;

app.use(cors());
app.use(express.json());

// --- Dashboard Stats API ---
app.get('/api/stats', async (req, res) => {
  try {
    const totalLicenses = await prisma.license.count();
    const activeUsers = await prisma.usageLog.count({ where: { endedAt: null } });
    const today = new Date();
    const thirtyDaysLater = new Date();
    thirtyDaysLater.setDate(today.getDate() + 30);
    const expiredCount = await prisma.license.count({ where: { expiryDate: { lt: today } } });
    const expiringSoonCount = await prisma.license.count({ where: { expiryDate: { gte: today, lte: thirtyDaysLater } } });
    res.json({ totalLicenses, activeUsers, expiredCount, expiringSoonCount });
  } catch (error) { res.status(500).json(error); }
});

// --- Usage History API ---
app.get('/api/history', async (req, res) => {
  try {
    const history = await prisma.usageLog.findMany({
      include: { license: true },
      orderBy: { startedAt: 'desc' },
      take: 50
    });
    res.json(history);
  } catch (error) { res.status(500).json(error); }
});

// --- Main License API ---
app.get('/api/licenses', async (req, res) => {
  try {
    const licenses = await prisma.license.findMany({
      include: { usageLogs: { where: { endedAt: null } } },
      orderBy: { name: 'asc' }
    });
    res.json(licenses);
  } catch (error) { res.status(500).json({ error: 'Failed' }); }
});

app.post('/api/licenses', async (req, res) => {
  try {
    const license = await prisma.license.create({
      data: {
        name: req.body.name,
        licenseKey: req.body.licenseKey || null,
        totalSeats: parseInt(req.body.totalSeats),
        startDate: req.body.startDate ? new Date(req.body.startDate) : null,
        expiryDate: req.body.expiryDate ? new Date(req.body.expiryDate) : null,
        status: req.body.status || 'Active'
      }
    });
    res.json(license);
  } catch (error) { res.status(400).json({ error: 'Failed' }); }
});

// --- UPDATE LICENSE (RE-CHECKED) ---
app.put('/api/licenses/:id', async (req, res) => {
  const id = parseInt(req.params.id);
  console.log(`>>> Updating License ID: ${id}`, req.body);
  try {
    const updated = await prisma.license.update({
      where: { id: id },
      data: {
        name: req.body.name,
        licenseKey: req.body.licenseKey || null,
        totalSeats: parseInt(req.body.totalSeats),
        startDate: req.body.startDate ? new Date(req.body.startDate) : null,
        expiryDate: req.body.expiryDate ? new Date(req.body.expiryDate) : null,
        status: req.body.status
      }
    });
    console.log('>>> Update Success:', updated.name);
    res.json(updated);
  } catch (error: any) {
    console.error('>>> Update Failed:', error.message);
    res.status(400).json({ error: 'Update failed', details: error.message });
  }
});

app.delete('/api/licenses/:id', async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    await prisma.usageLog.deleteMany({ where: { licenseId: id } });
    await prisma.license.delete({ where: { id: id } });
    res.json({ message: 'Deleted' });
  } catch (error) { res.status(500).json({ error: 'Delete failed' }); }
});

app.post('/api/usage/start', async (req, res) => {
  try {
    const log = await prisma.usageLog.create({
      data: { licenseId: req.body.licenseId, userName: req.body.userName, machineName: req.body.machineName }
    });
    res.json(log);
  } catch (err) { res.status(500).json(err); }
});

app.post('/api/usage/end/:id', async (req, res) => {
  try {
    const log = await prisma.usageLog.update({
      where: { id: parseInt(req.params.id) },
      data: { endedAt: new Date() }
    });
    res.json(log);
  } catch (err) { res.status(500).json(err); }
});

// --- Debug Reset ---
app.get('/api/debug/reset', async (req, res) => {
  try {
    await prisma.usageLog.deleteMany();
    await prisma.license.deleteMany();
    const samples = [
      { name: 'Adobe Creative Cloud', licenseKey: 'ADBE-777-888', totalSeats: 5, startDate: new Date('2024-01-01'), expiryDate: new Date('2025-01-01') },
      { name: 'SketchUp Pro 2024', licenseKey: null, totalSeats: 2, startDate: new Date('2024-03-01'), expiryDate: new Date('2024-09-01') },
      { name: 'Windows 11 Pro', licenseKey: 'WIND-PRO-888', totalSeats: 50, startDate: new Date('2023-10-10'), expiryDate: null },
      { name: 'SolidWorks 2024', licenseKey: 'SW-9988-7766', totalSeats: 1, startDate: new Date('2024-06-01'), expiryDate: new Date('2027-06-01') },
      { name: 'Zoom Business', licenseKey: null, totalSeats: 20, startDate: new Date('2024-02-01'), expiryDate: new Date('2024-12-31') }
    ];
    for (const s of samples) { await prisma.license.create({ data: s }); }
    res.json({ message: 'Success' });
  } catch (err: any) { res.status(500).json({ error: err.message }); }
});

app.listen(PORT, () => console.log(`Server is running on http://localhost:${PORT}`));
