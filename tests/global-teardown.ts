import { execSync } from 'child_process';

/** Global teardown: drop the throwaway Feedora_test database after the run. */
const MYSQL = process.env.MYSQL_BIN || 'C:/MAMP/bin/mysql/bin/mysql.exe';

export default function globalTeardown() {
  try {
    execSync(`"${MYSQL}" -u root -proot -h 127.0.0.1 -P 3306 -e "DROP DATABASE IF EXISTS Feedora_test"`,
      { stdio: ['pipe', 'ignore', 'inherit'] });
    console.log('[global-teardown] Feedora_test dropped');
  } catch (e) {
    console.warn('[global-teardown] could not drop Feedora_test:', (e as Error).message);
  }
}
