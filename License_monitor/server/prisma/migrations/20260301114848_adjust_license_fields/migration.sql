-- RedefineTables
PRAGMA defer_foreign_keys=ON;
PRAGMA foreign_keys=OFF;
CREATE TABLE "new_License" (
    "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "name" TEXT NOT NULL,
    "licenseKey" TEXT,
    "totalSeats" INTEGER NOT NULL DEFAULT 1,
    "startDate" DATETIME,
    "expiryDate" DATETIME,
    "status" TEXT NOT NULL DEFAULT 'Active',
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL
);
INSERT INTO "new_License" ("createdAt", "expiryDate", "id", "licenseKey", "name", "status", "totalSeats", "updatedAt") SELECT "createdAt", "expiryDate", "id", "licenseKey", "name", "status", "totalSeats", "updatedAt" FROM "License";
DROP TABLE "License";
ALTER TABLE "new_License" RENAME TO "License";
PRAGMA foreign_keys=ON;
PRAGMA defer_foreign_keys=OFF;
