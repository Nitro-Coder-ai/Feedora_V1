import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';

/**
 * Global setup: create + seed the throwaway Feedora_test database from the
 * current Feedora.sql, so no second database has to live on the machine between
 * runs. global-teardown drops it again. Tests therefore stay isolated from the
 * real `Feedora` DB without a persistent test database.
 *
 * Override the mysql client path with MYSQL_BIN if it differs from MAMP's.
 */
const MYSQL = process.env.MYSQL_BIN || 'C:/MAMP/bin/mysql/bin/mysql.exe';
const CONN = '-u root -proot -h 127.0.0.1 -P 3306';
const DB = 'Feedora_test';

function run(args: string, input?: string) {
  execSync(`"${MYSQL}" ${args}`, { input, stdio: ['pipe', 'ignore', 'inherit'] });
}

export default function globalSetup() {
  run(`${CONN} -e "DROP DATABASE IF EXISTS ${DB}; CREATE DATABASE ${DB} CHARACTER SET utf8mb4"`);

  // Load Feedora.sql minus its own CREATE DATABASE / USE lines (which target Feedora).
  const sql = fs
    .readFileSync(path.join(__dirname, '..', 'Feedora.sql'), 'utf8')
    .split(/\r?\n/)
    .filter((l) => !/^\s*CREATE DATABASE/i.test(l) && !/^\s*USE\s+Feedora/i.test(l))
    .join('\n');

  run(`${CONN} ${DB}`, sql);
  console.log(`[global-setup] ${DB} created + seeded from Feedora.sql`);
}
