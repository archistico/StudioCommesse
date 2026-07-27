# Testing

Il gate autoritativo è:

```powershell
.\scripts\validate.ps1
```

Esito atteso:

```text
M9.1 HOTFIX 2 VALIDATION PASSED
```

Il gate esegue:

- validazione Composer e audit dipendenze;
- installazione e controllo asset npm;
- lint PHP, YAML e Twig;
- contratti Doctrine e Symfony;
- migrazioni su database di test pulito;
- sincronizzazione mapping/schema;
- PHPStan livello 8;
- PHPUnit.

La baseline M8 è validata. La candidate M9.1 aggiunge backup e ripristino coordinati, mantenendo in regressione l’intera catena M1–M8.

M6.1 aggiunge test per dettaglio ore multi-persona, filtro assegnatario, report globale, filtri, paginazione e permessi.

M6.2 aggiunge test per:

- accesso soci e rifiuto dei collaboratori alla valutazione individuale;
- raggruppamento giornaliero, totali, media e distinzione fatturabile;
- filtri per persona, periodo, cliente, responsabile, commessa e fatturabilità;
- aggregazioni di commessa senza duplicazione delle stime in presenza di più registrazioni;
- chiusura operativa, economica e complessiva;
- incoerenza fra stato commessa chiuso e attività ancora aperte;
- rilevazione di commesse ferme;
- rilevazione del sovraccarico per attività e ore residue;
- riepiloghi per persona, cliente e mese nel periodo;
- restrizione dell’area `Controllo` ai soci;
- persistenza di filtri e ordinamento nella sessione;
- visibilità del pannello di chiusura soltanto ai soci;
- query SQLite aggregate e assenza di nuove migrazioni.

M6.2 Hotfix 2 aggiunge il contratto `FormLayoutContractTest`, che per ciascun Symfony Form Type:

- estrae i campi configurati con `add()`;
- verifica che ciascun campo sia renderizzato esplicitamente nel template corretto;
- vieta `form_widget(form)` sui form CRUD;
- impone `form_rest()` prima del pulsante e `render_rest: false` alla chiusura;
- verifica il pulsante primario a larghezza piena;
- controlla che il breakpoint a due colonne sia `lg` e non `md`;
- mantiene a riga intera i campi testuali estesi.


M6.2 Hotfix 3 corregge tre regressioni di analisi statica senza ridurre il livello PHPStan:

- ri-verifica del tipo `Project` dopo la possibile assegnazione a `null`;
- filtro dei parametri del report Ore basato soltanto sull'esclusione di `null`;
- lettura diretta delle stringhe da `InputBag` senza `is_scalar()` ridondante.


M6.2 Hotfix 4 corregge la portabilità e l'aderenza dei test al markup effettivo:

- verifica diretta dei link sidebar `/controllo` e `/ore`;
- contratto della soglia di 2.400 minuti indipendente dal separatore `_`;
- normalizzazione delle terminazioni di riga prima dei confronti testuali dei controller.


## M6.3

M6.3 aggiunge verifiche per:

- caricamento di `/attivita?assignee=me` senza conversioni intere;
- nome cliente cliccabile e assenza di colonne `Apri`/`Azioni`;
- attributo DataTables su ogni tabella applicativa;
- valori di ordinamento macchina per date italiane, durate e importi;
- dipendenze npm bloccate per DataTables, Responsive e jQuery;
- assenza di colonne Bootstrap `sm`/`md` e larghezza piena sotto `lg`;
- operazioni distruttive assenti dalle tabelle e presenti nelle pagine di modifica;
- asset DataTables/Responsive installati localmente e relative licenze.

## M6.3 Hotfix 1

La hotfix aggiunge regressioni mirate per:

- presenza del comando `Archivia cliente` nella pagina di modifica;
- presenza del comando `Archivia commessa` per i soci nella pagina di modifica;
- mantenimento dei vincoli di dominio che impediscono l'archiviazione quando non ammessa;
- verifica semantica del valore `assignee=me` tramite il valore effettivo del campo del form, senza dipendere dalla serializzazione dell'attributo HTML `selected`.


## M7

M7 aggiunge test per:

- mapping e vincolo attività/commessa dell’entità `Attachment`;
- archiviazione fuori da `public`, chiave casuale e impronta SHA-256;
- limite di 10 MiB, allowlist, MIME e firma del contenuto;
- rifiuto di file eseguibili, estensioni non autorizzate e firma EICAR;
- caricamento e collegamento a un’attività;
- download protetto con header `nosniff`;
- consultazione generale e gestione limitata da `AttachmentVoter`;
- modifica dei metadati ed eliminazione dalla pagina documento;
- commesse archiviate in sola lettura documentale;
- presenza della nuova migrazione, della voce menu e del contratto sullo spazio documentale.


## M8

M8 aggiunge test per:

- visibilità completa di spese e incassi per i soci;
- visibilità limitata alle proprie spese per i collaboratori;
- gestione delle sole spese proprie tramite `ExpenseVoter`;
- divieto dei form incasso ai collaboratori;
- accesso al report mensile riservato ai soci;
- filtro per mese e commessa;
- dettaglio delle registrazioni ore e esclusione delle registrazioni fuori periodo;
- riepiloghi per commessa e per azione;
- esportazione CSV;
- contratto statico `monthly-report-contract.php`;
- assenza di nuove migrazioni e dipendenze.


## M9.1

M9.1 aggiunge verifiche per:

- creazione di uno snapshot SQLite reale con `VACUUM INTO`;
- manifest versionato e hash SHA-256;
- corrispondenza tra record `attachment` e file salvati;
- rifiuto di backup manomessi;
- ripristino effettivo di database e allegati;
- creazione e verifica del backup automatico pre-ripristino;
- disattivazione della modalità manutenzione al termine;
- risposta HTTP 503 durante la manutenzione;
- conferma esplicita `RESTORE`;
- estrazione ZIP protetta da path traversal e collegamenti simbolici;
- smoke test `app:backup:create` e `app:backup:verify` sul database di test migrato.

- verificare i filtri vuoti del report Ore, il riepilogo importi dovuti per cliente e la selezione automatica dell’ultimo backup;
