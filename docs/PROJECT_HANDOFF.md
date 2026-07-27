# Project Handoff

## Stato autoritativo

- ultima baseline validata: **M8**;
- esito: `M8 VALIDATION PASSED`;
- archivio baseline: `StudioCommesse_M8_Report_Mensile_Economia_Ruoli.zip`;
- candidate corrente: **M9.1 Hotfix 2 `0.9.1-M9.1-HF2`**;
- archivio candidate atteso: `StudioCommesse_M9.1_Hotfix2_Backup_Contract.zip`;
- gate richiesto: `M9.1 HOTFIX 2 VALIDATION PASSED`.

## M9.1 Hotfix 2 candidate

- backup coordinato di SQLite e `var/storage/attachments`;
- formato `studio-commesse-backup-v1` con manifest JSON;
- snapshot SQLite con `VACUUM INTO`;
- allegati copiati esclusivamente in base ai record presenti nello snapshot;
- verifica di hash, dimensioni, inventario migrazioni, `integrity_check`, `foreign_key_check` e inventario file;
- lock condiviso sulle mutazioni documentali e lock esclusivo durante il backup;
- modalità manutenzione e lock richieste durante ripristino, migrazioni e verifica;
- manutenzione mantenuta attiva in caso di fallimento e sblocco esplicito `CLEAR`;
- backup automatico pre-ripristino;
- rollback di database e storage in caso di sostituzione fallita;
- wrapper `backup.ps1`, `verify-backup.ps1` e `restore-backup.ps1`, con rifiuto di path traversal, symlink, flussi NTFS alternativi e nomi speciali Windows;
- report soci degli importi dovuti per cliente;
- correzione filtri Ore vuoti e dei narrowing PHPStan;
- verifica automatica dell’ultimo backup o diagnostica dei file disponibili;
- corretto il contratto PHPUnit sulle versioni di migrazione senza modificare la logica di backup;
- nessuna migrazione e nessuna nuova dipendenza.

## Comandi ordinari

```powershell
.\scripts\setup.ps1 -SkipPartnerBootstrap
.\scripts\validate.ps1
.\scripts\load-fixtures.ps1
```

## Comandi backup

```powershell
.\scripts\backup.ps1
.\scripts\verify-backup.ps1
.\scripts\restore-backup.ps1 -Archive "<backup.zip>" -Confirm RESTORE
```

## Passo successivo dopo la validazione

M9.2: hardening finale, test end-to-end, installazione/ripristino controllati e manuale utente completo.
