# Consuntivazione ore

`TimeEntry` collega attività e persona che ha lavorato. Inizio e fine sono autoritativi; la durata è sempre calcolata, evitando dati duplicati.

## Regole

- un solo timer attivo per persona;
- fine successiva all’inizio;
- nessuna sovrapposizione tra registrazioni della stessa persona;
- soci modificano ogni registrazione; collaboratori solo le proprie;
- nessuna nuova registrazione su commesse archiviate;
- le fixtures sono esplicite e idempotenti.

## Assegnatario e autore delle ore

`Activity.assignee` identifica la persona responsabile dell’attività. `TimeEntry.user` identifica chi ha effettivamente lavorato. I due valori possono coincidere, ma non esiste alcun vincolo che lo imponga.

La pagina `/attivita` filtra per assegnatario. La pagina `/ore` filtra invece per persona che ha lavorato e permette di consultare trasversalmente tutte le registrazioni.

## Consuntivato consolidato

I totali consolidati includono soltanto registrazioni concluse. I timer attivi restano visibili nelle pagine delle ore, ma non entrano nel totale finché non vengono fermati.

Nel dettaglio commessa, una singola query aggrega le durate per attività e persona. Il totale dell’attività deriva dalla somma degli stessi contributi, evitando differenze e query N+1.

## Report globale

`/ore` offre:

- filtri per periodo, commessa, attività, persona e fatturabilità;
- periodo applicato alla data e ora di inizio della registrazione;
- riepiloghi calcolati sull’intero risultato filtrato, non soltanto sulle 50 righe correnti;
- paginazione a 50 registrazioni;
- collegamenti verso commessa e attività;
- timer attivi visibili ma esclusi dalle ore consuntivate fino alla chiusura;
- nessuna colonna tariffa/costo, perché la visibilità economica dipende dalla singola commessa.

## Snapshot economico M5

Nell’inserimento manuale la tariffa viene congelata al salvataggio; per il timer viene congelata all’avvio e il costo viene calcolato alla chiusura. Una modifica dell’intervallo ricalcola il costo con la tariffa storica già congelata.

Le durate sono mostrate con il filtro Twig `duration_hm`, senza ciclo sulle 24 ore: 27 ore e 30 minuti sono `27:30`.

## Valutazione giornaliera dei collaboratori

Dalla tabella `Carico per persona` dell’area soci `Controllo` si apre `/controllo/collaboratori/{id}`. La pagina raggruppa le registrazioni concluse per data di inizio e mostra:

- totale del giorno e quota fatturabile;
- commessa e attività;
- intervallo orario;
- descrizione del lavoro svolto;
- durata della singola registrazione;
- totale del periodo, giornate lavorate, media per giornata, registrazioni e commesse coinvolte.

I filtri consentono di restringere la valutazione per periodo, cliente, responsabile della commessa, commessa e fatturabilità. La pagina usa una sola query per le registrazioni e costruisce i gruppi giornalieri in memoria, senza query per singolo giorno.
