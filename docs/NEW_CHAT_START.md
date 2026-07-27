# Avvio nuova chat

Progetto: StudioCommesse.

- baseline validata: M9.1 Hotfix 3 corretta;
- archivio baseline: `StudioCommesse_M9.1_Hotfix3_Corretto.zip`;
- gate baseline: `M9.1 HOTFIX 2 VALIDATION PASSED`;
- baseline testata con 171 test e 1.631 asserzioni;
- candidate corrente: M9.2-A `0.9.2-M9.2-A-HF1`;
- archivio candidate atteso: `StudioCommesse_M9.2-A_Hotfix1_PowerShell_Parser.zip`;
- gate atteso: `M9.2-A HOTFIX 1 VALIDATION PASSED`;
- prossimo passo dopo la validazione: M9.2-B audit autorizzazioni e riservatezza.

M9.2-A non introduce funzioni di dominio. Riallinea tutti i riferimenti autoritativi e aggiunge un packaging ripetibile che esclude automaticamente configurazioni locali, database, allegati, backup, log, cache, dipendenze installate e asset generati.

## M9.2-A Hotfix 1

Corregge esclusivamente la sintassi PowerShell del ciclo ricorsivo in `package-release.ps1`; nessuna funzione applicativa è stata modificata. Gate: `M9.2-A HOTFIX 1 VALIDATION PASSED`.
