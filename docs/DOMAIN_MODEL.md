# Modello del dominio

## User

Utente autenticabile con ruolo Socio o Collaboratore. Conserva anche la tariffa oraria standard individuale, visibile e modificabile soltanto dai soci.

## Client

Anagrafica essenziale del cliente, archiviabile senza cancellazione fisica.

## Project

Commessa con codice annuale, cliente, responsabile unico, stato, priorità, date, descrizione e nota riservata. M5 aggiunge:

- `estimatedAmountCents`: importo preventivato a corpo;
- `defaultHourlyRateCents`: tariffa oraria della commessa.

## Activity

Sottoattività assegnata a un utente, socio o collaboratore. Registra stato, priorità, avanzamento, stima iniziale e residua, date e tariffa oraria specifica opzionale.

## TimeEntry

Intervallo di lavoro associato a un'attività e alla persona che ha realmente lavorato, indipendentemente dall’assegnatario. La durata deriva da inizio e fine e non viene duplicata nel database.

Quando l'intervallo viene concluso, M5 congela:

- tariffa oraria applicata;
- costo totale calcolato.

Le modifiche future alle tariffe non alterano la storia. Modificando gli estremi temporali si ricalcola il costo usando la tariffa già congelata.

## Risoluzione della tariffa

Ordine di precedenza:

1. tariffa specifica dell'attività;
2. tariffa della commessa;
3. tariffa standard del collaboratore;
4. tariffa generale dell'applicazione.

Un valore pari a zero indica che il livello non definisce una tariffa.

## Expense

Spesa associata obbligatoriamente alla commessa e facoltativamente a un'attività. Registra data, categoria, descrizione, importo e indicazione rimborsabile.

## Payment

Incasso semplice associato alla commessa. Registra data, importo, descrizione, metodo, riferimento e note. Non rappresenta una fattura e non gestisce IVA o prima nota.

## Dati economici derivati

Non vengono memorizzati campi duplicati per:

- costo totale = costo ore + spese;
- residuo da incassare = max(preventivo − incassato, 0);
- margine gestionale = preventivo − costo totale;
- superamento budget = costo totale maggiore del preventivo.

## Proiezioni M6.1

Le viste di rendicontazione non introducono nuove entità o colonne:

- il dettaglio commessa aggrega `TimeEntry` per attività e utente;
- `/attivita` continua a interrogare `Activity.assignee`;
- `/ore` interroga `TimeEntry.user` e applica filtri trasversali;
- la paginazione e i riepiloghi sono proiezioni di query, non stato persistito.


## Proiezioni M6.2

M6.2 non introduce entità o colonne. `ProjectControlService` costruisce proiezioni aggregate a partire da commesse, attività, registrazioni, spese e incassi.

La chiusura è derivata:

- operativa: stato della commessa, attività aperte e timer attivi;
- economica: preventivo e incassi, con annullamento non applicabile;
- complessiva: combinazione delle due precedenti.

Anche commessa ferma, sovraccarico, scostamento ore, superamento preventivo e saldo gestionale del periodo sono indicatori calcolati, non stato persistito.

### Valutazione giornaliera M6.2 Hotfix 1

La valutazione collaboratore resta una proiezione senza persistenza aggiuntiva. `ControlRepository` seleziona le registrazioni concluse della persona nel periodo; `CollaboratorEvaluationService` le raggruppa per data di inizio e calcola totali giornalieri e complessivi. Non esistono entità di valutazione, approvazione o punteggio.


## Attachment

Documento associato obbligatoriamente a una `Project` e facoltativamente a una `Activity` appartenente alla stessa commessa. Conserva:

- classificazione documentale;
- nome originale;
- chiave casuale dello storage protetto;
- MIME rilevato;
- dimensione;
- impronta SHA-256;
- descrizione opzionale;
- autore e data del caricamento.

Il contenuto binario non viene memorizzato in SQLite e non è pubblicato nel document root. La relazione database–filesystem è intenzionale: backup e ripristino devono trattare i due insiemi come un’unica unità.
