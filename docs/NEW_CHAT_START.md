# Avvio nuova chat

Progetto: StudioCommesse.

- baseline validata: M8;
- archivio baseline: `StudioCommesse_M8_Report_Mensile_Economia_Ruoli.zip`;
- candidate corrente: M9.1 Hotfix 2 `0.9.1-M9.1-HF2`;
- archivio candidate atteso: `StudioCommesse_M9.1_Hotfix2_Backup_Contract.zip`;
- gate atteso: `M9.1 HOTFIX 2 VALIDATION PASSED`;
- prossima fase dopo validazione: M9.2 hardening e collaudo di rilascio.

M9.1 Hotfix 2 mantiene tutte le funzioni di M9.1 Hotfix 1 e corregge il contratto PHPUnit delle versioni di migrazione, evitando l’interpolazione accidentale di `$databasePath` nella stringa attesa.
