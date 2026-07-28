# Decisioni autoritative

## D001 – Obiettivo operativo prioritario

Il prodotto serve prima di tutto a controllare commesse, attività e lavoro dei collaboratori. Pagamenti e spese sono secondari.

## D002 – Compenso non vincolato al costo orario

Una commessa può prevedere compenso:

- da definire;
- a corpo;
- a ore;
- misto.

Le ore registrate misurano sempre il lavoro svolto, ma non determinano automaticamente l'importo dovuto dal cliente.

## D003 – Nessuna duplicazione dell'urgenza

L'urgenza è un livello della sola priorità:

- bassa;
- normale;
- alta;
- urgente.

Non esiste un campo booleano separato.

## D004 – Stati minimali

Commessa e attività usano:

- da iniziare;
- in corso;
- in attesa;
- completata;
- annullata.

Archiviazione e situazione economica sono concetti separati.

## D005 – Ruoli

Esistono due ruoli globali:

- Socio;
- Collaboratore.

I due soci hanno uguali diritti. Il responsabile è assegnato alla singola commessa.

## D006 – Visibilità

Tutti i collaboratori vedono tutte le commesse e i dati operativi. Dati economici, costi e note riservate sono limitati ai soci e al responsabile della specifica commessa.

## D007 – Flussi semplici

- attività dei collaboratori senza approvazione;
- ore senza approvazione;
- pochi campi obbligatori;
- pagamenti semplicemente registrati;
- importi complessivi senza IVA/imponibile;
- nessuna fatturazione o contabilità generale.

## D008 – Architettura

- PHP 8.4;
- Symfony 8.1;
- SQLite;
- Doctrine ORM;
- Twig e Symfony Forms;
- Tabler 1.4.0 locale;
- controller classici;
- niente EasyAdmin;
- applicazione monolitica modulare;
- rete interna.

## D009 – Qualità

Ogni milestone richiede ZIP completo, documentazione aggiornata, test, analisi statica e validazione locale esplicita prima di diventare baseline ufficiale.

## D010 – Economia gestionale M5

- importi memorizzati come centesimi interi;
- preventivo a corpo separato dai costi interni;
- tariffa risolta per specificità: attività, commessa, collaboratore, standard applicazione;
- tariffa e costo congelati sulla registrazione ore conclusa;
- spese e incassi non rappresentano documenti fiscali;
- i soci consultano il riepilogo economico completo; i collaboratori consultano e gestiscono soltanto le proprie spese secondo D016.

## D011 – Responsabilità e lavoro effettivo sono distinti

- l’assegnatario identifica la responsabilità operativa dell’attività;
- l’autore della registrazione identifica chi ha lavorato;
- la vista `Attività` filtra per assegnatario;
- la vista `Ore` filtra e riepiloga il lavoro effettivo;
- i timer attivi non entrano nel consuntivato consolidato;
- il report globale non mostra dati economici, perché i relativi permessi dipendono dalla commessa.


## D012 – Chiusura e controllo sono proiezioni

- non vengono aggiunti stati duplicati o campi booleani di chiusura;
- la chiusura operativa deriva da stato commessa, attività e timer;
- la chiusura economica deriva da preventivo e incassi;
- una commessa è ferma dopo 14 giorni senza aggiornamenti operativi;
- una persona è sovraccarica oltre 8 attività aperte o 40 ore residue;
- l’area trasversale `Controllo` è riservata ai soci perché espone costi, margini e incassi;
- filtri e ordinamento sono persistiti soltanto nella sessione, non nel database.

## D013 – Valutazione collaboratori basata sulle registrazioni effettive

- la valutazione giornaliera usa `TimeEntry.user`, non l’assegnatario dell’attività;
- ogni giornata è determinata dalla data di inizio della registrazione, coerentemente con i report M6.1 e M6.2;
- i timer ancora aperti sono esclusi dai totali fino alla chiusura;
- la pagina mostra il lavoro dichiarato nella descrizione, la commessa, l’attività, l’intervallo e la durata;
- la valutazione trasversale è riservata ai soci e non introduce approvazioni o giudizi persistiti.

