import { copyFile, mkdir, readFile, writeFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const expectedVersion = '1.4.0';
const root = path.resolve(import.meta.dirname, '..');
const sourceRoot = path.join(root, 'node_modules', '@tabler', 'core');
const destinationRoot = path.join(root, 'public', 'vendor', 'tabler');
const dataTablesDestinationRoot = path.join(root, 'public', 'vendor', 'datatables');
const jqueryDestinationRoot = path.join(root, 'public', 'vendor', 'jquery');

const files = [
  ['dist/css/tabler.min.css', 'css/tabler.min.css'],
  ['dist/js/tabler.min.js', 'js/tabler.min.js'],
];

if (!existsSync(sourceRoot)) {
  console.error('Tabler non è installato. Eseguire prima "npm ci".');
  process.exit(1);
}

const packageJsonPath = path.join(sourceRoot, 'package.json');
if (!existsSync(packageJsonPath)) {
  console.error('Metadati del pacchetto Tabler mancanti: package.json');
  process.exit(1);
}

const packageMetadata = JSON.parse(await readFile(packageJsonPath, 'utf8'));
if (packageMetadata.version !== expectedVersion) {
  console.error(`Versione Tabler inattesa: ${packageMetadata.version ?? 'sconosciuta'}; attesa ${expectedVersion}.`);
  process.exit(1);
}

for (const [sourceRelative, destinationRelative] of files) {
  const source = path.join(sourceRoot, sourceRelative);
  const destination = path.join(destinationRoot, destinationRelative);

  if (!existsSync(source)) {
    console.error(`File Tabler mancante: ${sourceRelative}`);
    process.exit(1);
  }

  await mkdir(path.dirname(destination), { recursive: true });
  await copyFile(source, destination);
}

await mkdir(destinationRoot, { recursive: true });
await writeFile(
  path.join(destinationRoot, 'NOTICE.txt'),
  [
    `Tabler ${expectedVersion}`,
    'Copyright (c) Paweł Kuna and Tabler contributors.',
    'Licensed under the MIT License.',
    'See THIRD_PARTY_NOTICES.md in the project root.',
    '',
  ].join('\n'),
  'utf8',
);

const additionalPackages = [
  {
    name: 'jQuery',
    version: '3.6.4',
    root: path.join(root, 'node_modules', 'jquery'),
    destination: jqueryDestinationRoot,
    files: [
      ['dist/jquery.min.js', 'jquery.min.js'],
      ['LICENSE.txt', 'LICENSE.txt'],
    ],
  },
  {
    name: 'DataTables core',
    version: '2.3.8',
    root: path.join(root, 'node_modules', 'datatables.net'),
    destination: dataTablesDestinationRoot,
    files: [
      ['js/dataTables.min.js', 'js/dataTables.min.js'],
      ['License.txt', 'licenses/DataTables.txt'],
    ],
  },
  {
    name: 'DataTables Bootstrap 5',
    version: '2.3.8',
    root: path.join(root, 'node_modules', 'datatables.net-bs5'),
    destination: dataTablesDestinationRoot,
    files: [
      ['js/dataTables.bootstrap5.min.js', 'js/dataTables.bootstrap5.min.js'],
      ['css/dataTables.bootstrap5.min.css', 'css/dataTables.bootstrap5.min.css'],
      ['License.txt', 'licenses/DataTables-Bootstrap5.txt'],
    ],
  },
  {
    name: 'DataTables Responsive',
    version: '3.0.8',
    root: path.join(root, 'node_modules', 'datatables.net-responsive'),
    destination: dataTablesDestinationRoot,
    files: [
      ['js/dataTables.responsive.min.js', 'js/dataTables.responsive.min.js'],
      ['License.txt', 'licenses/DataTables-Responsive.txt'],
    ],
  },
  {
    name: 'DataTables Responsive Bootstrap 5',
    version: '3.0.8',
    root: path.join(root, 'node_modules', 'datatables.net-responsive-bs5'),
    destination: dataTablesDestinationRoot,
    files: [
      ['js/responsive.bootstrap5.min.js', 'js/responsive.bootstrap5.min.js'],
      ['css/responsive.bootstrap5.min.css', 'css/responsive.bootstrap5.min.css'],
      ['License.txt', 'licenses/DataTables-Responsive-Bootstrap5.txt'],
    ],
  },
];

for (const packageDefinition of additionalPackages) {
  const metadataPath = path.join(packageDefinition.root, 'package.json');
  if (!existsSync(metadataPath)) {
    console.error(`${packageDefinition.name} non è installato. Eseguire prima "npm ci".`);
    process.exit(1);
  }

  const metadata = JSON.parse(await readFile(metadataPath, 'utf8'));
  if (metadata.version !== packageDefinition.version) {
    console.error(`Versione ${packageDefinition.name} inattesa: ${metadata.version ?? 'sconosciuta'}; attesa ${packageDefinition.version}.`);
    process.exit(1);
  }

  for (const [sourceRelative, destinationRelative] of packageDefinition.files) {
    const source = path.join(packageDefinition.root, sourceRelative);
    const destination = path.join(packageDefinition.destination, destinationRelative);
    if (!existsSync(source)) {
      console.error(`File ${packageDefinition.name} mancante: ${sourceRelative}`);
      process.exit(1);
    }
    await mkdir(path.dirname(destination), { recursive: true });
    await copyFile(source, destination);
  }
}

console.log(`Asset Tabler ${expectedVersion}, jQuery 3.6.4, DataTables 2.3.8 e Responsive 3.0.8 copiati in public/vendor.`);
