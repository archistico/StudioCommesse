import { existsSync, statSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const root = path.resolve(import.meta.dirname, '..');
const requiredFiles = [
  'public/vendor/tabler/css/tabler.min.css',
  'public/vendor/tabler/js/tabler.min.js',
  'public/vendor/tabler/NOTICE.txt',
  'public/vendor/datatables/js/dataTables.min.js',
  'public/vendor/datatables/js/dataTables.bootstrap5.min.js',
  'public/vendor/datatables/js/dataTables.responsive.min.js',
  'public/vendor/datatables/js/responsive.bootstrap5.min.js',
  'public/vendor/datatables/css/dataTables.bootstrap5.min.css',
  'public/vendor/datatables/css/responsive.bootstrap5.min.css',
  'public/vendor/datatables/licenses/DataTables.txt',
  'public/vendor/datatables/licenses/DataTables-Bootstrap5.txt',
  'public/vendor/datatables/licenses/DataTables-Responsive.txt',
  'public/vendor/datatables/licenses/DataTables-Responsive-Bootstrap5.txt',
  'public/vendor/jquery/jquery.min.js',
  'public/vendor/jquery/LICENSE.txt',
  'public/assets/css/app.css',
  'public/assets/js/app.js',
  'THIRD_PARTY_NOTICES.md',
];

let failed = false;
for (const relativePath of requiredFiles) {
  const absolutePath = path.join(root, relativePath);
  if (!existsSync(absolutePath) || statSync(absolutePath).size === 0) {
    console.error(`Asset o avviso licenza mancante/vuoto: ${relativePath}`);
    failed = true;
  }
}

if (failed) {
  process.exit(1);
}

console.log('Controllo asset e avvisi licenza superato.');