## D014 – Form espliciti e responsive

- ogni campo configurato nei Symfony Form Type deve essere renderizzato esplicitamente nel relativo template;
- `form_widget(form)` non viene usato nei form CRUD, per evitare campi automatici in posizioni impreviste;
- `form_rest()` è ammesso soltanto prima del pulsante per token e campi nascosti, con `render_rest: false` alla chiusura;
- i campi compatti usano due colonne da `6/12` soltanto da breakpoint `lg`;
- su schermi piccoli e medi tutti i campi occupano la larghezza completa;
- descrizioni, note, indirizzi e altri testi estesi restano sempre su riga intera;
- i pulsanti principali di creazione e salvataggio occupano l’intera larghezza disponibile.


## D015 – Tabelle e navigazione coerenti

- tutte le tabelle applicative usano DataTables e Responsive con integrazione Bootstrap 5;
- gli asset JavaScript e CSS vengono installati con npm e serviti localmente, senza CDN a runtime;
- ricerca e ordinamento DataTables affiancano i filtri di dominio già presenti e non sostituiscono la paginazione server-side del report `Ore`;
- sotto il breakpoint `lg` le strutture applicative multi-colonna tornano sempre a una colonna;
- nelle tabelle non esistono colonne generiche `Apri` o `Azioni`;
- il collegamento principale è applicato al nome, codice, titolo o descrizione leggibile dell’entità;
- quando esiste una pagina di dettaglio il collegamento principale la apre; quando non esiste, apre direttamente la modifica;
- archiviazioni ed eliminazioni sono collocate nelle schermate di modifica, non nelle tabelle o nelle pagine di consultazione.

## M7 – Allegati e documenti

- I contenuti binari restano fuori da SQLite e fuori da `public`; il database conserva soltanto metadati e chiave dello storage.
- Ogni documento appartiene a una commessa e può riferirsi a una sola attività della stessa commessa.
- La classificazione non introduce livelli di segretezza aggiuntivi: la visibilità segue quella generale delle commesse.
- Il download avviene esclusivamente tramite controller autenticato e voter.
- Tutti i collaboratori possono caricare su commesse attive; la gestione successiva è limitata a uploader, responsabile, assegnatario/creatore dell’attività e soci.
- Limite iniziale di 10 MiB e allowlist ristretta; archivi ZIP generici, eseguibili e formati con macro non sono ammessi.
- I controlli integrati non vengono presentati come antivirus completo; la directory può essere sorvegliata da un antivirus di sistema.
- Backup e ripristino devono includere insieme database SQLite e directory documentale.


## D016 – Economia per ruolo e report mensile

- la pagina economica della commessa è consultabile da tutti gli utenti autenticati, ma la risposta è costruita in base al ruolo;
- i soci ricevono il quadro economico completo;
- i collaboratori ricevono soltanto le spese registrate dal proprio account e non ricevono incassi, margini o preventivi;
- ogni collaboratore può gestire esclusivamente le proprie spese tramite voter;
- il report mensile completo è riservato ai soci perché combina costi, incassi e audit globale;
- le registrazioni ore appartengono al mese della data di inizio; i timer aperti sono elencati ma non sommati;
- gli indicatori mensili sono storici per i movimenti, mentre stato, avanzamento e residuo delle attività sono proiezioni correnti esplicitamente dichiarate;
- il CSV esporta il dettaglio ore in UTF-8 con separatore `;`;
- M8 non introduce nuovi campi o migrazioni.

## D017 – Ore dashboard riferite al mese corrente

- la card `Ore effettuate` usa soltanto registrazioni concluse;
- l’attribuzione mensile segue la data di inizio della registrazione, come nel report mensile;
- l’intervallo è chiuso a sinistra e aperto a destra: dal primo giorno del mese incluso al primo giorno del mese successivo escluso;
- timer aperti e registrazioni iniziate nei mesi precedenti non contribuiscono al totale;
- le ore pianificate non sono più mostrate in dashboard e il relativo aggregato inutilizzato viene rimosso.
